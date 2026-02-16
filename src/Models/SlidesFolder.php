<?php

namespace Platform\Slides\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class SlidesFolder extends Model
{
    use SoftDeletes;

    protected $table = 'slides_folders';

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'order',
        'parent_id',
        'user_id',
        'team_id',
    ];

    protected $casts = [
        'uuid' => 'string',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SlidesFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(SlidesFolder::class, 'parent_id')->orderBy('order');
    }

    public function presentations(): HasMany
    {
        return $this->hasMany(SlidesPresentation::class, 'folder_id')->orderBy('name');
    }

    public function folderUsers(): HasMany
    {
        return $this->hasMany(SlidesFolderUser::class, 'folder_id');
    }

    /**
     * Gibt die effektive Rolle eines Users für diesen Ordner zurück.
     * Prüft zuerst direkte Berechtigungen, dann vererbte vom Parent.
     */
    public function getEffectiveRoleForUser($userId): ?string
    {
        // 1. Direkte Berechtigung prüfen
        $folderUser = $this->folderUsers()->where('user_id', $userId)->first();
        if ($folderUser && $folderUser->role) {
            return $folderUser->role;
        }

        // 2. Owner hat immer Zugriff
        if ($this->user_id === $userId) {
            return 'owner';
        }

        // 3. Vererbung vom Parent prüfen (rekursiv)
        if ($this->parent_id) {
            $parent = $this->parent;
            if ($parent) {
                return $parent->getEffectiveRoleForUser($userId);
            }
        }

        return null;
    }
}
