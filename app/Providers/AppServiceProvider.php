<?php

namespace App\Providers;

use App\Contracts\AudioInspector;
use App\Contracts\MeetingAnalysisProvider;
use App\Contracts\TranscriptionProvider;
use App\Models\User;
use App\Services\FakeMeetingAnalysisProvider;
use App\Services\FakeTranscriptionProvider;
use App\Services\FfprobeAudioInspector;
use App\Services\OpenAI\OpenAIMeetingAnalysisProvider;
use App\Services\OpenAI\OpenAITranscriptionProvider;
use App\Services\Postgis\ManagedPostgisConfiguration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $managedPostgis = new ManagedPostgisConfiguration;
        $this->app->instance(ManagedPostgisConfiguration::class, $managedPostgis);
        if (! $this->app->runningUnitTests()) {
            $managedPostgis->apply();
        }

        $this->app->bind(AudioInspector::class, FfprobeAudioInspector::class);
        $this->app->bind(TranscriptionProvider::class, fn () => config('meetings.ai_driver') === 'openai' ? app(OpenAITranscriptionProvider::class) : app(FakeTranscriptionProvider::class));
        $this->app->bind(MeetingAnalysisProvider::class, fn () => config('meetings.ai_driver') === 'openai' ? app(OpenAIMeetingAnalysisProvider::class) : app(FakeMeetingAnalysisProvider::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('approve-spatial-publication', fn (User $user): bool => $user->canApproveSpatialPublication());
        Gate::define('manage-dashboards', fn (User $user): bool => $user->canManageDashboards());
        Gate::define('approve-dashboards', fn (User $user): bool => $user->canApproveDashboards());
        Gate::define('manage-open-data-sources', fn (User $user): bool => $user->canManageOpenDataSources());
        RateLimiter::for('dashboard-queries', fn (Request $request) => Limit::perMinute(120)->by((string) ($request->user()?->id ?: $request->ip())));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perHour(20)->by((string) ($request->user()?->id ?: $request->ip())));
        RateLimiter::for('open-data', fn (Request $request) => Limit::perMinute(60)->by((string) ($request->user()?->id ?: $request->ip())));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
