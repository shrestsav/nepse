<?php

namespace App\Enums;

enum BlogContentFormat: string
{
    case Markdown = 'markdown';
    case RichText = 'rich_text';

    public function label(): string
    {
        return match ($this) {
            self::Markdown => 'Markdown',
            self::RichText => 'Rich text',
        };
    }
}
