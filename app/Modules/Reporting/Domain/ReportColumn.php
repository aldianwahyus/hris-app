<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain;

/**
 * Satu kolom yang BOLEH dipilih pengguna Report Builder. `sql` adalah
 * ekspresi SQL SUMBER (bukan input pengguna — didefinisikan tetap di
 * tiap kelas ReportSubject) sehingga aman disisipkan via DB::raw()
 * tanpa risiko SQL injection; pengguna hanya memilih `key` dari daftar
 * whitelist, TIDAK PERNAH mengetik SQL sendiri.
 */
final readonly class ReportColumn
{
    public function __construct(
        public string $key,
        public string $label,
        public string $sql,
    ) {}
}
