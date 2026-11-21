<?php

namespace Platform\Slides\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lose Kopplung Deck ↔ beliebiges Objekt (Morph-Alias + ID), z. B. management_book_meeting #7. */
class SlidesDeckLink extends Model
{
    protected $table = 'slides_deck_links';

    protected $fillable = ['deck_id', 'linkable_type', 'linkable_id', 'role', 'created_by'];

    protected $casts = ['linkable_id' => 'integer'];

    public function deck(): BelongsTo
    {
        return $this->belongsTo(SlidesDeck::class, 'deck_id');
    }
}
