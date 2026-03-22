<?php

namespace App\Enums;

enum SyncMode: string
{
    case Daily = 'daily';
    case Full = 'full';
    case Smart = 'smart';
    case Live = 'live';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Full => 'Full',
            self::Smart => 'Smart',
            self::Live => 'Live',
        };
    }
}
