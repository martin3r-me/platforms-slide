<?php

namespace Tests\Feature;

require_once __DIR__ . '/SlidesTestHarness.php';

use Platform\Core\Contracts\ToolContext;
use Platform\Slides\Models\SlidesDeck;
use Tests\TestCase;

/** Service + MCP-Tools: Deck anlegen, Batch-Upsert, Reihenfolge, revision, Links, Rechte, Duplizieren. */
class DeckToolsTest extends TestCase
{
    use SlidesTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSlides();
        $this->withoutExceptionHandling();
    }

    private function run_(string $tool, array $args, ToolContext $ctx)
    {
        $cls = '\\Platform\\Slides\\Tools\\' . $tool;

        return (new $cls())->execute($args, $ctx);
    }

    public function test_tools_end_to_end(): void
    {
        $d = $this->seedUsers();
        $ctx = new ToolContext($d['user'], $d['team']);

        $g = $this->run_('GetGuideTool', [], $ctx);
        $this->assertTrue($g->success);
        $this->assertArrayHasKey('classes', $g->data['theme']);

        $r = $this->run_('CreateDeckTool', ['title' => 'Strategie 2027', 'linkable_type' => 'management_book_meeting', 'linkable_id' => 7], $ctx);
        $this->assertTrue($r->success, json_encode($r));
        $deckId = $r->data['deck']['id'];
        $this->assertSame('16:9', $r->data['deck']['size']);
        $this->assertSame('management_book_meeting', $r->data['link']['linkable_type']);
        $deck = SlidesDeck::find($deckId);
        $this->assertStringContainsString('--accent', $deck->theme_css, 'Standard-Theme gesetzt');
        $rev0 = $deck->revision;

        // Batch-Upsert: 3 neue Folien, eine mit Script + aside
        $r = $this->run_('UpsertSlidesTool', ['deck_id' => $deckId, 'slides' => [
            ['key' => 'cover', 'html' => '<section class="title"><h1>BHG.DIGITAL 2027</h1><aside>Begrüßung</aside></section>'],
            ['key' => 'agenda', 'html' => '<section><h2>Agenda</h2><script>alert(1)</script><ul><li>A</li></ul></section>', 'notes' => 'Kurz halten'],
            ['key' => 'kpis', 'html' => '<section><h2>Zahlen</h2></section>'],
        ]], $ctx);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame(3, $r->data['slide_count']);
        $agenda = collect($r->data['slides'])->firstWhere('key', 'agenda');
        $this->assertNotEmpty($agenda['removed']);
        $this->assertGreaterThan($rev0, $r->data['revision']);

        $r = $this->run_('ListSlidesTool', ['deck_id' => $deckId], $ctx);
        $this->assertSame(['cover', 'agenda', 'kpis'], array_column($r->data['slides'], 'key'));
        $this->assertSame('Begrüßung', $r->data['slides'][0]['notes']);
        $this->assertSame('Kurz halten', $r->data['slides'][1]['notes']);
        $this->assertStringNotContainsString('<script', $r->data['slides'][1]['html']);

        // Update nur Notizen + neue Folie an Position 1
        $r = $this->run_('UpsertSlidesTool', ['deck_id' => $deckId, 'slides' => [
            ['key' => 'cover', 'notes' => 'Neu'],
            ['key' => 'ziel', 'html' => '<section class="statement"><h1>Ziel</h1></section>', 'position' => 1],
        ]], $ctx);
        $this->assertTrue($r->success, json_encode($r));
        $keys = array_column($this->run_('ListSlidesTool', ['deck_id' => $deckId, 'include_html' => false], $ctx)->data['slides'], 'key');
        $this->assertSame(['cover', 'ziel', 'agenda', 'kpis'], $keys);

        // Neue Folie ohne html → Fehler, ungültiger Key → Fehler
        $this->assertFalse($this->run_('UpsertSlidesTool', ['deck_id' => $deckId, 'slides' => [['key' => 'x']]], $ctx)->success);
        $this->assertFalse($this->run_('UpsertSlidesTool', ['deck_id' => $deckId, 'slides' => [['key' => 'a b', 'html' => '<section></section>']]], $ctx)->success);

        // Reihenfolge
        $r = $this->run_('OrderSlidesTool', ['deck_id' => $deckId, 'keys' => ['kpis', 'cover']], $ctx);
        $this->assertSame(['kpis', 'cover', 'ziel', 'agenda'], $r->data['order']);
        $this->assertFalse($this->run_('OrderSlidesTool', ['deck_id' => $deckId, 'keys' => ['gibtsnicht']], $ctx)->success);

        // Löschen
        $r = $this->run_('DeleteSlidesTool', ['deck_id' => $deckId, 'keys' => ['ziel', 'nope']], $ctx);
        $this->assertSame(['ziel'], $r->data['deleted']);
        $this->assertSame(['nope'], $r->data['not_found']);

        // Deck-Update mit bösem Theme
        $r = $this->run_('UpdateDeckTool', ['deck_id' => $deckId, 'status' => 'ready', 'theme_css' => '@import url(x); section{color:red}', 'include_theme' => true], $ctx);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame('ready', $r->data['deck']['status']);
        $this->assertStringNotContainsString('@import', $r->data['deck']['theme_css']);
        $this->assertNotEmpty($r->data['removed']);
        $this->assertFalse($this->run_('UpdateDeckTool', ['deck_id' => $deckId, 'size' => '1:1'], $ctx)->success);

        // Links
        $r = $this->run_('ListLinksTool', ['linkable_type' => 'management_book_meeting', 'linkable_id' => 7], $ctx);
        $this->assertCount(1, $r->data['links']);
        $this->assertTrue($this->run_('CreateLinkTool', ['deck_id' => $deckId, 'linkable_type' => 'management_book_committee', 'linkable_id' => 4, 'role' => 'pre-read'], $ctx)->success);
        $this->assertCount(2, $this->run_('ListLinksTool', ['deck_id' => $deckId], $ctx)->data['links']);
        $this->assertTrue($this->run_('DeleteLinkTool', ['deck_id' => $deckId, 'linkable_type' => 'management_book_committee', 'linkable_id' => 4], $ctx)->data['deleted']);
        $r = $this->run_('ListDecksTool', ['linkable_type' => 'management_book_meeting', 'linkable_id' => 7], $ctx);
        $this->assertSame(1, $r->data['total']);

        // Duplizieren
        $r = $this->run_('DuplicateDeckTool', ['deck_id' => $deckId, 'title' => 'Vorlage'], $ctx);
        $this->assertSame(3, SlidesDeck::find($r->data['deck']['id'])->slides()->count());
        $this->assertSame('draft', $r->data['deck']['status']);

        // Fremder User: kein Zugriff
        $other = new ToolContext($d['other'], $d['team']);
        $this->assertFalse($this->run_('ListSlidesTool', ['deck_id' => $deckId], $other)->success);
        $this->assertFalse($this->run_('UpsertSlidesTool', ['deck_id' => $deckId, 'slides' => [['key' => 'z', 'html' => '<section></section>']]], $other)->success);

        // Löschen (Soft)
        $this->assertTrue($this->run_('DeleteDeckTool', ['deck_id' => $deckId], $ctx)->success);
        $this->assertNotNull(SlidesDeck::withTrashed()->find($deckId)->deleted_at);
    }

    public function test_ui_pages_render(): void
    {
        $d = $this->seedUsers();
        $ctx = new ToolContext($d['user'], $d['team']);
        $deckId = $this->run_('CreateDeckTool', ['title' => 'Board Q4'], $ctx)->data['deck']['id'];
        $this->run_('UpsertSlidesTool', ['deck_id' => $deckId, 'slides' => [
            ['key' => 'cover', 'html' => '<section class="title"><h1>Board Q4</h1></section>', 'notes' => 'Hallo'],
            ['key' => 'two', 'html' => '<section><h2>Zweite</h2></section>'],
        ]], $ctx);
        $deck = SlidesDeck::find($deckId);

        $this->get(route('slides.dashboard'))->assertOk()->assertSee('Board Q4')
            ->assertSee('href="' . route('slides.decks.show', $deck) . '"', false);
        $this->get(route('slides.decks.show', $deck))->assertOk()
            ->assertSee('sandbox="allow-same-origin"', false)
            ->assertSee('srcdoc=', false)
            ->assertSee('Content-Security-Policy', false)
            ->assertSee('Hallo');
        // Präsentation: Zähler und Navigation laufen im Browser; der Startindex kommt aus ?slide=n (1-basiert)
        $this->get(route('slides.decks.present', $deck))->assertOk()->assertSee("\\u0022index\\u0022:0", false);
        $this->get(route('slides.decks.present', $deck) . '?slide=2')->assertOk()->assertSee("\\u0022index\\u0022:1", false);
        $this->get(route('slides.decks.pdf', $deck))->assertOk()->assertSee('@page', false);

        // Fremder User sieht das Deck nicht
        $this->actingAs($d['other']);
        $this->withExceptionHandling();
        $this->get(route('slides.decks.show', $deck))->assertForbidden();
    }
}
