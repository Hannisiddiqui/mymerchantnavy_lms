<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReportStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    /** The question was checked and fixed (or found correct and explained to the student). */
    case Resolved = 'resolved';
    /** Not a real problem (the question is right as it is). */
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Resolved => 'Resolved',
            self::Rejected => 'No change needed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Resolved => 'success',
            self::Rejected => 'gray',
        };
    }
}
