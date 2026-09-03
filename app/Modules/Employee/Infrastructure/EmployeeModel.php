<?php

declare(strict_types=1);

namespace App\Modules\Employee\Infrastructure;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Eloquent internal modul Employee — TIDAK diekspor lewat
 * Contracts/. Modul lain berbicara lewat EmployeeRepository, tidak
 * pernah lewat model ini langsung (M-1/M-2).
 *
 * @property string $id
 * @property string $nrp
 * @property string $full_name
 * @property string $office_id
 * @property string $position_id
 * @property string $employment_status
 * @property ?string $separated_at
 */
final class EmployeeModel extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'emp_employees';

    protected $fillable = [
        'nrp', 'full_name', 'birth_date', 'gender', 'marital_status',
        'join_date', 'permanent_date', 'employment_status',
        'office_id', 'position_id', 'person_grade', 'job_grade', 'email',
    ];

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['id'];
    }
}
