<?php

namespace App\Providers;

use App\Enums\ApplicationStatus;
use App\Enums\ComplaintStatus;
use App\Enums\PaymentStatus;
use App\Models\Complaint;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\SeatApplication;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Catch N+1 queries and typos in attribute names while developing
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8));

        // 5 wrong passwords per minute per email + IP, then the form waits
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Ask AI costs an API call per message: 10 a minute and 150 a day per user
        RateLimiter::for('assistant', fn (Request $request) => [
            Limit::perMinute(10)->by('minute:'.$request->user()->id),
            Limit::perDay(150)->by('day:'.$request->user()->id),
        ]);

        // Counts for the sidebar badges and the urgent notice banner
        View::composer('components.layouts.app', function ($view) {
            $user = auth()->user();
            if (! $user) {
                return;
            }

            $badges = [];
            if ($user->isManager()) {
                $badges = [
                    'applications' => $user->isAdmin() ? SeatApplication::query()->where('status', ApplicationStatus::Pending)->count() : 0,
                    'complaints' => Complaint::query()->where('status', ComplaintStatus::Open)->count(),
                    'payments' => Payment::query()->where('status', PaymentStatus::Pending)->count(),
                ];
            }

            $view->with([
                'isResident' => $user->isStudent() && $user->activeAllocation()->exists(),
                'navBadges' => $badges,
                'unreadCount' => $user->unreadNotifications()->count(),
                'urgentNotice' => Notice::query()->live()->where('priority', 'urgent')->latest('published_at')->first(),
            ]);
        });
    }
}
