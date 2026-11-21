<?php

namespace Platform\Slides\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Schnappschuss eines Decks (Eckdaten, Theme, alle Folien). snapshot = ['deck' => [...], 'slides' => [[key, position, html, notes, is_hidden], …]].
 */
class SlidesDeckVersion extends Model
{
    public const KIND_AUTO = 'auto';
    public const KIND_MANUAL = 'manual';
    public const KIND_BEFORE_RESTORE = 'before_restore';

    protected $table = 'slides_deck_versions';

    protected $fillable = ['deck_id', 'revision', 'kind', 'label', 'slide_count', 'content_hash', 'snapshot', 'created_by'];

    protected $casts = [
        'revision' => 'integer',
        'slide_count' => 'integer',
        'snapshot' => 'array',
    ];

    public function deck(): BelongsTo
    {
        return $this->belongsTo(SlidesDeck::class, 'deck_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by');
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::KIND_MANUAL => 'Festgehalten',
            self::KIND_BEFORE_RESTORE => 'Vor Wiederherstellung',
            default => 'Automatisch',
        };
    }
}
