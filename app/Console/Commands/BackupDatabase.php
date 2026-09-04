<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Shared\Configuration\Domain\ParameterResolver;
use App\Shared\Temporal\Domain\AsOfDate;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Backup Basis Data Otomatis — Fase 2 (evaluasi PM/client 2026-09-03).
 * `pg_dump` format plain SQL (bukan custom/-Fc) — SENGAJA, supaya
 * pemulihan bisa lewat `psql` biasa tanpa bergantung versi `pg_restore`
 * yang cocok. Dump ditulis ke berkas SEMENTARA lokal dulu (bukan
 * ditangkap sebagai string di memori) karena `pg_dump -f` jauh lebih
 * hemat memori untuk basis data besar — HANYA hasil gzip-nya yang
 * dimuat penuh ke memori sebelum diunggah (keterbatasan yang jujur
 * diakui, bukan streaming penuh — cukup untuk skala basis data ini).
 */
final class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Cadangkan basis data ke S3/MinIO (pg_dump) dan hapus cadangan di luar masa retensi.';

    public function __construct(private readonly ParameterResolver $parameters)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $connection = config('database.connections.pgsql');
        $database = (string) $connection['database'];
        $timestamp = now()->format('Y-m-d_His');
        $tempPath = storage_path("app/tmp-backup-{$database}-{$timestamp}.sql");

        $result = Process::env(['PGPASSWORD' => (string) $connection['password']])
            ->timeout(600)
            ->run([
                'pg_dump',
                '-h', (string) $connection['host'],
                '-p', (string) $connection['port'],
                '-U', (string) $connection['username'],
                '-F', 'p',
                '-f', $tempPath,
                $database,
            ]);

        if (! $result->successful()) {
            $this->error("pg_dump gagal: {$result->errorOutput()}");
            @unlink($tempPath);

            return self::FAILURE;
        }

        $dump = file_get_contents($tempPath);
        @unlink($tempPath);

        if ($dump === false || $dump === '') {
            $this->error('Berkas dump kosong atau gagal dibaca.');

            return self::FAILURE;
        }

        $gzipped = gzencode($dump, 9);

        if ($gzipped === false) {
            $this->error('Gagal mengompresi dump.');

            return self::FAILURE;
        }

        $remotePath = "backups/{$database}/{$timestamp}.sql.gz";
        Storage::disk('s3')->put($remotePath, $gzipped);

        $this->info("Backup tersimpan: {$remotePath} (".number_format(strlen($gzipped) / 1024, 1).' KB)');

        $this->pruneOldBackups($database);

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $database): void
    {
        $retentionDays = $this->parameters->integer('DATABASE_BACKUP_RETENTION_DAYS', AsOfDate::on(new DateTimeImmutable));
        $cutoff = now()->subDays($retentionDays);

        $deleted = 0;

        foreach (Storage::disk('s3')->files("backups/{$database}") as $file) {
            $lastModified = Storage::disk('s3')->lastModified($file);

            if ($lastModified !== false && Carbon::createFromTimestamp($lastModified)->lt($cutoff)) {
                Storage::disk('s3')->delete($file);
                $deleted++;
            }
        }

        $this->info("Cadangan di luar retensi ({$retentionDays} hari) dihapus: {$deleted}");
    }
}
