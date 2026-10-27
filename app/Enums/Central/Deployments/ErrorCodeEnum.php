<?php

namespace App\Enums\Central\Deployments;

/** Codes d'erreur enregistrés dans tenant_schema_deployments.error_code, avec leur explication pour l'administration. */
enum ErrorCodeEnum: string
{
    case PROVISIONING_FAILED = 'PROVISIONING_FAILED';
    case MIGRATION_FAILED = 'MIGRATION_FAILED';
    case DEPLOYMENT_EXHAUSTED = 'DEPLOYMENT_EXHAUSTED';

    /** Ancien code, conservé pour lire les anciennes lignes. */
    case PROVISIONING_EXHAUSTED = 'PROVISIONING_EXHAUSTED';

    private const string FALLBACK = 'L’opération technique a échoué. Vérifiez les prérequis et relancez une tentative autorisée.';

    /** Explication d'un code, ou message générique pour un code absent ou inconnu. */
    public static function explain(?string $code): string
    {
        return self::tryFrom((string) $code)?->explanation() ?? self::FALLBACK;
    }

    public function explanation(): string
    {
        return match ($this) {
            self::PROVISIONING_FAILED => 'PROVISIONING_FAILED : la création, les migrations ou l’initialisation de la boutique n’ont pas abouti. Une reprise conserve la réservation.',
            self::MIGRATION_FAILED => 'MIGRATION_FAILED : la mise à jour n’a pas abouti. Des migrations MySQL peuvent déjà avoir été appliquées ; la reprise utilise leur historique, sans retour automatique en arrière.',
            self::DEPLOYMENT_EXHAUSTED, self::PROVISIONING_EXHAUSTED => 'DEPLOYMENT_EXHAUSTED : le traitement a été interrompu ou ses tentatives sont épuisées. Vérifiez le worker et relancez une tentative autorisée.',
        };
    }
}