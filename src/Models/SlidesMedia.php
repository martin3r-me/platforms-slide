<?php

namespace Platform\Slides\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SlidesMedia extends Model
{
    protected $table = 'slides_media';

    protected $fillable = [
        'uuid',
        'presentation_id',
        'filename',
        'path',
        'mime_type',
        'file_size',
        'width',
        'height',
        'user_id',
        'team_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;
        });
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(SlidesPresentation::class, 'presentation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }
}
