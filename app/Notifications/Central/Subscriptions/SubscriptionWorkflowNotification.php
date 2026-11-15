<?php

namespace App\Notifications\Central\Subscriptions;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Models\Central\Users\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

class SubscriptionWorkflowNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly string $event,
        public readonly string $message,
        public readonly string $url,
        public readonly bool $administrative = false,
    ) {
        $this->onConnection('database')->onQueue('saas-documents')->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $current = $notifiable instanceof User ? $notifiable->fresh() : null;

        return $current instanceof User && $current->status === StatusEnum::ACTIVE && $current->hasVerifiedEmail()
            && filter_var($current->email, FILTER_VALIDATE_EMAIL) !== false
            && (! $this->administrative || Gate::forUser($current)->allows('viewAny', SubscriptionRequest::class));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Aydra — Suivi d’abonnement')
            ->line($this->message)->action('Consulter dans Aydra', $this->url)
            ->line('Cet e-mail ne constitue ni une facture ni une preuve de paiement. Vos documents restent disponibles dans votre espace.');
    }
}
