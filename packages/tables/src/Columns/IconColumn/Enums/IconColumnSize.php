<?php

namespace Filament\Tables\Columns\IconColumn\Enums;

enum IconColumnSize
{
    case ExtraSmall;

    case Small;

    case Medium;

    case Large;

    case ExtraLarge;

    case TwoExtraLarge;

    /**
     * @deprecated Use `TwoExtraLarge` instead.
     */
    public const ExtraExtraLarge = self::TwoExtraLarge;
}
