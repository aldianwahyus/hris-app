<?php

declare(strict_types=1);

namespace App\Modules\Leave\Domain;

enum BucketType: string
{
    case CurrentYear = 'current_year';
    case CarryForward = 'carry_forward';
    case LongLeave = 'long_leave';
}
