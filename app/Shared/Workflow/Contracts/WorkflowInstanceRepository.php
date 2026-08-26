<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Contracts;

use App\Shared\Workflow\Domain\WorkflowInstance;
use DateTimeImmutable;
use DomainException;

/**
 * Satu-satunya pintu bagi modul lain untuk memulai instansi alur
 * persetujuan tanpa menyentuh Infrastructure Workflow secara langsung.
 */
interface WorkflowInstanceRepository
{
    /**
     * Memulai instansi baru dari definisi alur AKTIF untuk jenis dokumen
     * ini, lalu menyimpannya (wf_instances + langkah berjalan saat ini).
     *
     * $slaAnchor menentukan titik awal penghitungan tenggat SLA bila
     * BERBEDA dari waktu instansi dibuat ($now) — mis. lembur (DEC-94):
     * jendela 1 bulan dihitung sejak TANGGAL PELAKSANAAN, bukan sejak
     * pengajuan dikirim, sehingga pengajuan retroaktif tidak diam-diam
     * memperpanjang tenggat sesungguhnya. Baris wf_instances/
     * wf_instance_steps.started_at TETAP mencatat $now (kapan proses
     * persetujuan benar-benar dimulai) — hanya sla_due_at yang memakai
     * $slaAnchor. Default null berarti sla_due_at dihitung dari $now,
     * sesuai perilaku sebelumnya (Tahap 2 — Cuti tidak retroaktif).
     *
     * @throws DomainException bila tidak ada definisi alur aktif
     */
    public function startFor(
        string $documentType,
        string $documentId,
        string $initiatorId,
        DateTimeImmutable $now,
        ?DateTimeImmutable $slaAnchor = null,
    ): WorkflowInstance;

    /**
     * Memuat ulang instansi yang MASIH PENDING agar mutator Domain
     * (mis. expire()) dapat dipanggil. Mengembalikan null bila instansi
     * tidak ditemukan atau sudah final — mencegah transisi ganda pada
     * baris yang sudah diproses proses lain.
     */
    public function findPending(string $instanceId): ?WorkflowInstance;

    /**
     * Menyimpan transisi status yang sudah ditegakkan Domain (status,
     * langkah berjalan, waktu selesai). Peristiwa Domain (releaseEvents)
     * TIDAK ditangani di sini — itu tanggung jawab pemanggil, karena
     * penulisan audit trail adalah keputusan lintas-modul.
     */
    public function save(WorkflowInstance $instance): void;
}
