<?php

declare(strict_types=1);

namespace App\Shared\Signature\Application;

use App\Core\Domain\Uuid7;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Tanda Tangan Elektronik INTERNAL — SATU mekanisme dipakai lintas
 * jenis dokumen lewat signable_type/signable_id polimorfik murni
 * string (pola SAMA aud_change_logs). `document_hash` sengaja dihitung
 * dari IDENTITAS dokumen+penandatangan+waktu (bukan byte PDF — banyak
 * dokumen di aplikasi ini, mis. SK, sudah tergenerate sebagai berkas
 * terpisah di S3 SEBELUM ditandatangani, menera ulang byte-nya butuh
 * pustaka manipulasi PDF yang di luar cakupan modul ini) — tetap sah
 * sebagai jejak anti-ubah karena isi dokumen (SK/Surat, dst.) sendiri
 * ditentukan PENUH oleh baris database-nya, jadi hash ini SETARA
 * "menera versi baris itu pada saat ditandatangani".
 */
final class SignDocument
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function handle(
        string $signableType,
        string $signableId,
        string $signerEmployeeId,
        string $signerName,
        ?string $signerRole,
        ?string $signatureImageBase64,
        ?string $typedName,
        ?string $ipAddress,
        string $contextRef,
        AuditActor $actor,
    ): string {
        if (($signatureImageBase64 === null || $signatureImageBase64 === '') && ($typedName === null || trim($typedName) === '')) {
            throw new DomainException('Wajib mengisi tanda tangan (gambar) atau nama ketik.');
        }

        $alreadySigned = DB::table('sig_signatures')
            ->where('signable_type', $signableType)
            ->where('signable_id', $signableId)
            ->where('signer_employee_id', $signerEmployeeId)
            ->exists();

        if ($alreadySigned) {
            throw new DomainException('Dokumen ini sudah Anda tandatangani sebelumnya.');
        }

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        $documentHash = hash('sha256', json_encode([
            'signable_type' => $signableType,
            'signable_id' => $signableId,
            'context_ref' => $contextRef,
            'signer_employee_id' => $signerEmployeeId,
            'signed_at' => $now->format('c'),
        ], JSON_THROW_ON_ERROR));

        DB::table('sig_signatures')->insert([
            'id' => $id,
            'signable_type' => $signableType,
            'signable_id' => $signableId,
            'signer_employee_id' => $signerEmployeeId,
            'signer_name_snapshot' => $signerName,
            'signer_role_snapshot' => $signerRole,
            'signature_image_base64' => $signatureImageBase64 !== '' ? $signatureImageBase64 : null,
            'typed_name' => $typedName !== '' ? $typedName : null,
            'signed_at' => $now,
            'ip_address' => $ipAddress,
            'document_hash' => $documentHash,
            'created_at' => $now,
        ]);

        $this->audit->append(new AuditEntry(
            occurredAt: $now,
            actor: $actor,
            auditableType: $signableType,
            auditableId: $signableId,
            action: AuditAction::Signed,
            contextRef: $contextRef,
        ));

        return $id;
    }
}
