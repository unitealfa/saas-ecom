<?php

namespace App\Services\Tenant\Auth;

use App\Models\Tenant\Users\Passkey;
use App\Models\Tenant\Users\User;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Support\WebAuthn;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;

final class TenantPasskeys
{
    public function registrationOptions(User $user, string $host): PublicKeyCredentialCreationOptions
    {
        $generator = new class($host) extends GenerateRegistrationOptions
        {
            public function __construct(private readonly string $host) {}

            protected function relyingParty(): PublicKeyCredentialRpEntity
            {
                return PublicKeyCredentialRpEntity::create(name: $this->host, id: $this->host);
            }
        };

        return $generator($user);
    }

    public function loginOptions(string $host): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32), rpId: $host, allowCredentials: [],
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: 60000,
        );
    }

    public function register(User $user, string $name, PublicKeyCredential $credential, PublicKeyCredentialCreationOptions $options, string $host, string $origin): Passkey
    {
        $store = new class($host, $origin) extends StorePasskey
        {
            public function __construct(private readonly string $host, private readonly string $origin) {}

            protected function validate(AuthenticatorAttestationResponse $response, PublicKeyCredentialCreationOptions $options): CredentialRecord
            {
                return TenantPasskeys::attestationValidator($this->origin)->check($response, $options, $this->host);
            }

            protected function ensureCredentialIsUnique(CredentialRecord $source): void
            {
                if (Passkey::where('credential_id', Base64UrlSafe::encodeUnpadded($source->publicKeyCredentialId))->exists()) {
                    throw InvalidPasskeyException::make('Cette clé existe déjà.');
                }
            }
        };

        $passkey = $store($user, $name, $credential, $options);
        if (! $passkey instanceof Passkey) {
            throw new \LogicException('Invalid tenant passkey model.');
        }

        return $passkey;
    }

    public function verify(PublicKeyCredential $credential, PublicKeyCredentialRequestOptions $options, string $host, string $origin): Passkey
    {
        $verify = new class($host, $origin) extends VerifyPasskey
        {
            public function __construct(private readonly string $host, private readonly string $origin) {}

            public function getPasskey(PublicKeyCredential $credential, bool $lock = false): Passkey
            {
                $query = Passkey::where('credential_id', Base64UrlSafe::encodeUnpadded($credential->rawId));

                return ($lock ? $query->lockForUpdate() : $query)->first()
                    ?? throw InvalidPasskeyException::make('Clé d’accès inconnue.');
            }

            protected function validate(AuthenticatorAssertionResponse $response, \Laravel\Passkeys\Passkey $passkey, PublicKeyCredentialRequestOptions $options): CredentialRecord
            {
                $source = WebAuthn::fromJson(json_encode($passkey->credential, JSON_THROW_ON_ERROR), CredentialRecord::class);

                return TenantPasskeys::assertionValidator($this->origin)->check($source, $response, $options, $this->host, $source->userHandle);
            }
        };

        $passkey = $verify($credential, $options);
        if (! $passkey instanceof Passkey) {
            throw new \LogicException('Invalid tenant passkey model.');
        }

        return $passkey;
    }

    public static function attestationValidator(string $origin): AuthenticatorAttestationResponseValidator
    {
        $factory = self::factory($origin);
        $support = AttestationStatementSupportManager::create();
        $support->add(NoneAttestationStatementSupport::create());
        $factory->setAttestationStatementSupportManager($support);

        return AuthenticatorAttestationResponseValidator::create(ceremonyStepManager: $factory->creationCeremony());
    }

    public static function assertionValidator(string $origin): AuthenticatorAssertionResponseValidator
    {
        return AuthenticatorAssertionResponseValidator::create(ceremonyStepManager: self::factory($origin)->requestCeremony());
    }

    private static function factory(string $origin): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory;
        $factory->setAllowedOrigins([$origin]);

        return $factory;
    }
}
