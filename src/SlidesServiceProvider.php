<?php

namespace Platform\Slides;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use Platform\Slides\Models\SlidesFolder;
use Platform\Slides\Models\SlidesPresentation;
use Platform\Slides\Policies\FolderPolicy;
use Platform\Slides\Policies\PresentationPolicy;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SlidesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/slides.php', 'slides');
    }

    public function boot(): void
    {
        Relation::morphMap([
            'slides_presentation' => \Platform\Slides\Models\SlidesPresentation::class,
        ]);

        // EntityLinkProvider registrieren (loose Kopplung mit Organization-Modul)
        try {
            resolve(\Platform\Organization\Services\EntityLinkRegistry::class)
                ->register(new \Platform\Slides\Organization\SlidesEntityLinkProvider());
        } catch (\Throwable $e) {
            // Organization-Modul nicht geladen
        }

        $this->publishes([
            __DIR__.'/../config/slides.php' => config_path('slides.php'),
        ], 'config');

        if (
            config()->has('slides.routing') &&
            config()->has('slides.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'slides',
                'title'      => 'Präsentationen',
                'group'      => 'content',
                'routing'    => config('slides.routing'),
                'guard'      => config('slides.guard'),
                'navigation' => config('slides.navigation'),
                'sidebar'    => config('slides.sidebar'),
            ]);
        }

        if (PlatformCore::getModule('slides')) {
            ModuleRouter::group('slides', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });

            // Public routes (without auth)
            ModuleRouter::group('slides', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/public.php');
            }, requireAuth: false);
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'slides');
        $this->registerLivewireComponents();
        $this->registerPolicies();

        // Tools registrieren (loose gekoppelt - für AI/Chat)
        $this->registerTools();
    }

    /**
     * Registriert Slides-Tools für die AI/Chat-Funktionalität.
     * Folgt dem Vorbild des Planner-Moduls.
     */
    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            // Overview-Tool
            $registry->register(new \Platform\Slides\Tools\SlidesOverviewTool());

            // Deck-Tools (Präsentationen)
            $registry->register(new \Platform\Slides\Tools\ListDecksTool());
            $registry->register(new \Platform\Slides\Tools\GetDeckTool());
            $registry->register(new \Platform\Slides\Tools\CreateDeckTool());
            $registry->register(new \Platform\Slides\Tools\UpdateDeckTool());
            $registry->register(new \Platform\Slides\Tools\DeleteDeckTool());

            // Slide-Tools
            $registry->register(new \Platform\Slides\Tools\ListSlidesTool());
            $registry->register(new \Platform\Slides\Tools\CreateSlideTool());
            $registry->register(new \Platform\Slides\Tools\UpdateSlideTool());
            $registry->register(new \Platform\Slides\Tools\DeleteSlideTool());
            $registry->register(new \Platform\Slides\Tools\SortSlidesTool());

            // Content-Tool (Platzhalter befüllen)
            $registry->register(new \Platform\Slides\Tools\FillSlideContentTool());
        } catch (\Throwable $e) {
            // Silent fail - ToolRegistry möglicherweise nicht verfügbar
            \Log::warning('Slides: Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Slides\\Livewire';
        $prefix = 'slides';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }

    protected function registerPolicies(): void
    {
        $policies = [
            SlidesFolder::class => FolderPolicy::class,
            SlidesPresentation::class => PresentationPolicy::class,
        ];

        foreach ($policies as $model => $policy) {
            if (class_exists($model) && class_exists($policy)) {
                Gate::policy($model, $policy);
            }
        }
    }
}
