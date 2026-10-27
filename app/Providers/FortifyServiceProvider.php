<?php

namespace App\Providers;

use App\Actions\Central\Auth\Fortify\CreateNewUser;
use App\Actions\Central\Auth\Fortify\ResetUserPassword;
use App\Enums\Central\Users\StatusEnum;
use App\Http\Controllers\Central\Auth\VerificationNotificationController;
use App\Http\Responses\Central\Auth\LoginResponse as CentralLoginResponse;
use App\Http\Responses\Central\Auth\RegistrationResponse;
use App\Http\Responses\Central\Auth\VerifiedEmailResponse;
use App\Models\Central\Users\User;
use App\Services\Central\Auth\RegistrationJourney;
use App\Services\Central\Auth\RegistrationVerificationNotification;
use App\Services\Central\Countries\CountryCatalog;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkeys;

class FortifyServiceProvider extends ServiceProvider
{
    /** Durée minimale (µs) de la vérification des identifiants : masque le temps de réponse. */
    private const int LOGIN_TIMEBOX = 200_000;

    public function register(): void
    {
        $this->app->singleton(RegisterResponse::class, RegistrationResponse::class);
        $this->app->singleton(LoginResponse::class, CentralLoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponse::class, CentralLoginResponse::class);
        $this->app->singleton(VerifyEmailResponse::class, VerifiedEmailResponse::class);

        $this->app->bind(SendEmailVerificationNotification::class, RegistrationVerificationNotification::class);
        $this->app->bind(EmailVerificationNotificationController::class, VerificationNotificationController::class);
    }

    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    private function configureAuthentication(): void
    {
        Passkeys::authorizeLoginUsing(
            fn (Request $request, PasskeyUser $user): bool => ! tenancy()->initialized
                && $user instanceof User
                && $user->fresh()?->status === StatusEnum::ACTIVE
        );

        Fortify::authenticateUsing(fn (Request $request): ?User => (new Timebox)->call(
            fn (): ?User => $this->attempt($request),
            self::LOGIN_TIMEBOX,
        ));
    }

    /**
     * Même réponse (null) pour e-mail inconnu, mauvais mot de passe ou compte non autorisé.
     */
    private function attempt(Request $request): ?User
    {
        $password = $request->string('password')->toString();
        $user = Auth::createUserProvider('central_users')
            ?->retrieveByCredentials(['email' => $this->email($request)]);

        if (! $user instanceof User
            || ! app(RegistrationJourney::class)->mayAuthenticate($user)
            || ! Hash::check($password, $user->password)) {
            return null;
        }

        if (config('hashing.rehash_on_login', true) && Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        }

        return $user;
    }

    private function email(Request $request): string
    {
        return Str::lower(trim($request->string(Fortify::username())->toString()));
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
        Fortify::registerView(fn () => view('pages::auth.register', [
            'countries' => app(CountryCatalog::class)->active(),
        ]));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)
            ->by($request->session()->get('login.id') ?: $request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::transliterate($this->email($request).'|'.$request->ip())));

        RateLimiter::for('passkeys', fn (Request $request) => Limit::perMinute(10)
            ->by(($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip()));
    }
}
