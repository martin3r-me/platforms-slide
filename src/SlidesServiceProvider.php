<?php

namespace Platform\Slides;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Policies\DeckPolicy;
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
            SlidesDeck::MORPH_ALIAS => SlidesDeck::class,
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
            PlatformCore::modulesTableExists()
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
            // Bilder und signierte Vorschauen ohne Login (iframes, PDF-Renderer und Agenten haben keine Session)
            ModuleRouter::group('slides', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/public.php');
            }, false);
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'slides');
        $this->registerLivewireComponents();
        $this->registerPolicies();

        // Tools registrieren (loose gekoppelt - für AI/Chat)
        $this->registerTools();
    }

    /**
     * MCP-Tools (Präfix = Modul-Key „slides“). Regeln für Agenten: slides.guide.GET.
     */
    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);
            foreach ([
                \Platform\Slides\Tools\GetGuideTool::class,
                \Platform\Slides\Tools\ListDecksTool::class,
                \Platform\Slides\Tools\CreateDeckTool::class,
                \Platform\Slides\Tools\UpdateDeckTool::class,
                \Platform\Slides\Tools\DeleteDeckTool::class,
                \Platform\Slides\Tools\DuplicateDeckTool::class,
                \Platform\Slides\Tools\ListSlidesTool::class,
                \Platform\Slides\Tools\UpsertSlidesTool::class,
                \Platform\Slides\Tools\DeleteSlidesTool::class,
                \Platform\Slides\Tools\OrderSlidesTool::class,
                \Platform\Slides\Tools\ListLinksTool::class,
                \Platform\Slides\Tools\CreateLinkTool::class,
                \Platform\Slides\Tools\DeleteLinkTool::class,
                \Platform\Slides\Tools\ListVersionsTool::class,
                \Platform\Slides\Tools\CreateVersionTool::class,
                \Platform\Slides\Tools\RestoreVersionTool::class,
                \Platform\Slides\Tools\PreviewTool::class,
                \Platform\Slides\Tools\ListAssetsTool::class,
                \Platform\Slides\Tools\CreateAssetTool::class,
                \Platform\Slides\Tools\DeleteAssetTool::class,
            ] as $tool) {
                $registry->register(new $tool());
            }
        } catch (\Throwable $e) {
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

            // Je Segment kebab-casen, sonst wird aus Deck/Show "deck/-show"
            $aliasPath = collect(preg_split('#[\\\\/]#', str_replace('.php', '', $relativePath)))
                ->map(fn ($segment) => Str::kebab($segment))
                ->implode('.');
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }

    protected function registerPolicies(): void
    {
        Gate::policy(SlidesDeck::class, DeckPolicy::class);
    }
}
