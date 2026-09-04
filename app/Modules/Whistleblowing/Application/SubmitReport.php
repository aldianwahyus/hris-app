<?php

declare(strict_types=1);

namespace App\Modules\Whistleblowing\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Mengajukan laporan Whistleblowing/Pengaduan (Fase 2) — kategori
 * WAJIB salah satu dari daftar tetap (lihat CATEGORIES).
 *
 * Saat $isAnonymous true: `reporter_employee_id` SENGAJA disetel null
 * (BUKAN cuma disembunyikan di tampilan) DAN jejak audit dicatat TANPA
 * actorId/IP/user-agent — data itu SAMA membocorkan identitas pelapor
 * seperti menyimpan token anti-duplikat, jadi keduanya SENGAJA tidak
 * dilakukan di sini (BEDA dari pola Submit* modul lain yang SELALU
 * merekam identitas lengkap pelaku).
 */
final class SubmitReport
{
    public const CATEGORIES = [
        'fraud' => 'Kecurangan/Fraud',
        'corruption' => 'Korupsi/Gratifikasi',
        'harassment' => 'Pelecehan',
        'code_of_conduct' => 'Pelanggaran Kode Etik',
        'other' => 'Lainnya',
    ];

    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(string $category, string $description, bool $isAnonymous, ?string $employeeId, AuditActor $actor): string
    {
        if (! array_key_exists($category, self::CATEGORIES)) {
            throw new DomainException('Kategori pengaduan tidak dikenal.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('wb_reports')->insert([
            'id' => $id,
            'category' => $category,
            'description' => $description,
            'is_anonymous' => $isAnonymous,
            'reporter_employee_id' => $isAnonymous ? null : $employeeId,
            'status' => 'baru',
            'created_at' => $now,
        ]);

        $auditActor = $isAnonymous ? new AuditActor(actorId: null, actorRole: 'pelapor (anonim)') : $actor;

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $auditActor,
            auditableType: 'wb_report',
            auditableId: $id,
            action: AuditAction::Submitted,
        ));

        return $id;
    }
}
