<?php

declare(strict_types=1);

namespace App\Modules\Employee\Application;

use App\Core\Domain\Uuid7;
use App\Models\User;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * hr_approver (checker) menyetujui/menolak usulan pegawai baru dari
 * SYSADMIN — persetujuan-lah yang BENAR-BENAR membuat baris
 * emp_employees + akun login (users + peran pegawai), pola sama
 * DecideEmployeeProfileChange (§6.3).
 */
final class DecideNewEmployeeRequest
{
    public function __construct(private readonly AuditRepository $audit) {}

    /** Kata sandi acak yang baru dibuat — HANYA dikembalikan sekali, tidak pernah disimpan/ditampilkan ulang. */
    public function approve(string $requestId, AuditActor $actor, ?string $note = null): string
    {
        return DB::transaction(function () use ($requestId, $actor, $note) {
            $request = DB::table('emp_new_employee_requests')
                ->where('id', $requestId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($request === null) {
                throw new DomainException('Pengajuan tidak ditemukan atau sudah diputus sebelumnya.');
            }

            if ($request->requested_by === $actor->actorId) {
                throw new DomainException('Tidak dapat menyetujui pengajuan pegawai baru yang Anda usulkan sendiri.');
            }

            $data = json_decode($request->proposed_data, true);
            $nrp = $data['nrp'];

            if (DB::table('emp_employees')->where('nrp', $nrp)->exists()) {
                throw new DomainException("NRP \"{$nrp}\" sudah terpakai — pengajuan ini tidak dapat disetujui, tolak dan minta diajukan ulang.");
            }

            $now = new DateTimeImmutable;
            $employeeId = (string) Uuid7::generate();

            DB::table('emp_employees')->insert([
                'id' => $employeeId,
                'nrp' => $nrp,
                'full_name' => $data['full_name'],
                'birth_date' => $data['birth_date'] ?? null,
                'gender' => $data['gender'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'tanggungan' => $data['tanggungan'] ?? 0,
                'join_date' => $data['join_date'],
                'permanent_date' => $data['permanent_date'] ?? null,
                'employment_status' => $data['employment_status'],
                'office_id' => $data['office_id'],
                'supervisor_id' => $data['supervisor_id'] ?? null,
                'position_id' => $data['position_id'],
                'division' => $data['division'] ?? null,
                'agama' => $data['agama'] ?? null,
                'nomor_ktp' => $data['nomor_ktp'] ?? null,
                'nomor_npwp' => $data['nomor_npwp'] ?? null,
                'bpjs_tenaga_kerja' => $data['bpjs_tenaga_kerja'] ?? null,
                'bpjs_kesehatan' => $data['bpjs_kesehatan'] ?? null,
                'nomor_simpeda' => $data['nomor_simpeda'] ?? null,
                'nomor_tambora_rencana' => $data['nomor_tambora_rencana'] ?? null,
                'tmt_pangkat' => $data['tmt_pangkat'] ?? null,
                'person_grade' => $data['person_grade'] ?? null,
                'job_grade' => $data['job_grade'] ?? null,
                'salary_step' => $data['salary_step'] ?? 1,
                'email' => $data['email'] ?? null,
                'photo_path' => $data['photo_path'] ?? null,
                'alamat' => $data['alamat'] ?? null,
                'no_telepon' => $data['no_telepon'] ?? null,
                'kontak_darurat_nama' => $data['kontak_darurat_nama'] ?? null,
                'kontak_darurat_hubungan' => $data['kontak_darurat_hubungan'] ?? null,
                'kontak_darurat_telepon' => $data['kontak_darurat_telepon'] ?? null,
                'pendidikan_terakhir' => $data['pendidikan_terakhir'] ?? null,
                'pendidikan_jurusan' => $data['pendidikan_jurusan'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
                'version' => 1,
            ]);

            $newPassword = Str::random(14);
            $userId = DB::table('users')->insertGetId([
                'employee_id' => $employeeId,
                'name' => $data['full_name'],
                'email' => $data['email'] ?? (str_replace(' ', '', $nrp).'@bankntbsyariah.demo'),
                'password' => Hash::make($newPassword),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $pegawaiRoleId = DB::table('roles')->where('name', 'pegawai')->value('id');

            DB::table('model_has_roles')->insert([
                'role_id' => $pegawaiRoleId,
                'model_type' => User::class,
                'model_id' => $userId,
            ]);

            DB::table('emp_new_employee_requests')->where('id', $requestId)->update([
                'status' => 'approved',
                'decided_by' => $actor->actorId,
                'decided_at' => $now,
                'decision_note' => $note,
                'created_employee_id' => $employeeId,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'new_employee_request',
                auditableId: $requestId,
                action: AuditAction::Approved,
                newValues: ['employee_id' => $employeeId, 'nrp' => $nrp],
                contextRef: $requestId,
            ));

            return $newPassword;
        });
    }

    public function reject(string $requestId, AuditActor $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($requestId, $actor, $note) {
            $request = DB::table('emp_new_employee_requests')
                ->where('id', $requestId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($request === null) {
                throw new DomainException('Pengajuan tidak ditemukan atau sudah diputus sebelumnya.');
            }

            $now = new DateTimeImmutable;

            DB::table('emp_new_employee_requests')->where('id', $requestId)->update([
                'status' => 'rejected',
                'decided_by' => $actor->actorId,
                'decided_at' => $now,
                'decision_note' => $note,
                'updated_at' => $now,
            ]);

            $this->audit->append(new AuditEntry(
                occurredAt: $now,
                actor: $actor,
                auditableType: 'new_employee_request',
                auditableId: $requestId,
                action: AuditAction::Rejected,
                contextRef: $requestId,
            ));
        });
    }
}
