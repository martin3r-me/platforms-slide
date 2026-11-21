<?php

namespace Platform\Slides\Enums;

/** Folienformat: feste Pixel-Fläche, auf der jede Folie gestaltet wird. */
enum DeckSize: string
{
    case WIDE = '16:9';
    case STANDARD = '4:3';

    public function width(): int
    {
        return $this === self::WIDE ? 1920 : 1440;
    }

    public function height(): int
    {
        return 1080;
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
