<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

/**
 * Gemeinsames Setup für Slides-Tests (sqlite :memory: in einer Verifikations-App mit vendor/martin3r/platform-core).
 * Lädt das Modul aus SLIDES_MODULE_PATH, spielt Core- und Modul-Migrationen ein, registriert Provider,
 * Livewire-Komponenten und Routen, ersetzt das Plattform-Layout durch ein Minimal-Layout.
 */
trait SlidesTestHarness
{
    protected string $module;

    protected function bootSlides(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        $this->module = getenv('SLIDES_MODULE_PATH') ?: dirname(__DIR__, 2);
        $module = $this->module;
        spl_autoload_register(function ($c) use ($module) {
            $p = 'Platform\\Slides\\';
            if (str_starts_with($c, $p)) {
                $f = $module . '/src/' . str_replace('\\', '/', substr($c, strlen($p))) . '.php';
                if (is_file($f)) {
                    require $f;
                }
            }
        });

        $files = array_merge(
            glob(base_path('vendor/martin3r/platform-core/database/migrations/*.php')),
            glob($module . '/database/migrations/*.php'),
        );
        usort($files, fn ($a, $b) => strcmp(basename($a), basename($b)));
        foreach ($files as $f) {
            try {
                (require $f)->up();
            } catch (\Throwable $e) {
                // Fremd-Migrationen, die auf sqlite nicht laufen, ignorieren
            }
        }

        $provider = $this->app->register(\Platform\Slides\SlidesServiceProvider::class);
        $m = new \ReflectionMethod($provider, 'registerLivewireComponents');
        $m->setAccessible(true);
        $m->invoke($provider);

        if (! \Illuminate\Support\Facades\Route::has('slides.dashboard')) {
            \Illuminate\Support\Facades\Route::middleware('web')->prefix('slides')->group($module . '/routes/web.php');
            \Illuminate\Support\Facades\Route::middleware('web')->prefix('slides')->group($module . '/routes/public.php');
        }
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();
        $this->app['view']->addNamespace('slides', $module . '/resources/views');

        $layoutDir = sys_get_temp_dir() . '/slides-ui-layout';
        @mkdir($layoutDir . '/layouts', 0777, true);
        file_put_contents($layoutDir . '/layouts/app.blade.php', '<html><body>{{ $slot }}</body></html>');
        $this->app['view']->prependNamespace('platform', $layoutDir);
    }

    /** @return array{user:\Platform\Core\Models\User, team:\Platform\Core\Models\Team, teamId:int, other:\Platform\Core\Models\User} */
    protected function seedUsers(): array
    {
        $U = \Platform\Core\Models\User::class;
        $user = $U::create(['name' => 'Martin', 'email' => 'm@x.test', 'password' => 'x']);
        $other = $U::create(['name' => 'Fremd', 'email' => 'o@x.test', 'password' => 'x']);
        $teamId = DB::table('teams')->insertGetId(['name' => 'T1', 'user_id' => $user->id, 'personal_team' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('team_user')->insert(['team_id' => $teamId, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        $user->forceFill(['current_team_id' => $teamId])->save();
        $user = $user->fresh();
        $this->actingAs($user);

        return ['user' => $user, 'team' => \Platform\Core\Models\Team::find($teamId), 'teamId' => $teamId, 'other' => $other];
    }
}
