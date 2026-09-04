<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Backup Basis Data Otomatis — Fase 2 (evaluasi PM/client 2026-09-03).
 * `pg_dump` DI-FAKE (Process::fake) — jalur GAGAL (exit code bukan 0)
 * WAJIB diuji juga, bukan hanya jalur sukses (lihat Pola Bersama Fase
 * 2 di plan: kode yang menjaga sistem, bukan mempercepat pekerjaan).
 */
final class BackupDatabaseTest extends TestCase
{
    use DatabaseTransactions;

    public function test_backup_berhasil_mengunggah_berkas_gzip_ke_s3(): void
    {
        Storage::fake('s3');
        $this->fakeSuccessfulPgDump();

        $this->artisan('backup:database')->assertExitCode(0);

        $database = config('database.connections.pgsql.database');
        $files = Storage::disk('s3')->files("backups/{$database}");

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.sql.gz', $files[0]);
        $this->assertNotEmpty(Storage::disk('s3')->get($files[0]));
    }

    public function test_backup_gagal_bila_pg_dump_gagal(): void
    {
        Storage::fake('s3');
        Process::fake(fn (PendingProcess $process) => Process::result(output: '', errorOutput: 'koneksi ditolak', exitCode: 1));

        $this->artisan('backup:database')->assertExitCode(1);

        $database = config('database.connections.pgsql.database');
        $this->assertCount(0, Storage::disk('s3')->files("backups/{$database}"));
    }

    public function test_cadangan_di_luar_masa_retensi_dihapus(): void
    {
        Storage::fake('s3');
        $database = config('database.connections.pgsql.database');

        // Berkas "lama" — Storage::fake tidak mendukung mtime kustom
        // lewat put() biasa, jadi disimulasikan dengan menulis langsung
        // lalu memundurkan waktu via touch() pada disk lokal fake.
        $oldPath = "backups/{$database}/2020-01-01_000000.sql.gz";
        Storage::disk('s3')->put($oldPath, 'dump-lama');
        touch(Storage::disk('s3')->path($oldPath), now()->subDays(40)->timestamp);

        $this->fakeSuccessfulPgDump();

        $this->artisan('backup:database')->assertExitCode(0);

        $this->assertFalse(Storage::disk('s3')->exists($oldPath), 'Cadangan di luar retensi (30 hari) seharusnya sudah terhapus.');
    }

    private function fakeSuccessfulPgDump(): void
    {
        Process::fake(function (PendingProcess $process) {
            /** @var array<int, string> $command */
            $command = $process->command;
            $index = array_search('-f', $command, true);

            if ($index !== false && isset($command[$index + 1])) {
                file_put_contents($command[$index + 1], "-- dump uji\nSELECT 1;\n");
            }

            return Process::result(output: '', errorOutput: '', exitCode: 0);
        });
    }
}
