<?php

namespace Platform\Slides\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Slides\Enums\DeckSize;
use Platform\Slides\Enums\DeckStatus;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Deck = Präsentation im HTML-Folienformat. Jede Folie ist genau ein <section> auf 1920×1080 (4:3: 1440×1080);
 * theme_css gilt für alle Folien. revision steigt bei jeder Änderung (Live-Aktualisierung offener Ansichten).
 * Lose an beliebige Objekte gekoppelt über slides_deck_links (z. B. Gremium/Sitzung im Management Book).
 *
 * @property-read string $uuid
 */
class SlidesDeck extends Model implements HasDisplayName
{
    use SoftDeletes;

    public const MORPH_ALIAS = 'slides_deck';

    protected $table = 'slides_decks';

    protected $fillable = [
        'uuid', 'team_id', 'title', 'description', 'size', 'theme_css', 'fonts', 'status', 'revision',
        'owner_user_id', 'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
        'size' => DeckSize::class,
        'status' => DeckStatus::class,
        'fonts' => 'array',
        'revision' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $deck) {
            if (empty($deck->uuid)) {
                $deck->uuid = (string) UuidV7::generate();
            }
            $deck->size ??= DeckSize::WIDE;
            $deck->status ??= DeckStatus::DRAFT;
            $deck->revision = max(1, (int) $deck->revision);
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'owner_user_id');
    }

    public function slides(): HasMany
    {
        return $this->hasMany(SlidesDeckSlide::class, 'deck_id')->orderBy('position')->orderBy('id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(SlidesDeckLink::class, 'deck_id');
    }

    /** Revision erhöhen, ohne andere Felder anzufassen (für Live-Ansichten). */
    public function bumpRevision(): void
    {
        $this->newQuery()->whereKey($this->getKey())->increment('revision');
        $this->revision = (int) $this->newQuery()->whereKey($this->getKey())->value('revision');
    }

    public function getDisplayName(): ?string
    {
        return $this->title;
    }
}
