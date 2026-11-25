<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\Activity;
use App\Models\ActivityParticipation;
use App\Models\Category;
use App\Models\Content;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Quiz;
use App\Models\Report;
use App\Models\User;
use App\Models\Zone;
use App\Policies\ActivityPolicy;
use App\Policies\ContentPolicy;
use App\Policies\EventPolicy;
use App\Policies\QuizPolicy;
use App\Policies\ReportPolicy;
use App\Policies\UserPolicy;
use App\Support\ApiDocumentation;
use App\Support\ReferenceDataCache;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected array $policies = [
        Content::class => ContentPolicy::class,
        Quiz::class => QuizPolicy::class,
        Activity::class => ActivityPolicy::class,
        ActivityParticipation::class => ActivityPolicy::class,
        Report::class => ReportPolicy::class,
        Event::class => EventPolicy::class,
        EventRegistration::class => EventPolicy::class,
        User::class => UserPolicy::class,
    ];

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
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Gate::define('viewApiDocs', function (): bool {
            $user = Auth::guard('sanctum')->user();

            return $user instanceof User && $user->role?->name === RoleName::ADMINISTRADOR;
        });

        foreach ([Category::class => 'categories', Zone::class => 'zones'] as $model => $type) {
            $model::created(fn () => ReferenceDataCache::invalidate($type));
            $model::updated(fn () => ReferenceDataCache::invalidate($type));
            $model::deleted(fn () => ReferenceDataCache::invalidate($type));
        }

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by('api:'.($request->user()?->getAuthIdentifier() ?: $request->ip())));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)
            ->by('uploads:'.($request->user()?->getAuthIdentifier() ?: $request->ip())));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by('login:'.$request->ip().':'.strtolower((string) $request->input('email'))));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)
            ->by('register:'.$request->ip().':'.strtolower((string) $request->input('email'))));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)
            ->by('password-reset:'.$request->ip().':'.strtolower((string) $request->input('email'))));

        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(6)
            ->by('verification:'.($request->user()?->getAuthIdentifier() ?: $request->ip())));

        Scramble::extendOpenApi(function (OpenApi $openApi): void {
            ApiDocumentation::extend($openApi);
        });
    }
}
