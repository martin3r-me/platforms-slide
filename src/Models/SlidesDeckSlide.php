<?php

namespace Platform\Slides\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * Eine Folie: genau ein bereinigtes <section>-Element (html) plus Sprechernotizen (notes), adressiert über slide_key.
 *
 * @property-read string $uuid
 */
class SlidesDeckSlide extends Model
{
    use SoftDeletes;

    protected $table = 'slides_deck_slides';

    protected $fillable = ['uuid', 'deck_id', 'slide_key', 'position', 'html', 'notes', 'is_hidden'];

    protected $casts = [
        'uuid' => 'string',
        'position' => 'integer',
        'is_hidden' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $slide) {
            if (empty($slide->uuid)) {
                $slide->uuid = (string) UuidV7::generate();
            }
        });
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(SlidesDeck::class, 'deck_id');
    }
}
