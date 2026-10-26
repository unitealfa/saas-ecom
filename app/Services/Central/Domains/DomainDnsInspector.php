<?php

declare(strict_types=1);

namespace App\Services\Central\Domains;

use App\Enums\Central\Domains\CertificateStatusEnum;
use App\Enums\Central\Domains\TypeEnum;
use App\Models\Central\Domain;

class DomainDnsInspector
{
    public const string TXT_PREFIX = '_aydra-verification.';

    private const string TOKEN_PREFIX = 'aydra=';

    private const int TLS_TIMEOUT_SECONDS = 5;

    private const int TLS_PORT = 443;

    /** Nombre maximal d'adresses essayées pour le certificat (une adresse injoignable ne doit pas tout bloquer). */
    private const int TLS_MAX_ADDRESSES = 2;

    /** Jeton à publier dans le TXT du domaine : signé, lié au domaine, à la boutique et à l'identifiant public. */
    public function token(Domain $domain): string
    {
        return self::TOKEN_PREFIX.hash_hmac('sha256', $domain->uuid.'|'.$domain->tenant_id.'|'.$domain->domain, config('app.key'));
    }

    /**
     * verified : true = conforme, false = non conforme, null = contrôle impossible pour l'instant.
     *
     * @return array{verified: ?bool, certificate: ?CertificateStatusEnum}
     */
    public function inspect(Domain $domain): array
    {
        if ($domain->type === TypeEnum::SUBDOMAIN && $domain->usesLocalHttp()) {
            return ['verified' => true, 'certificate' => null];
        }

        if ($domain->type === TypeEnum::CUSTOM) {
            $owned = $this->ownsDomain($domain);

            if ($owned !== true) {
                return ['verified' => $owned, 'certificate' => null];
            }
        }

        $verified = $this->routesToPlatform($domain->domain);

        return [
            'verified' => $verified,
            'certificate' => $verified === true ? $this->certificate($domain->domain) : null,
        ];
    }

    /** @return list<array<string, mixed>>|null NULL signifie que le contrôle DNS a échoué techniquement. */
    protected function records(string $host, int $type): ?array
    {
        $records = @dns_get_record($host, $type);

        return $records === false ? null : $records;
    }

    protected function certificate(string $host): ?CertificateStatusEnum
    {
        $addresses = $this->addresses($host);

        if ($addresses === null) {
            return null;
        }

        if ($addresses === [] || ! $this->arePublic($addresses)) {
            return CertificateStatusEnum::ERROR;
        }

        $rejected = false;

        foreach (array_slice($addresses, 0, self::TLS_MAX_ADDRESSES) as $address) {
            $status = $this->handshake($host, $address);

            if ($status === CertificateStatusEnum::ACTIVE) {
                return $status;
            }

            $rejected = $rejected || $status === CertificateStatusEnum::ERROR;
        }

        return $rejected ? CertificateStatusEnum::ERROR : null;
    }

    /** null = pas de réponse, true = enregistrement trouvé, false = enregistrement absent ou incorrect. */
    private function ownsDomain(Domain $domain): ?bool
    {
        $txt = $this->records(self::TXT_PREFIX.$domain->domain, DNS_TXT);

        if ($txt === null || $txt === []) {
            return null;
        }

        $expected = $this->token($domain);

        return collect($txt)->contains(
            fn (array $record): bool => hash_equals($expected, (string) ($record['txt'] ?? '')),
        );
    }

    private function routesToPlatform(string $host): ?bool
    {
        $base = $this->platformHost();

        if ($base === '') {
            return null;
        }

        $cnames = $this->records($host, DNS_CNAME);

        if ($cnames !== null && $this->pointsTo($cnames, $base)) {
            return true;
        }

        $actual = $this->addresses($host);
        $expected = $this->addresses($base);

        if ($actual === null || $expected === null || $actual === [] || $expected === []) {
            return null;
        }

        if (! $this->arePublic($actual) || ! $this->arePublic($expected)) {
            return false;
        }

        if (array_diff($actual, $expected) === []) {
            return true;
        }

        return $cnames === null ? null : false;
    }

    private function platformHost(): string
    {
        return strtolower(rtrim((string) config('tenancy.saas_base_domain'), '.'));
    }

    /** @param list<array<string, mixed>> $cnames */
    private function pointsTo(array $cnames, string $base): bool
    {
        return collect($cnames)->contains(
            fn (array $record): bool => strtolower(rtrim((string) ($record['target'] ?? ''), '.')) === $base,
        );
    }

    /** @return list<string>|null */
    private function addresses(string $host): ?array
    {
        $records = $this->records($host, DNS_A | DNS_AAAA);

        if ($records === null) {
            return null;
        }

        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false) {
                $packed = inet_pton($address);
                $normalized = $packed === false ? false : inet_ntop($packed);

                if ($normalized === false) {
                    return [];
                }

                $addresses[] = $normalized;
            } elseif ($address !== null) {
                return [];
            }
        }

        return array_values(array_unique($addresses));
    }

    /** @param list<string> $addresses */
    private function arePublic(array $addresses): bool
    {
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /** Connexion à une IP publique figée ; le certificat est vérifié pour le vrai nom via SNI. */
    protected function handshake(string $host, string $address): ?CertificateStatusEnum
    {
        $endpoint = str_contains($address, ':') ? '['.$address.']' : $address;
        $context = stream_context_create(['ssl' => [
            'peer_name' => $host,
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
        ]]);

        $socket = @stream_socket_client(
            'tcp://'.$endpoint.':'.self::TLS_PORT,
            $errorCode,
            $errorMessage,
            self::TLS_TIMEOUT_SECONDS,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($socket === false) {
            return null;
        }

        try {
            stream_set_timeout($socket, self::TLS_TIMEOUT_SECONDS);
            $valid = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

            if ($valid !== true && stream_get_meta_data($socket)['timed_out']) {
                return null;
            }

            return $valid === true ? CertificateStatusEnum::ACTIVE : CertificateStatusEnum::ERROR;
        } finally {
            fclose($socket);
        }
    }
}
