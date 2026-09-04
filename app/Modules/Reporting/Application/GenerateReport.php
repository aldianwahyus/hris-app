<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application;

use App\Modules\Reporting\Domain\ReportColumn;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menjalankan satu subjek Report Builder dengan kolom+filter yang
 * DIPILIH pengguna, dibatasi ketat ke whitelist subjek itu (lihat
 * ReportSubject) — TIDAK PERNAH menyisipkan input pengguna langsung ke
 * SQL, hanya KUNCI kolom yang divalidasi terhadap array kolom yang
 * SUDAH didefinisikan tetap.
 */
final class GenerateReport
{
    public function __construct(private readonly ReportSubjectRegistry $registry) {}

    /**
     * @param  array<int, string>  $columnKeys
     * @param  array{start?: string, end?: string, status?: string}  $filters
     * @return array{0: array<int, ReportColumn>, 1: Collection<int, \stdClass>}
     */
    public function handle(string $subjectKey, array $columnKeys, array $filters, ?string $officeId): array
    {
        $subject = $this->registry->find($subjectKey);

        if ($subject === null) {
            throw new DomainException('Subjek laporan tidak dikenal.');
        }

        $available = $subject->columns();
        /** @var array<int, ReportColumn> $selected */
        $selected = array_values(array_intersect_key($available, array_flip($columnKeys)));

        if ($selected === []) {
            throw new DomainException('Pilih minimal satu kolom untuk laporan ini.');
        }

        $query = $subject->query($officeId);

        $dateColumn = $subject->dateColumn();
        $start = $filters['start'] ?? null;
        $end = $filters['end'] ?? null;

        if ($start !== null && $start !== '') {
            $query->whereDate($dateColumn, '>=', $start);
        }

        if ($end !== null && $end !== '') {
            $query->whereDate($dateColumn, '<=', $end);
        }

        $status = $filters['status'] ?? null;

        if ($status !== null && $status !== '' && array_key_exists($status, $subject->statusOptions())) {
            $query->where($subject->statusColumn(), $status);
        }

        foreach ($selected as $column) {
            $query->addSelect(DB::raw("{$column->sql} as \"{$column->key}\""));
        }

        return [$selected, $query->get()];
    }
}
