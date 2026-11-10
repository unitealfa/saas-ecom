<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Évolution centrale UNIQUEMENT : isoler les échéances actuellement stockées
 * dans subscriptions(record_type=2) sans changer leurs ID/UUID, factures ni audits.
 *
 * DDL MySQL = commits implicites. Exécuter en maintenance après backup vérifié,
 * sur un clone MySQL 8.4 en premier. Cette migration ne possède PAS de rollback
 * destructeur ; sa reprise après un échec DDL exige une intervention DBA.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new LogicException('La séparation des échéances exige MySQL 8.4.');
        }

        if (tenancy()->initialized) {
            throw new LogicException('La migration doit être exécutée sur la base centrale uniquement.');
        }

        if (! Schema::hasTable('subscription_requests') || ! Schema::hasColumn('subscriptions', 'record_type')
            || ! Schema::hasColumn('subscriptions', 'parent_subscription_id')
            || Schema::hasTable('subscription_installments')) {
            throw new LogicException('Schéma central inattendu. Vérifier les migrations déjà appliquées.');
        }

        $this->assertLegacyDataIntegrity();

        // La même clé UNIQUE garantit désormais l'appartenance d'une échéance
        // au bon propriétaire, sans discriminant record_type partagé.
        DB::statement('ALTER TABLE `subscriptions` ADD UNIQUE INDEX `uq_subscriptions_id_user` (`id`, `user_id`)');

        Schema::create('subscription_installments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid'); // SchemaBlueprint Aydra : ASCII/ascii_bin + UNIQUE.
            $table->foreignId('user_id')->constrained('users', indexName: 'fk_subscription_installments_user')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('subscription_id')->constrained('subscriptions', indexName: 'fk_subscription_installments_subscription')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->string('installment_number')->unique();
            $table->string('operation_key')->unique();
            $table->decimal('installment_amount'); // SchemaBlueprint Aydra : DECIMAL(14,2).
            $table->unsignedTinyInteger('installment_status')->comment('InstallmentStatusEnum: 1, 2, 3, 4');
            $table->dateTime('period_starts_at');
            $table->dateTime('period_ends_at');
            $table->dateTime('due_at');
            $table->timestamps();

            $table->unique(['id', 'subscription_id', 'user_id'], 'uq_subscription_installments_context');
            $table->index(['subscription_id', 'installment_status', 'due_at'], 'ix_subscription_installments_due');
            $table->foreign(['subscription_id', 'user_id'], 'fk_subscription_installments_owner')
                ->references(['id', 'user_id'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
        });

        // Copie exacte, y compris ID, UUID, clé idempotente et dates d'historique.
        DB::statement(<<<'SQL'
INSERT INTO `subscription_installments`
 (`id`, `uuid`, `user_id`, `subscription_id`, `installment_number`, `operation_key`,
  `installment_amount`, `installment_status`, `period_starts_at`, `period_ends_at`,
  `due_at`, `created_at`, `updated_at`)
SELECT `id`, `uuid`, `user_id`, `parent_subscription_id`, `installment_number`, `operation_key`,
       `installment_amount`, `installment_status`, `period_starts_at`, `period_ends_at`,
       `due_at`, `created_at`, `updated_at`
FROM `subscriptions` WHERE `record_type` = 2
SQL);

        $this->assertCopiedExactly();

        // Facture -> échéance : on bascule les FKs APRÈS la copie et AVANT
        // l'effacement des anciennes lignes enfant. Les IDs restent identiques.
        // Créer les nouveaux index AVANT de retirer les anciens : les FK
        // simples encore actives ont besoin d'un index de même préfixe.
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->index(['subscription_id', 'user_id'], 'ix_invoice_subscription_owner');
            $table->index(['installment_id', 'subscription_id', 'user_id'], 'ix_invoice_installment_context');
        });
        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropForeign('fk_saas_invoices_b991245fce');
            $table->dropForeign('fk_saas_invoices_b0a744b4f2');
            $table->dropForeign('fk_saas_invoices_fcc56c4a07');
            $table->dropIndex('ix_saas_invoices_b991245fce');
            $table->dropIndex('ix_saas_invoices_fcc56c4a07');
        });

        Schema::table('subscription_requests', function (Blueprint $table): void {
            $table->dropForeign('fk_request_subscription');
        });

        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->dropColumn(['subscription_record_type', 'installment_record_type']);
        });
        Schema::table('subscription_requests', function (Blueprint $table): void {
            $table->dropColumn('subscription_record_type');
        });

        Schema::table('saas_invoices', function (Blueprint $table): void {
            $table->foreign('installment_id', 'fk_saas_invoices_b0a744b4f2')
                ->references('id')->on('subscription_installments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['subscription_id', 'user_id'], 'fk_invoice_subscription_owner')
                ->references(['id', 'user_id'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['installment_id', 'subscription_id', 'user_id'], 'fk_invoice_installment_context')
                ->references(['id', 'subscription_id', 'user_id'])->on('subscription_installments')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('subscription_requests', function (Blueprint $table): void {
            $table->index(['current_subscription_id', 'user_id'], 'ix_request_subscription_owner');
            $table->foreign(['current_subscription_id', 'user_id'], 'fk_request_subscription')
                ->references(['id', 'user_id'])->on('subscriptions')->restrictOnDelete()->restrictOnUpdate();
        });

        // Les références des documents pointent désormais vers les deux
        // vraies tables. On peut retirer les anciennes lignes enfant.
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropForeign('fk_subscriptions_e56456f06c');
            $table->dropForeign('fk_subscriptions_acf499a627');
        });
        $deleted = DB::table('subscriptions')->where('record_type', 2)->delete();
        $copied = DB::table('subscription_installments')->count();
        if ($deleted !== $copied) {
            throw new LogicException('Nombre de lignes déplacées incohérent. Stop : restaurer la sauvegarde.');
        }

        // Anciennes contraintes de table double, devenues inadaptées.
        DB::unprepared('DROP TRIGGER IF EXISTS `guard_subscriptions_e2fc712050`');
        DB::unprepared('DROP TRIGGER IF EXISTS `guard_subscriptions_96518beadc`');
        foreach ([
            'ck_subscriptions_cbccd3efcf', // record_type 1/2
            'ck_subscriptions_990cf763fa', // statut échéance
            'ck_subscriptions_cf8f2c9eca', // forme parent/enfant fusionnée
            'ck_subscriptions_a056276a62', // montants fusionnés
            'ck_subscription_request_type', // abonnement record_type=1
        ] as $check) {
            DB::statement("ALTER TABLE `subscriptions` DROP CHECK `{$check}`");
        }

        // Conserver une entrée d'index dédiée au FK user_id pendant le
        // remplacement de l'index UNIQUE actif existant.
        DB::statement('ALTER TABLE `subscriptions` ADD INDEX `ix_subscriptions_owner` (`user_id`)');
        // Conserver l'unicité d'un seul abonnement actif par propriétaire.
        DB::statement('ALTER TABLE `subscriptions` DROP INDEX `uq_subscriptions_f59d836b7a`');
        DB::statement('ALTER TABLE `subscriptions` MODIFY `active_owner_slot` TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN `status` = 3 THEN 1 ELSE NULL END) STORED');
        DB::statement('ALTER TABLE `subscriptions` ADD UNIQUE INDEX `uq_subscriptions_f59d836b7a` (`user_id`, `active_owner_slot`)');

        foreach ([
            'uq_subscriptions_8ef95e0c44',
            'uq_subscriptions_f5e32fea0c',
            'ix_subscriptions_c0c4780fba',
            'ix_subscriptions_2606f12b0d',
            'ix_subscriptions_e56456f06c',
        ] as $index) {
            DB::statement("ALTER TABLE `subscriptions` DROP INDEX `{$index}`");
        }
        // Index UNIQUE simple de la colonne installment_number.
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropUnique('subscriptions_installment_number_unique'));

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'parent_record_type', 'parent_subscription_id', 'record_type',
                'installment_number', 'installment_amount', 'installment_status', 'due_at',
            ]);
        });

        // Vérifications SQL explicites : champs requis, montant/état,
        // appartenance parent et immutabilité de l'identité.
        DB::statement('ALTER TABLE `subscriptions` ADD CONSTRAINT `ck_subscriptions_parent_required` CHECK (`plan_id` IS NOT NULL AND `status` IS NOT NULL AND `period` IS NOT NULL AND `agreed_amount` IS NOT NULL AND `started_at` IS NOT NULL AND `auto_renew` IS NOT NULL AND `agreed_amount` >= 0)');
        DB::statement('ALTER TABLE `subscription_installments` ADD CONSTRAINT `ck_subscription_installments_shape` CHECK (`installment_amount` >= 0 AND `installment_status` IN (1,2,3,4) AND `period_ends_at` > `period_starts_at` AND CHAR_LENGTH(TRIM(`installment_number`)) > 0)');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER `guard_subscriptions_e2fc712050` BEFORE UPDATE ON `subscriptions`
FOR EACH ROW BEGIN
    IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`uuid` <=> NEW.`uuid`)
       OR NOT (OLD.`user_id` <=> NEW.`user_id`)
    THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable subscription identity'; END IF;
END
SQL);
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `guard_subscription_installments_identity` BEFORE UPDATE ON `subscription_installments`
FOR EACH ROW BEGIN
    IF NOT (OLD.`id` <=> NEW.`id`) OR NOT (OLD.`uuid` <=> NEW.`uuid`)
       OR NOT (OLD.`user_id` <=> NEW.`user_id`)
       OR NOT (OLD.`subscription_id` <=> NEW.`subscription_id`)
    THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable subscription installment identity'; END IF;
END
SQL);

        // Contrôles d'invariants a posteriori (sans écrire d'autres données).
        if (DB::table('subscriptions')->whereNull('plan_id')->orWhereNull('status')->exists()) {
            throw new LogicException('Une ligne parent invalide subsiste. Restaurer la sauvegarde.');
        }
        $this->assertInvoiceReferences();
    }

    private function assertLegacyDataIntegrity(): void
    {
        $badTypes = DB::table('subscriptions')->whereNotIn('record_type', [1, 2])->exists();
        $badParents = DB::table('subscriptions as d')
            ->leftJoin('subscriptions as s', function ($join): void {
                $join->on('s.id', '=', 'd.parent_subscription_id')->on('s.user_id', '=', 'd.user_id')
                    ->where('s.record_type', '=', 1);
            })->where('d.record_type', 2)->whereNull('s.id')->exists();
        if ($badTypes || $badParents) {
            throw new LogicException('Enregistrements subscriptions hérités invalides : opération annulée.');
        }
        $invalidInvoice = DB::table('saas_invoices as i')
            ->leftJoin('subscriptions as d', 'd.id', '=', 'i.installment_id')
            ->where(function ($query): void {
                $query->whereNull('d.id')->orWhere('d.record_type', '<>', 2)
                    ->orWhereColumn('d.parent_subscription_id', '<>', 'i.subscription_id')
                    ->orWhereColumn('d.user_id', '<>', 'i.user_id');
            })->exists();
        if ($invalidInvoice) {
            throw new LogicException('Une facture référence une échéance invalide : opération annulée.');
        }
    }

    private function assertCopiedExactly(): void
    {
        $expected = DB::table('subscriptions')->where('record_type', 2)->count();
        $actual = DB::table('subscription_installments')->count();
        if ($expected !== $actual) {
            throw new LogicException('Le nombre de copies des échéances ne correspond pas à la source.');
        }
        $different = DB::selectOne(<<<'SQL'
SELECT COUNT(*) AS n
FROM `subscriptions` s
LEFT JOIN `subscription_installments` d ON d.id = s.id
WHERE s.record_type = 2 AND (
    d.id IS NULL OR NOT (d.uuid <=> s.uuid) OR NOT (d.user_id <=> s.user_id)
    OR NOT (d.subscription_id <=> s.parent_subscription_id)
    OR NOT (d.installment_number <=> s.installment_number)
    OR NOT (d.operation_key <=> s.operation_key)
    OR NOT (d.installment_amount <=> s.installment_amount)
    OR NOT (d.installment_status <=> s.installment_status)
    OR NOT (d.period_starts_at <=> s.period_starts_at)
    OR NOT (d.period_ends_at <=> s.period_ends_at)
    OR NOT (d.due_at <=> s.due_at)
    OR NOT (d.created_at <=> s.created_at)
    OR NOT (d.updated_at <=> s.updated_at)
)
SQL);
        if ((int) $different->n !== 0) {
            throw new LogicException('Les lignes copiées diffèrent des lignes historiques. Stop.');
        }
    }

    private function assertInvoiceReferences(): void
    {
        $bad = DB::table('saas_invoices as i')
            ->leftJoin('subscription_installments as d', 'd.id', '=', 'i.installment_id')
            ->where(function ($query): void {
                $query->whereNull('d.id')->orWhereColumn('d.subscription_id', '<>', 'i.subscription_id')
                    ->orWhereColumn('d.user_id', '<>', 'i.user_id');
            })->exists();
        if ($bad) {
            throw new LogicException('Une facture a perdu son rattachement à une échéance. Stop.');
        }
    }

    public function down(): void
    {
        throw new LogicException('Migration conservatrice à sens unique. Restaurer le backup complet pour revenir en arrière.');
    }
};
