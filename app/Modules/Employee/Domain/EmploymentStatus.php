<?php

declare(strict_types=1);

namespace App\Modules\Employee\Domain;

enum EmploymentStatus: string
{
    case Tetap = 'tetap';
    case Trainee = 'trainee';
    case Kontrak = 'kontrak';
    case Outsource = 'outsource';
}
