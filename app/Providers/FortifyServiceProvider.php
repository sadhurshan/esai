<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
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

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request): View {
            return $this->renderAppShell([
                'page' => 'auth.login',
                'props' => [
                    'canResetPassword' => Features::enabled(Features::resetPasswords()),
                    'canRegister' => Features::enabled(Features::registration()),
                ],
                'flash' => [
                    'status' => $request->session()->get('status'),
                ],
            ]);
        });

        Fortify::resetPasswordView(function (Request $request): View {
            return $this->renderAppShell([
                'page' => 'auth.reset-password',
                'props' => [
                    'email' => $request->email,
                    'token' => $request->route('token'),
                ],
            ]);
        });

        Fortify::requestPasswordResetLinkView(function (Request $request): View {
            return $this->renderAppShell([
                'page' => 'auth.forgot-password',
                'flash' => [
                    'status' => $request->session()->get('status'),
                ],
            ]);
        });

        Fortify::verifyEmailView(function (Request $request): View {
            return $this->renderAppShell([
                'page' => 'auth.verify-email',
                'flash' => [
                    'status' => $request->session()->get('status'),
                ],
            ]);
        });

        Fortify::registerView(fn (): View => $this->renderAppShell(['page' => 'auth.register']));

        Fortify::twoFactorChallengeView(fn (): View => $this->renderAppShell(['page' => 'auth.two-factor-challenge']));

        Fortify::confirmPasswordView(fn (): View => $this->renderAppShell(['page' => 'auth.confirm-password']));
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
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * Return the SPA shell view with optional initial data payload.
     */
    private function renderAppShell(array $initialData = []): View
    {
        return view('app', [
            'initialData' => $initialData,
        ]);
    }
}
