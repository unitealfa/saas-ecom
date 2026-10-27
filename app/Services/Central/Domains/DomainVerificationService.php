<?php

declare(strict_types=1);

namespace App\Services\Central\Domains;

use App\Enums\Central\Domains\CertificateStatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Domains\Domain;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Support\Facades\Gate;

class DomainVerificationService
{
    private const string EVENT_CHECKED = 'domain_verification_checked';

    /** Attributs suivis dans l'audit avant et après le contrôle. */
    private const array TRACKED = ['verification_status', 'verified_at', 'certificate_status'];

    private const string MESSAGE_VERIFIED = 'Domaine vérifié.';

    private const string MESSAGE_UNAVAILABLE = 'La vérification DNS est impossible pour le moment. Les preuves et l’accès existants sont conservés. Vérifiez la configuration ou réessayez après propagation DNS.';

    private const string MESSAGE_MISMATCH = 'La preuve TXT ou le pointage DNS ne correspond pas à cette boutique. Vérifiez la configuration affichée.';

    private const string MESSAGE_HTTPS_UNAVAILABLE = 'DNS vérifié. Le contrôle HTTPS est temporairement indisponible ; son dernier état connu est conservé.';

    private const string MESSAGE_HTTPS_FAILED = 'DNS vérifié, mais le contrôle HTTPS échoue. Vérifiez le certificat, le pointage et la configuration TLS du serveur puis relancez le contrôle.';

    public function __construct(
        private DomainService $domains,
        private DomainDnsInspector $inspector,
        private AuthorizationMutation $mutations,
    ) {}

    /** @return array{successful: bool, message: string} */
    public function verify(User $actor, Domain $domain, string $expectedState): array
    {
        Gate::forUser($actor)->authorize('verify', $domain);

        // Les contrôles réseau (DNS, TLS) peuvent durer plusieurs secondes : ils se font avant de prendre les verrous.
        // L'état attendu est comparé ensuite, donc un domaine modifié entre-temps est refusé.
        $result = $this->inspector->inspect($domain);

        return $this->mutations->run(
            $actor,
            'verify',
            fn () => $this->domains->lock($domain, $expectedState),
            function (Domain $domain) use ($actor, $result): array {
                $before = $domain->only(self::TRACKED);

                $this->record($domain, $result);
                $this->audit($actor, $domain, $before, $result);

                return $this->outcome($domain, $result);
            },
        );
    }

    /**
     * Un contrôle impossible (null) ne change rien. Le certificat n'est réécrit que si le DNS a échoué
     * ou si le contrôle HTTPS a répondu : sinon le dernier état connu est conservé.
     *
     * @param  array{verified: ?bool, certificate: ?CertificateStatusEnum}  $result
     */
    private function record(Domain $domain, array $result): void
    {
        if ($result['verified'] === null) {
            return;
        }

        $verified = $result['verified'];

        $attributes = [
            'verification_status' => $verified ? VerificationStatusEnum::VERIFIED : VerificationStatusEnum::FAILED,
            'verified_at' => $verified ? now() : null,
        ];

        if (! $verified || $result['certificate'] !== null) {
            $attributes['certificate_status'] = $result['certificate'];
        }

        $domain->forceFill($attributes)->save();
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array{verified: ?bool, certificate: ?CertificateStatusEnum}  $result
     */
    private function audit(User $actor, Domain $domain, array $before, array $result): void
    {
        activity()->causedBy($actor)->performedOn($domain)->event(self::EVENT_CHECKED)
            ->withProperties([
                'before' => $before,
                'after' => $domain->only(array_keys($before)),
                'dns_check' => $result['verified'],
                'https_check' => $result['certificate']?->value,
            ])
            ->log('Contrôle DNS et HTTPS du domaine.');
    }

    /**
     * @param  array{verified: ?bool, certificate: ?CertificateStatusEnum}  $result
     * @return array{successful: bool, message: string}
     */
    private function outcome(Domain $domain, array $result): array
    {
        if ($result['verified'] === null) {
            return $this->failure(self::MESSAGE_UNAVAILABLE);
        }

        if (! $result['verified']) {
            return $this->failure(self::MESSAGE_MISMATCH);
        }

        if ($domain->usesLocalHttp() || $result['certificate'] === CertificateStatusEnum::ACTIVE) {
            return ['successful' => true, 'message' => self::MESSAGE_VERIFIED];
        }

        return $this->failure($result['certificate'] === null ? self::MESSAGE_HTTPS_UNAVAILABLE : self::MESSAGE_HTTPS_FAILED);
    }

    /** @return array{successful: false, message: string} */
    private function failure(string $message): array
    {
        return ['successful' => false, 'message' => $message];
    }
}
