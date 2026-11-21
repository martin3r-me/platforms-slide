<?php

namespace Tests\Feature;

require_once __DIR__ . '/SlidesTestHarness.php';

use Platform\Core\Contracts\ToolContext;
use Platform\Slides\Models\SlidesDeck;
use Platform\Slides\Rendering\SlideDocument;
use Platform\Slides\Services\DeckService;
use Tests\TestCase;

/** UI: Dashboard, Deck-Ansicht, Präsentation, Referentenansicht, Druck/PDF per HTTP; Player-Payload, Fragmente, Editor-Info. */
class UiTest extends TestCase
{
    use SlidesTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSlides();
        $this->withoutExceptionHandling();
    }

    private function deck(array $d): SlidesDeck
    {
        $svc = app(DeckService::class);
        ['deck' => $deck] = $svc->createDeck($d['user'], $d['teamId'], ['title' => 'Strategie 2027', 'description' => 'Für das Board']);
        $svc->upsertSlides($deck, [
            ['key' => 'cover', 'html' => '<section class="title"><h1>BHG.DIGITAL 2027</h1><aside>Begrüßung</aside></section>'],
            ['key' => 'agenda', 'html' => '<section><h2>Agenda</h2><ol><li class="fragment">Eins</li><li class="fragment" data-fragment-index="2">Zwei</li></ol></section>', 'notes' => 'Kurz halten'],
            ['key' => 'geheim', 'html' => '<section><h2>Intern</h2></section>', 'is_hidden' => true],
        ], $d['user']);
        $svc->link($deck->fresh(), 'management_book_meeting', 7, 'presentation', $d['user']);

        return $deck->fresh();
    }

    public function test_pages_render(): void
    {
        $d = $this->seedUsers();
        $deck = $this->deck($d);

        $this->get(route('slides.dashboard'))->assertOk()
            ->assertSee('Strategie 2027')->assertSee('Decks, die mitdenken')->assertSee('2 Folien');
        $this->get(route('slides.dashboard') . '?q=gibtsnicht')->assertOk()->assertSee('Keine Decks für diese Auswahl');

        $this->get(route('slides.decks.show', $deck))->assertOk()
            ->assertSee('Sprechernotizen')->assertSee('Begrüßung')->assertSee('Referentenansicht')
            ->assertSee('Sitzung')->assertSee('#7')                     // Link-Typ + nicht auflösbares Objekt
            ->assertSee('aktualisiert')->assertSee('Martin', false)
            ->assertSee('Alle Folien')->assertSee('ausgeblendet');
        $this->get(route('slides.decks.show', $deck) . '?slide=agenda')->assertOk()->assertSee('Kurz halten');

        $present = $this->get(route('slides.decks.present', $deck) . '?slide=2')->assertOk()
            ->assertSee('slidesPlayer', false)->assertSee('Referentenansicht')->assertSee('Vollbild (F)', false);
        $html = $present->getContent();
        $this->assertStringNotContainsString('Intern', $html, 'ausgeblendete Folie nicht in der Präsentation');
        $this->assertStringContainsString('slides-deck-' . $deck->uuid, $html, 'BroadcastChannel je Deck');

        $this->get(route('slides.decks.presenter', $deck))->assertOk()
            ->assertSee('Als Nächstes')->assertSee('Notizen')->assertSee('Präsentation öffnen')->assertSee('Timer starten');

        $this->get(route('slides.decks.pdf', $deck) . '?print=1')->assertOk()
            ->assertSee('Drucken / PDF')->assertSee('@page', false)->assertSee('2 Seiten');

        // Serverseitiges PDF aus → Weiterleitung auf die Druckansicht
        config(['slides.pdf.server' => false]);
        $this->get(route('slides.decks.pdf.download', $deck))->assertRedirect(route('slides.decks.pdf', $deck) . '?print=1');
    }

    public function test_empty_deck_explains_agents(): void
    {
        $d = $this->seedUsers();
        ['deck' => $deck] = app(DeckService::class)->createDeck($d['user'], $d['teamId'], ['title' => 'Leer']);
        $this->get(route('slides.decks.show', $deck))->assertOk()
            ->assertSee('Dieses Deck ist noch leer')->assertSee('slides.guide.GET')->assertSee('slides.slides.PUT');
        $this->get(route('slides.decks.present', $deck))->assertOk()->assertSee('noch keine sichtbaren Folien');
    }

    public function test_fragments_static_vs_live_and_editor_tracking(): void
    {
        $d = $this->seedUsers();
        $deck = $this->deck($d);
        $slide = $deck->slides()->where('slide_key', 'agenda')->first();

        $live = SlideDocument::render($deck, $slide, false);
        $static = SlideDocument::render($deck, $slide, true);
        $this->assertStringContainsString('.fragment{opacity:0', $live);
        $this->assertStringContainsString('class="is-live"', $live);
        $this->assertStringContainsString('class="is-static"', $static);
        $this->assertStringContainsString('opacity:1!important', $static, 'Standbild zeigt alle Klickschritte');

        // Wer zuletzt geändert hat: Tool-Aufruf setzt den User aus dem Kontext
        $agent = \Platform\Core\Models\User::create(['name' => 'Ratchet', 'email' => 'r@x.test', 'password' => 'x']);
        \Illuminate\Support\Facades\DB::table('team_user')->insert(['team_id' => $d['teamId'], 'user_id' => $agent->id, 'role' => 'member', 'created_at' => now(), 'updated_at' => now()]);
        $before = $deck->revision;
        $r = (new \Platform\Slides\Tools\UpsertSlidesTool())->execute(['deck_id' => $deck->id, 'slides' => [['key' => 'cover', 'notes' => 'Neu']]], new ToolContext($agent, $d['team']));
        $this->assertTrue($r->success, json_encode($r));
        $deck->refresh();
        $this->assertGreaterThan($before, $deck->revision);
        $this->assertSame($agent->id, (int) $deck->last_editor_user_id);
        $this->assertNotNull($deck->last_edited_at);
        $this->get(route('slides.decks.show', $deck))->assertOk()->assertSee('Ratchet');

        // Player-Payload: nur sichtbare Folien, Hash ändert sich nur bei Inhalt
        $p = new \Platform\Slides\Livewire\Deck\Present();
        $p->deck = $deck;
        $m = new \ReflectionMethod($p, 'slidePayload');
        $m->setAccessible(true);
        $a = $m->invoke($p);
        $this->assertSame(['cover', 'agenda'], array_column($a, 'key'));
        app(DeckService::class)->upsertSlides($deck, [['key' => 'agenda', 'html' => '<section><h2>Agenda neu</h2></section>']], $d['user']);
        $b = $m->invoke($p);
        $this->assertSame($a[0]['hash'], $b[0]['hash'], 'unveränderte Folie behält ihren Hash');
        $this->assertNotSame($a[1]['hash'], $b[1]['hash'], 'geänderte Folie bekommt neuen Hash');
    }

    public function test_status_and_duplicate_and_rights(): void
    {
        $d = $this->seedUsers();
        $deck = $this->deck($d);

        $c = new \Platform\Slides\Livewire\Deck\Show();
        $c->mount($deck);
        $c->setStatus('ready');
        $this->assertSame('ready', $deck->fresh()->status->value);
        $c->setStatus('quatsch');
        $this->assertSame('ready', $deck->fresh()->status->value);
        $c->duplicate();
        $this->assertSame(2, SlidesDeck::query()->where('team_id', $d['teamId'])->count());

        // Fremder User sieht weder Präsentation noch Referentenansicht
        $this->actingAs($d['other']);
        $this->withExceptionHandling();
        $this->get(route('slides.decks.present', $deck))->assertForbidden();
        $this->get(route('slides.decks.presenter', $deck))->assertForbidden();
        $this->get(route('slides.decks.pdf', $deck))->assertForbidden();
    }
}
