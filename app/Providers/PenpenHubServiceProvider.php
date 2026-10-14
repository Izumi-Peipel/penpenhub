<?php

namespace App\Providers;

use App\Actions\ResetUserPassword;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Outlet;
use App\Models\User;
use App\Policies\BrandPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\OutletPolicy;
use App\Support\AuditLogger;
use App\Support\CurrentContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Fortify;

class PenpenHubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentContext::class);
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(10)->mixedCase()->numbers());

        // Super Admin melewati semua Policy; Policy lain menangani scope G/B/O.
        Gate::before(fn ($user) => $user instanceof User && $user->isSuperAdmin() ? true : null);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Outlet::class, OutletPolicy::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn ($request) => view('auth.reset-password', ['request' => $request]));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $this->registerAuditListeners();
    }

    private function registerAuditListeners(): void
    {
        // Saat event Login, Auth::user() belum terisi, jadi aktor diberikan eksplisit.
        Event::listen(Login::class, fn (Login $e) => AuditLogger::log(
            'auth.login',
            $e->user instanceof Model ? $e->user : null,
            actor: $e->user,
        ));

        Event::listen(Failed::class, fn (Failed $e) => AuditLogger::log(
            'auth.login_failed',
            null,
            ['email' => $e->credentials['email'] ?? null],
        ));

        Event::listen(PasswordReset::class, fn (PasswordReset $e) => AuditLogger::log(
            'auth.password_reset',
            $e->user instanceof Model ? $e->user : null,
            actor: $e->user,
        ));

        Event::listen(TwoFactorAuthenticationConfirmed::class, fn ($e) => AuditLogger::log('auth.2fa_enabled', $e->user, actor: $e->user));
        Event::listen(TwoFactorAuthenticationDisabled::class, fn ($e) => AuditLogger::log('auth.2fa_disabled', $e->user, actor: $e->user));
    }
}
