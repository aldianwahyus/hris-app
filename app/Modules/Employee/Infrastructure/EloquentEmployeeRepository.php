<?php

declare(strict_types=1);

namespace App\Modules\Employee\Infrastructure;

use App\Modules\Employee\Contracts\EmployeeRepository;
use App\Modules\Employee\Domain\Employee;
use App\Modules\Employee\Domain\EmploymentStatus;
use Illuminate\Support\Facades\DB;

final class EloquentEmployeeRepository implements EmployeeRepository
{
    public function findByNrp(string $nrp): ?Employee
    {
        $row = EmployeeModel::query()->where('nrp', $nrp)->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function findById(string $id): ?Employee
    {
        $row = EmployeeModel::query()->find($id);

        return $row === null ? null : $this->toDomain($row);
    }

    /** @return array<int, string> */
    public function officeIdsInTree(string $officeId): array
    {
        // Kantor tidak berjumlah besar (puluhan hingga ratusan) — telusuri
        // di memori agar tidak bergantung pada dukungan WITH RECURSIVE
        // yang berbeda antara PostgreSQL (produksi) dan SQLite (lokal).
        $offices = DB::table('md_offices')->select('id', 'parent_office_id')->get();

        $childrenOf = [];
        foreach ($offices as $office) {
            if ($office->parent_office_id !== null) {
                $childrenOf[$office->parent_office_id][] = $office->id;
            }
        }

        $result = [$officeId];
        $queue = [$officeId];

        while ($queue !== []) {
            $current = array_pop($queue);
            foreach ($childrenOf[$current] ?? [] as $childId) {
                if (! in_array($childId, $result, true)) {
                    $result[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $result;
    }

    /** @return array<int, string> */
    public function officeIdsByType(array $officeTypes): array
    {
        return DB::table('md_offices')
            ->whereIn('office_type', $officeTypes)
            ->pluck('id')
            ->all();
    }

    private function toDomain(EmployeeModel $row): Employee
    {
        return new Employee(
            id: $row->id,
            nrp: $row->nrp,
            fullName: $row->full_name,
            officeId: $row->office_id,
            positionId: $row->position_id,
            employmentStatus: EmploymentStatus::from($row->employment_status),
            separatedAt: $row->separated_at,
        );
    }
}
