<?php

namespace App\Providers;

use App\Actions\Central\Auth\Fortify\CreateNewUser;
use App\Actions\Central\Auth\Fortify\ResetUserPassword;
use App\Enums\Central\Users\StatusEnum;
use App\Http\Controllers\Central\Auth\VerificationNotificationController;
use App\Http\Responses\Central\Auth\LoginResponse as CentralLoginResponse;
use App\Http\Responses\Central\Auth\RegistrationResponse;
use App\Http\Responses\Central\Auth\VerifiedEmailResponse;
use App\Models\Central\Country;
use App\Models\User;
use App\Services\Central\Auth\RegistrationJourney;
use App\Services\Central\Auth\RegistrationVerificationNotification;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkeys;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RegisterResponse::class, RegistrationResponse::class);
        $this->app->bind(SendEmailVerificationNotification::class, RegistrationVerificationNotification::class);
        $this->app->bind(EmailVerificationNotificationController::class, VerificationNotificationController::class);
        $this->app->singleton(LoginResponse::class, CentralLoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponse::class, CentralLoginResponse::class);
        $this->app->singleton(VerifyEmailResponse::class, VerifiedEmailResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Auth::provider('central_contacts', fn ($app, array $config) =>
            new \App\Services\Central\Auth\ContactUserProvider($app['hash'], $config['model']));
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    private function configureAuthentication(): void
    {
        Passkeys::authorizeLoginUsing(fn (Request $request, PasskeyUser $user): bool => ! tenancy()->initialized && $user instanceof \App\Models\Central\User
            && $user->fresh()?->status === StatusEnum::ACTIVE
        );
        Fortify::authenticateUsing(function (Request $request): ?User {
            return (new Timebox)->call(function () use ($request): ?User {
                $email = Str::lower(trim($request->string(Fortify::username())->toString()));
                $password = $request->string('password')->toString();
                $user = \Illuminate\Support\Facades\Auth::createUserProvider('central_users')->retrieveByCredentials(['email' => $email]);

                if ($user === null || ! app(RegistrationJourney::class)->mayAuthenticate($user) || ! Hash::check($password, $user->password)) {
                    return null;
                }

                if (config('hashing.rehash_on_login', true) && Hash::needsRehash($user->password)) {
                    $user->forceFill(['password' => Hash::make($password)])->save();
                }

                return $user;
            }, 200000);
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('pages::auth.login'));
        Fortify::verifyEmailView(fn () => view('pages::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('pages::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('pages::auth.confirm-password'));
        Fortify::registerView(fn () => view('pages::auth.register', [
            'countries' => Country::where('is_active', true)->orderBy('name_fr')->get(),
        ]));
        Fortify::resetPasswordView(fn () => view('pages::auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('pages::auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(trim($request->string(Fortify::username())->toString()));
            $throttleKey = Str::transliterate($email.'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
