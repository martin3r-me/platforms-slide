<?php

namespace Platform\Slides\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Uid\UuidV7;

/**
 * Bild für Folien (Team-weit, optional einem Deck zugeordnet). url() ist absolut und ohne Login abrufbar (Token).
 */
class SlidesAsset extends Model
{
    use SoftDeletes;

    protected $table = 'slides_assets';

    protected $fillable = ['uuid', 'token', 'team_id', 'deck_id', 'disk', 'path', 'original_name', 'mime_type', 'extension', 'size', 'width', 'height', 'alt', 'created_by'];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $asset) {
            if (empty($asset->uuid)) {
                $asset->uuid = (string) UuidV7::generate();
            }
        });
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(SlidesDeck::class, 'deck_id');
    }

    public function fileName(): string
    {
        return $this->token . '.' . $this->extension;
    }

    public function url(): ?string
    {
        return Route::has('slides.assets.show') ? route('slides.assets.show', $this->fileName()) : null;
    }

    /** Fertiges <img>-Snippet für Folien-HTML. */
    public function imgTag(): string
    {
        $attrs = 'src="' . e((string) $this->url()) . '" alt="' . e((string) ($this->alt ?? '')) . '"';
        if ($this->width && $this->height) {
            $attrs .= ' width="' . $this->width . '" height="' . $this->height . '"';
        }

        return '<img ' . $attrs . '>';
    }
}
