<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Models\Central\Users\User;
use App\Notifications\Central\Subscriptions\SubscriptionWorkflowNotification;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubscriptionWorkflowNotifier
{
    public const string RECEIPT_READY = 'subscription_payment_receipt_ready';

    public const string ACTIVATED = 'subscription_scheduled_activated';

    public function request(SubscriptionRequest $request, string $event): void
    {
        $message = match ($event) {
            SubscriptionRequestService::CREATED => 'Votre demande d’abonnement a été reçue.',
            SubscriptionRequestService::INVOICE_READY => 'Votre facture est disponible dans votre espace, avant paiement.',
            SubscriptionRequestPaymentState::SUBMITTED => 'Votre justificatif bancaire a été reçu. Votre paiement est en vérification.',
            SubscriptionRequestPaymentState::ACCEPTED => 'Votre paiement intégral est confirmé et votre demande est acceptée. Abonnement : '.$request->subscription->status->label().'.',
            SubscriptionRequestPaymentState::REJECTED => 'Votre demande a été refusée. Motif : '.$request->getAttribute('decision_reason'),
            default => null,
        };
        if ($message === null) {
            return;
        }
        $ownerId = $request->user_id;
        $ownerUrl = route('owner.subscription-requests.show', $request);
        $adminUrl = route('central.subscription-requests.show', $request);
        $this->afterCommit($event, function () use ($ownerId, $ownerUrl, $adminUrl, $event, $message): void {
            $this->send(User::find($ownerId), new SubscriptionWorkflowNotification($event, $message, $ownerUrl));
            if (! in_array($event, [SubscriptionRequestService::CREATED, SubscriptionRequestPaymentState::SUBMITTED], true)) {
                return;
            }
            User::withStatus(StatusEnum::ACTIVE)->whereNotNull('email_verified_at')
                ->whereHas('roles', fn ($roles) => $roles->where('guard_name', Role::GUARD))
                ->chunkById(100, function ($staff) use ($event, $adminUrl): void {
                    foreach ($staff as $actor) {
                        if (Gate::forUser($actor)->allows('viewAny', SubscriptionRequest::class)) {
                            $message = $event === SubscriptionRequestService::CREATED
                                ? 'Une nouvelle demande d’abonnement attend un traitement.'
                                : 'Un nouveau justificatif bancaire attend une vérification.';
                            $this->send($actor, new SubscriptionWorkflowNotification($event, $message, $adminUrl, administrative: true));
                        }
                    }
                });
        });
    }

    public function receipt(SaasPayment $payment): void
    {
        $this->owner($payment->getAttribute('user_id'), self::RECEIPT_READY,
            'Votre reçu est disponible après confirmation du paiement intégral.', route('owner.payments.show', $payment));
    }

    public function activated(Subscription $subscription): void
    {
        $this->owner($subscription->user_id, self::ACTIVATED, 'Votre abonnement programmé est maintenant actif.', route('owner.subscriptions.show', $subscription));
    }

    private function owner(int $id, string $event, string $message, string $url): void
    {
        $this->afterCommit($event, fn () => $this->send(User::find($id), new SubscriptionWorkflowNotification($event, $message, $url)));
    }

    private function send(?User $user, SubscriptionWorkflowNotification $notification): void
    {
        if ($user === null || ! $notification->shouldSend($user, 'mail')) {
            return;
        }
        $user->notify($notification);
    }

    private function afterCommit(string $event, Closure $send): void
    {
        DB::afterCommit(function () use ($event, $send): void {
            try {
                $send();
            } catch (Throwable $exception) {
                Log::warning('Subscription notification could not be queued.', ['event' => $event, 'exception_class' => $exception::class]);
            }
        });
    }
}
