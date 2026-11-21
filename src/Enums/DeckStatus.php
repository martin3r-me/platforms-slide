<?php

namespace Platform\Slides\Enums;

enum DeckStatus: string
{
    case DRAFT = 'draft';
    case READY = 'ready';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Entwurf',
            self::READY => 'Fertig',
            self::ARCHIVED => 'Archiviert',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::DRAFT => 'neutral',
            self::READY => 'success',
            self::ARCHIVED => 'neutral',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
