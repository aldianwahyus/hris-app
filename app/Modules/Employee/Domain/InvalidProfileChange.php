<?php

declare(strict_types=1);

namespace App\Modules\Employee\Domain;

use DomainException;

/** Perubahan data induk pegawai yang diajukan melanggar aturan Domain. */
final class InvalidProfileChange extends DomainException
{
    public static function fieldNotEditable(string $field): self
    {
        return new self(sprintf(
            'Field "%s" tidak termasuk data yang dapat diubah lewat pengajuan ini.',
            $field,
        ));
    }

    public static function noChangesProposed(): self
    {
        return new self('Tidak ada perubahan yang diajukan.');
    }

    public static function permanentDateRequiredForTetap(): self
    {
        return new self(
            'Status kepegawaian "Tetap" wajib disertai tanggal pengangkatan tetap '.
            '(permanent_date) — baik yang sudah tercatat maupun yang diajukan bersamaan.'
        );
    }
}
