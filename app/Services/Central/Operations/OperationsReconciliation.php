<?php

namespace App\Services\Central\Operations;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Subscriptions\DefaultSubscription;
use App\Services\Central\Tenants\TenantStatusService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationsReconciliation
{
    private const int BATCH_SIZE = 50;

    public function __construct(
        private readonly AuthorizationMutation $mutations,
        private readonly DefaultSubscription $subscriptions,
        private readonly TenantStatusService $tenants,
        private readonly CentralUserAccess $access,
    ) {}

    /** @return array{type: string, processed: int, failed: int, failed_owners: list<string>, next_cursor: ?string} */
    public function subscriptions(User $actor, ?string $cursor): array
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('reconcileSubscriptions', User::class);
        $owners = User::query()->whereDoesntHave('roles', fn (Builder $query) => $query->where('roles.guard_name', 'central'))
            ->whereHas('subscriptions', fn (Builder $query) => $query->dueForResolution())
            ->when($cursor !== null, fn (Builder $query) => $query->where('uuid', '>', $cursor))
            ->orderBy('uuid')->limit(self::BATCH_SIZE + 1)->get(['id', 'uuid']);
        $batch = $owners->take(self::BATCH_SIZE);
        $processed = 0;
        $failed = 0;
        $failedOwners = [];

        foreach ($batch as $owner) {
            try {
                $changed = $this->mutations->run($actor, 'reconcileSubscriptions',
                    fn () => User::whereKey($owner->id)->lockForUpdate()->firstOrFail(),
                    function (User $locked) use ($actor): bool {
                        if ($this->access->isStaff($locked) || ! Subscription::where('user_id', $locked->id)->dueForResolution()->exists()) {
                            return false;
                        }
                        $previous = Subscription::where('user_id', $locked->id)
                            ->whereIn('status', [StatusEnum::ACTIVE, StatusEnum::TRIAL])->orderByDesc('id')
                            ->lockForUpdate()->first();
                        $current = $this->subscriptions->ensure($locked, $actor);
                        if ($previous?->id === $current->id) {
                            $this->tenants->applySubscriptionQuota($locked, $current, $actor);
                        }
                        activity('central')->causedBy($actor)->performedOn($locked)->event('operations_subscription_reconciled')
                            ->withProperties(['subscription_uuid' => $current->uuid])->log('Réconciliation des abonnements et quotas.');
                        return true;
                    });
                if ($changed) {
                    $processed++;
                }
            } catch (AuthorizationException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $failed++;
                $failedOwners[] = $owner->uuid;
                Log::warning('Échec de réconciliation d’un abonnement.', ['exception_type' => $exception::class]);
                activity('central')->causedBy($actor)->performedOn($owner)->event('operations_subscription_reconciliation_failed')
                    ->log('Réconciliation interrompue pour ce compte.');
            }
        }

        return ['type' => 'subscriptions', 'processed' => $processed, 'failed' => $failed, 'failed_owners' => $failedOwners,
            'next_cursor' => $owners->count() > self::BATCH_SIZE ? $batch->last()->uuid : null];
    }

    /** @return array{type: string, processed: int, anomalies: array<int, array{uuid: string, reasons: list<string>}>, next_cursor: ?string} */
    public function payments(User $actor, ?string $cursor): array
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('reconcilePayments', User::class);
        $db = DB::connection(config('tenancy.database.central_connection'));
        $records = $db->table('saas_transfers as transfers')->leftJoin('saas_invoices as invoices', 'invoices.id', '=', 'transfers.document_id')
            ->where('transfers.record_type', SaasPayment::RECORD_TYPE)
            ->when($cursor !== null, fn ($query) => $query->where('transfers.uuid', '>', $cursor))
            ->orderBy('transfers.uuid')->limit(self::BATCH_SIZE + 1)
            ->select(['transfers.uuid', 'transfers.transfer_status', 'transfers.validated_at', 'transfers.reversal_of_id',
                'transfers.currency', 'transfers.user_id', 'invoices.status as invoice_status',
                'invoices.currency as invoice_currency', 'invoices.user_id as invoice_user_id'])
            ->selectRaw('(SELECT COUNT(*) FROM saas_transfers t WHERE t.document_id = transfers.document_id AND t.record_type = ? AND t.transfer_status = ? AND t.reversal_of_id IS NULL) AS verified_count', [SaasPayment::RECORD_TYPE, TransferStatusEnum::VERIFIED->value])
            ->get();
        $batch = $records->take(self::BATCH_SIZE);
        $anomalies = [];
        foreach ($batch as $record) {
            $reasons = [];
            if ((int) $record->transfer_status === TransferStatusEnum::UNCERTAIN->value) {
                $reasons[] = 'Paiement incertain à examiner';
            }
            if ((int) $record->transfer_status === TransferStatusEnum::VERIFIED->value && $record->reversal_of_id === null) {
                if ((int) $record->invoice_status !== DocumentStatusEnum::ISSUED->value || $record->validated_at === null
                    || $record->currency !== $record->invoice_currency || $record->user_id !== $record->invoice_user_id) {
                    $reasons[] = 'Paiement vérifié incohérent avec la facture';
                }
                if ((int) $record->verified_count > 1) {
                    $reasons[] = 'Plusieurs paiements vérifiés sur la même facture';
                }
            }
            if ($reasons !== []) {
                $anomalies[] = ['uuid' => $record->uuid, 'reasons' => $reasons];
            }
        }
        $result = ['type' => 'payments', 'processed' => $batch->count(), 'anomalies' => $anomalies,
            'next_cursor' => $records->count() > self::BATCH_SIZE ? $batch->last()->uuid : null];
        $this->mutations->run($actor, 'reconcilePayments', fn () => User::class,
            fn () => activity('central')->causedBy($actor)->event('operations_payments_reconciled')
                ->withProperties(['processed' => $result['processed'], 'anomalies' => count($anomalies)])
                ->log('Contrôle central des paiements sans modification financière.'));

        return $result;
    }
}
