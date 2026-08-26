<?php

declare(strict_types=1);

namespace App\Modules\Employee\Domain;

/**
 * Menegakkan aturan atas SATU pengajuan perubahan data induk pegawai —
 * dipanggil baik saat diajukan (maker) maupun sesaat sebelum diterapkan
 * (checker menyetujui), karena data bisa saja berubah di antara
 * keduanya (baris lain sudah lebih dulu disetujui).
 */
final readonly class ProfileChangeValidator
{
    /**
     * @param  array<string, mixed>  $proposedChanges
     * @param  array<string, mixed>  $previousValues  Nilai emp_employees TERKINI untuk field yang sama.
     *
     * @throws InvalidProfileChange
     */
    public static function validate(array $proposedChanges, array $previousValues): void
    {
        if ($proposedChanges === []) {
            throw InvalidProfileChange::noChangesProposed();
        }

        foreach (array_keys($proposedChanges) as $field) {
            if (EditableEmployeeField::tryFrom($field) === null) {
                throw InvalidProfileChange::fieldNotEditable($field);
            }
        }

        $resultingStatus = $proposedChanges[EditableEmployeeField::EmploymentStatus->value]
            ?? $previousValues[EditableEmployeeField::EmploymentStatus->value]
            ?? null;

        $resultingPermanentDate = array_key_exists(EditableEmployeeField::PermanentDate->value, $proposedChanges)
            ? $proposedChanges[EditableEmployeeField::PermanentDate->value]
            : ($previousValues[EditableEmployeeField::PermanentDate->value] ?? null);

        if ($resultingStatus === EmploymentStatus::Tetap->value && $resultingPermanentDate === null) {
            throw InvalidProfileChange::permanentDateRequiredForTetap();
        }
    }

    /**
     * Field yang WAJIB ada di $previousValues agar validate() dapat
     * menyilangkan aturan status/permanent_date dengan benar, walau
     * hanya salah satunya yang diajukan berubah — dipakai pemanggil
     * (Application) untuk tahu field apa saja yang perlu diambil dari
     * emp_employees sebelum memanggil validate(), di luar field yang
     * benar-benar diajukan berubah.
     *
     * @param  array<string, mixed>  $proposedChanges
     * @return array<int, string>
     */
    public static function fieldsToSnapshot(array $proposedChanges): array
    {
        return array_unique([
            ...array_keys($proposedChanges),
            EditableEmployeeField::EmploymentStatus->value,
            EditableEmployeeField::PermanentDate->value,
        ]);
    }
}
