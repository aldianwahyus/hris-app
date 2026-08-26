<?php

declare(strict_types=1);

namespace App\Shared\Workflow\Domain;

/**
 * TIGA POLA PERSETUJUAN yang berbeda secara struktural (ARCH-001 §5.4).
 *
 * Analisis dokumen kebijakan mengungkap bahwa ketiganya nyata dan tidak
 * dapat disatukan paksa. Membangun mesin dengan asumsi "satu approver =
 * atasan langsung" akan SALAH untuk dua dari tiga pola ini.
 */
enum ApprovalPattern: string
{
    /**
     * Individual berjenjang — dipakai CUTI.
     * Approver ditentukan matriks (jenis cuti × jabatan × lokasi kantor).
     * Sumber: BPP Fasilitas Cuti, matriks Kewenangan Memutus Cuti.
     */
    case IndividualHierarchical = 'individual';

    /**
     * Tunggal terpusat — dipakai LEMBUR.
     * MEMOTONG hierarki: langsung ke Direktur Bidang (unit pusat) atau
     * Direktur Pembina (KC/KCP). Satu pejabat melayani banyak unit.
     * Sumber: SK Upah Kerja Lembur.
     */
    case CentralizedSingle = 'centralized';

    /**
     * Komite / kuorum — dipakai PELANGGARAN & SANKSI.
     * Keputusan kolektif (KPP/KHC) dengan syarat kuorum, bukan
     * "satu approver per tahap".
     * Sumber: SPP Sanksi.
     */
    case Committee = 'committee';

    public function requiresQuorum(): bool
    {
        return $this === self::Committee;
    }

    /** Pola terpusat rawan bottleneck sehingga SLA menjadi wajib. */
    public function requiresSlaMonitoring(): bool
    {
        return $this === self::CentralizedSingle;
    }
}
