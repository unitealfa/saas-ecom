<?php

namespace App\Models\Central\Subscriptions;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Subscriptions\PaymentChoiceEnum;
use App\Enums\Central\Subscriptions\RequestStatusEnum;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Users\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $reference_version
 * @property int|null $current_subscription_id
 * @property int|null $current_invoice_id
 * @property int|null $current_payment_id
 * @property RequestStatusEnum $status
 * @property PaymentChoiceEnum $payment_choice
 */
#[Fillable([])]
#[Hidden(['id', 'user_id', 'current_subscription_id', 'current_invoice_id', 'current_payment_id', 'decided_by_id'])]
class SubscriptionRequest extends Model
{
    use CentralConnection, HasPublicUuid;

    public const string STATE_PATTERN = '/^[a-f0-9]{64}$/D';

    protected $attributes = ['status' => RequestStatusEnum::INVOICE_PENDING->value, 'reference_version' => 1, 'currency' => SaasInvoice::CURRENCY];

    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            if ($request->isDirty(['user_id', 'operation_key', 'initial_payload_hash']) || (RequestStatusEnum::from((int) $request->getRawOriginal('status'))->isFinal() && $request->isDirty())) {
                throw new LogicException('A final subscription request and its owner are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Subscription request history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['status' => RequestStatusEnum::class, 'payment_choice' => PaymentChoiceEnum::class,
            'user_id' => 'integer', 'current_subscription_id' => 'integer', 'current_invoice_id' => 'integer', 'current_payment_id' => 'integer',
            'reference_version' => 'integer', 'submitted_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'current_subscription_id');
    }

    /** @return BelongsTo<SaasInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SaasInvoice::class, 'current_invoice_id');
    }

    /** @return BelongsTo<SaasPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(SaasPayment::class, 'current_payment_id');
    }

    /** @return HasMany<Subscription, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(Subscription::class, 'subscription_request_id')->orderBy('id');
    }

    public function managementState(): string
    {
        return hash('sha256', json_encode($this->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function getMorphClass(): string
    {
        return 'subscription_request';
    }
}
