<?php

namespace App\Enums;

enum HousekeepingStatus: string
{
    case Dirty = 'dirty';
    case Cleaning = 'cleaning';
    case Ready = 'ready';
}
