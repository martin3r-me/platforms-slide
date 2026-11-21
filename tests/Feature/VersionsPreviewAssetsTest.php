<?php

namespace Tests\Feature;

require_once __DIR__ . '/SlidesTestHarness.php';

use Illuminate\Support\Facades\Storage;
use Platform\Core\Contracts\ToolContext;
use Platform\Slides\Models\SlidesAsset;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Models\SlidesDeckVersion;
use Platform\Slides\Services\DeckService;
use Tests\TestCase;

/** Versionen (automatisch, festgehalten, wiederherstellen), Vorschau/Layout-Prüfung, Bilder. */
class VersionsPreviewAssetsTest extends TestCase
{
    use SlidesTestHarness;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAADCAIAAAA2iEnWAAAAEElEQVR4nGP4z8AARAwoFABE0AX7pM/egAAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSlides();
        $this->withoutExceptionHandling();
        Storage::fake('local');
    }

    private function tool(string $tool, array $args, ToolContext $ctx)
    {
        $cls = '\\Platform\\Slides\\Tools\\' . $tool;

        return (new $cls())->execute($args, $ctx);
    }

    /** @return array{0:SlidesDeck,1:ToolContext,2:array} */
    private function deck(): array
    {
        $d = $this->seedUsers();
        $ctx = new ToolContext($d['user'], $d['team']);
        ['deck' => $deck] = app(DeckService::class)->createDeck($d['user'], $d['teamId'], ['title' => 'Strategie']);
        app(DeckService::class)->upsertSlides($deck, [
            ['key' => 'cover', 'html' => '<section class="title"><h1>Strategie</h1></section>'],
            ['key' => 'agenda', 'html' => '<section><h2>Agenda</h2><ul><li>A</li></ul></section>', 'notes' => 'kurz'],
        ], $d['user']);

        return [$deck->fresh(), $ctx, $d];
    }

    public function test_versions_auto_manual_restore(): void
    {
        [$deck, $ctx, $d] = $this->deck();
        $svc = app(DeckService::class);
        $this->assertSame(0, SlidesDeckVersion::count(), 'leeres neues Deck: keine Auto-Version');

        // Neue Änderungsrunde (letzte Änderung lange her) → Stand davor wird gesichert
        SlidesDeck::whereKey($deck->id)->update(['last_edited_at' => now()->subHour()]);
        $deck->refresh();
        $svc->upsertSlides($deck, [['key' => 'agenda', 'html' => '<section><h2>Agenda NEU</h2></section>']], $d['user']);
        $this->assertSame(1, SlidesDeckVersion::count());
        $v1 = SlidesDeckVersion::first();
        $this->assertSame('auto', $v1->kind);
        $this->assertStringContainsString('<li>A</li>', collect($v1->snapshot['slides'])->firstWhere('key', 'agenda')['html']);

        // Gleiche Runde (gerade geändert) → keine weitere Version
        $svc->upsertSlides($deck->fresh(), [['key' => 'agenda', 'notes' => 'neu']], $d['user']);
        $this->assertSame(1, SlidesDeckVersion::count());

        // Festhalten per Tool, Unterschied zum aktuellen Stand
        $r = $this->tool('CreateVersionTool', ['deck_id' => $deck->id, 'label' => 'Vor Umbau'], $ctx);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame('manual', $r->data['version']['kind']);
        $manualId = $r->data['version']['id'];
        $again = $this->tool('CreateVersionTool', ['deck_id' => $deck->id], $ctx);
        $this->assertSame($manualId, $again->data['version']['id'], 'unveränderter Stand wird nicht doppelt gespeichert');

        $svc->deleteSlides($deck->fresh(), ['cover'], $d['user']);
        $svc->upsertSlides($deck->fresh(), [['key' => 'neu', 'html' => '<section><h2>Neu</h2></section>']], $d['user']);
        $r = $this->tool('ListVersionsTool', ['deck_id' => $deck->id], $ctx);
        $this->assertTrue($r->success, json_encode($r));
        $diff = collect($r->data['versions'])->firstWhere('id', $manualId)['diff_to_current'];
        $this->assertSame(['neu'], $diff['added']);
        $this->assertSame(['cover'], $diff['removed']);

        // Wiederherstellen: aktueller Stand wird gesichert, Folien wie in der Version
        $rev = $deck->fresh()->revision;
        $r = $this->tool('RestoreVersionTool', ['deck_id' => $deck->id, 'version_id' => $v1->id], $ctx);
        $this->assertTrue($r->success, json_encode($r));
        $deck->refresh();
        $this->assertGreaterThan($rev, $deck->revision);
        $this->assertSame(['cover', 'agenda'], $deck->slides()->pluck('slide_key')->all());
        $this->assertStringContainsString('<li>A</li>', $deck->slides()->where('slide_key', 'agenda')->value('html'));
        $backup = SlidesDeckVersion::find($r->data['backup_version_id']);
        $this->assertSame('before_restore', $backup->kind);
        $this->assertSame(['agenda', 'neu'], collect($backup->snapshot['slides'])->pluck('key')->all());

        // ... und das ist selbst umkehrbar
        $this->tool('RestoreVersionTool', ['deck_id' => $deck->id, 'version_id' => $backup->id], $ctx);
        $this->assertSame(['agenda', 'neu'], $deck->slides()->pluck('slide_key')->all());

        // Fremde Version / fremder User
        $this->assertFalse($this->tool('RestoreVersionTool', ['deck_id' => $deck->id, 'version_id' => 99999], $ctx)->success);
        $this->assertFalse($this->tool('ListVersionsTool', ['deck_id' => $deck->id], new ToolContext($d['other'], null))->success);
    }

    public function test_assets_upload_serve_and_delete(): void
    {
        [$deck, $ctx, $d] = $this->deck();

        $r = $this->tool('CreateAssetTool', ['deck_id' => $deck->id, 'data' => 'data:image/png;base64,' . self::PNG, 'name' => 'logo.png', 'alt' => 'Logo'], $ctx);
        $this->assertTrue($r->success, json_encode($r));
        $a = $r->data['asset'];
        $this->assertSame('image/png', $a['mime_type']);
        $this->assertSame([2, 3], [$a['width'], $a['height']]);
        $this->assertStringContainsString('alt="Logo"', $a['img']);

        $svg = $this->tool('CreateAssetTool', ['svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 100"><rect width="200" height="100"/></svg>'], $ctx);
        $this->assertTrue($svg->success, json_encode($svg));
        $this->assertSame([200, 100], [$svg->data['asset']['width'], $svg->data['asset']['height']]);
        $this->assertNull($svg->data['asset']['deck_id'], 'ohne deck_id team-weit');

        $bad = $this->tool('CreateAssetTool', ['deck_id' => $deck->id, 'data' => base64_encode('kein bild')], $ctx);
        $this->assertFalse($bad->success);
        $this->assertSame('VALIDATION_ERROR', $bad->errorCode);

        // Auslieferung ohne Login, strenge Header
        $asset = SlidesAsset::find($a['id']);
        auth()->logout();
        $res = $this->get(route('slides.assets.show', $asset->fileName()));
        $res->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('sandbox', $res->headers->get('Content-Security-Policy'));
        $this->withExceptionHandling();
        $this->get(route('slides.assets.show', str_repeat('a', 40) . '.png'))->assertNotFound();
        $this->get(route('slides.assets.show', $asset->token . '.svg'))->assertNotFound();
        $this->withoutExceptionHandling();
        $this->actingAs($d['user']);

        // Bild bleibt im Sanitizer erhalten (eigene URL) und gilt dann als verwendet
        $up = app(DeckService::class)->upsertSlides($deck, [['key' => 'bild', 'html' => '<section><h2>Bild</h2>' . $asset->imgTag() . '</section>']], $d['user']);
        $this->assertSame([], $up['slides'][0]['removed']);
        $this->assertStringContainsString($asset->token, $deck->slides()->where('slide_key', 'bild')->value('html'));

        $list = $this->tool('ListAssetsTool', ['deck_id' => $deck->id], $ctx);
        $this->assertCount(2, $list->data['assets']);
        $this->assertSame($a['id'], $list->data['assets'][0]['id'], 'Deck-Bilder zuerst');

        $del = $this->tool('DeleteAssetTool', ['asset_id' => $asset->id], $ctx);
        $this->assertFalse($del->success, 'verwendetes Bild wird nicht gelöscht');
        $del = $this->tool('DeleteAssetTool', ['asset_id' => $asset->id, 'force' => true], $ctx);
        $this->assertTrue($del->success);
        Storage::disk('local')->assertMissing($asset->path);
        $this->assertFalse($this->tool('DeleteAssetTool', ['asset_id' => $svg->data['asset']['id']], new ToolContext($d['other'], null))->success);
    }

    public function test_preview_lint_without_browser(): void
    {
        [$deck, $ctx, $d] = $this->deck();
        config(['slides.browser.enabled' => false]);
        app(DeckService::class)->upsertSlides($deck, [['key' => 'voll', 'html' => '<section><p>' . str_repeat('Wort ', 120) . '[Umsatz 2026]</p><ul>' . str_repeat('<li>x</li>', 8) . '</ul></section>']], $d['user']);

        $r = $this->tool('PreviewTool', ['deck_id' => $deck->id], $ctx);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame('unavailable', $r->data['browser_check']);
        $voll = collect($r->data['slides'])->firstWhere('key', 'voll');
        $codes = array_column($voll['issues'], 'code');
        foreach (['no_heading', 'dense', 'many_bullets', 'placeholder', 'no_notes'] as $c) {
            $this->assertContains($c, $codes);
        }
        $this->assertNull($voll['image_url']);
        $cover = collect($r->data['slides'])->firstWhere('key', 'cover');
        $this->assertSame([], $cover['issues'], 'Titelfolie ohne Notizen ist ok');

        $this->assertFalse($this->tool('PreviewTool', ['deck_id' => $deck->id, 'keys' => ['gibtsnicht']], $ctx)->success);
    }

    /** Echte Messung im Headless-Chrome – nur wenn SLIDES_BROWSER_TEST=1 (lokal mit puppeteer). */
    public function test_preview_in_browser(): void
    {
        if (! getenv('SLIDES_BROWSER_TEST')) {
            $this->markTestSkipped('SLIDES_BROWSER_TEST nicht gesetzt.');
        }
        config(['slides.browser.node_modules_path' => getenv('SLIDES_NODE_MODULES_PATH') ?: null]);
        [$deck, $ctx, $d] = $this->deck();
        app(DeckService::class)->upsertSlides($deck, [
            ['key' => 'zuviel', 'html' => '<section><h2>Zu viel</h2>' . str_repeat('<p>Noch eine Zeile Text</p>', 25) . '<p style="font-size:14px">klein</p></section>', 'notes' => 'n'],
            ['key' => 'bildweg', 'html' => '<section><h2>Bild</h2><img src="https://example.invalid/x.png" alt="x"></section>', 'notes' => 'n'],
        ], $d['user']);

        $r = $this->tool('PreviewTool', ['deck_id' => $deck->id, 'width' => 640], $ctx);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame('ok', $r->data['browser_check'], (string) $r->data['browser_error']);
        $by = collect($r->data['slides'])->keyBy('key');
        $codes = fn ($k) => array_column($by[$k]['issues'], 'code');
        $this->assertContains('overflow', $codes('zuviel'), json_encode($by['zuviel']));
        $this->assertContains('small_text', $codes('zuviel'));
        $this->assertContains('image_broken', $codes('bildweg'));
        $this->assertSame([], array_values(array_filter($by['agenda']['issues'], fn ($i) => $i['level'] === 'error')), json_encode($by['agenda']));
        $this->assertIsInt($by['agenda']['fill']);

        $url = $by['cover']['image_url'];
        $this->assertNotNull($url);
        auth()->logout();
        $img = $this->get($url);
        $img->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame([640, 360], array_slice(getimagesizefromstring($img->getContent()), 0, 2));
        $this->withExceptionHandling();
        $this->get(preg_replace('/signature=[^&]+/', 'signature=falsch', $url))->assertForbidden();
    }

    public function test_show_page_versions_assets_check(): void
    {
        [$deck, $ctx, $d] = $this->deck();
        config(['slides.browser.enabled' => false]);
        $this->tool('CreateVersionTool', ['deck_id' => $deck->id, 'label' => 'Stand Strategiemeeting'], $ctx);
        $this->tool('CreateAssetTool', ['deck_id' => $deck->id, 'data' => self::PNG, 'name' => 'logo.png'], $ctx);

        $this->get(route('slides.decks.show', $deck))->assertOk()
            ->assertSee('Versionen')->assertSee('Stand Strategiemeeting')->assertSee('Festhalten')
            ->assertSee('Bilder')->assertSee('Bild hochladen')->assertSee('/slides/a/', false)
            ->assertSee('Qualität')->assertSee('Layout prüfen');

        $c = new \Platform\Slides\Livewire\Deck\Show();
        $c->mount($deck);
        $c->runCheck();
        $this->assertArrayHasKey('agenda', $c->check);
        $this->assertStringContainsString('Kein Headless-Browser', $c->notice);
        $html = $c->render()->render();
        $this->assertStringContainsString('Layout-Prüfung', $html);

        $c->versionLabel = 'UI-Stand';
        app(DeckService::class)->upsertSlides($deck, [['key' => 'x', 'html' => '<section><h2>X</h2></section>']], $d['user']);
        $c->createVersion();
        $v = SlidesDeckVersion::query()->where('label', 'UI-Stand')->first();
        $this->assertNotNull($v);
        $first = SlidesDeckVersion::query()->where('label', 'Stand Strategiemeeting')->first();
        $c->restoreVersion($first->id);
        $this->assertSame(['cover', 'agenda'], $deck->slides()->pluck('slide_key')->all());
        $this->assertStringContainsString('wiederhergestellt', $c->notice);
    }
}
