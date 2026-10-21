<?php

use App\Concerns\ProfileValidationRules;
/* @chisel-email-verification */
use Illuminate\Contracts\Auth\MustVerifyEmail;
/* @end-chisel-email-verification */
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $country_uuid = '';
    public string $phone_country_uuid = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->phone = Auth::user()->phone ?? '';
        $this->country_uuid = Auth::user()->country->uuid;
        $this->phone_country_uuid = Auth::user()->phoneCountry?->uuid ?? $this->country_uuid;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();
        $this->email = strtolower(trim($this->email));

        $validated = $this->validate([
            ...$this->profileRules($user->id),
            'phone' => ['nullable', 'string', 'max:255'],
            'country_uuid' => ['required', 'uuid'],
            'phone_country_uuid' => ['required', 'uuid'],
        ], ['email.unique' => 'Cette adresse e-mail est déjà utilisée.']);

        $result = app(\App\Services\Central\Users\UserManagementService::class)->update(
            $user, $user,
            new \App\DTOs\Central\Users\UserProfileData(
                trim($validated['name']) === $user->name ? $user->last_name : trim($validated['name']),
                trim($validated['name']) === $user->name ? $user->first_name : null,
                strtolower(trim($validated['email'])),
                $validated['phone'] ?: null, $validated['country_uuid'], [], $validated['phone_country_uuid'],
            ),
            ability: 'updateOwnProfile',
        );
        $this->phone = $result->user->phone ?? '';
        $this->phone_country_uuid = $result->user->phoneCountry?->uuid ?? $result->user->country->uuid;
        if ($result->emailSent === false) {
            Session::flash('warning', 'Le profil est enregistré, mais le message de vérification n’a pas pu être envoyé. Réessayez depuis la page de vérification.');
        }
        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    #[Computed]
    public function countries()
    {
        return app(\App\Services\Central\Countries\CountryCatalog::class)->forProfile(Auth::user()->country_id);
    }

    #[Computed]
    public function phoneCountries()
    {
        return app(\App\Services\Central\Countries\CountryCatalog::class)->forPhone(Auth::user()->phone_country_id);
    }

    /* @chisel-email-verification */
    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $sent = \Illuminate\Support\Facades\RateLimiter::attempt(
            'profile-verification:'.$user->id, 5,
            fn () => app(\App\Services\Central\Auth\AccountMailDelivery::class)->verification($user), 60,
        );
        if ($sent !== true) {
            $this->addError('email', 'Le message n’a pas pu être envoyé ou la limite de demandes est atteinte. Réessayez dans une minute.');

            return;
        }
        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
    /* @end-chisel-email-verification */
}; ?>

<section class="w-full">
    @include('central.partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        @include('central.partials.profile-photo-form')
        @if (session('warning'))<flux:callout variant="warning">{{ session('warning') }}</flux:callout>@endif
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                {{-- @chisel-email-verification --}}
                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
                {{-- @end-chisel-email-verification --}}
            </div>

            <flux:select wire:model="country_uuid" label="Pays du profil" required>
                @foreach ($this->countries as $country)
                    <option value="{{ $country->uuid }}">{{ $country->name_fr }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model="phone_country_uuid" label="Pays et indicatif du téléphone" required>
                @foreach ($this->phoneCountries as $country)
                    <option value="{{ $country->uuid }}">{{ $country->name_fr }} ({{ $country->calling_code }}){{ $country->is_active ? '' : ' — numéro existant uniquement' }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model="phone" label="Téléphone" type="tel" autocomplete="tel" placeholder="Numéro local ou international" />
            <flux:text>Le pays du profil et celui du téléphone peuvent être différents. Changer le pays du profil ne transforme pas votre numéro.</flux:text>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        {{-- @chisel-email-verification --}}
        @if ($this->showDeleteUser)
        {{-- @end-chisel-email-verification --}}
            <livewire:pages::settings.delete-user-form />
        {{-- @chisel-email-verification --}}
        @endif
        {{-- @end-chisel-email-verification --}}
    </x-pages::settings.layout>
</section>
