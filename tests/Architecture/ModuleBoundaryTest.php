<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * PENEGAKAN BATAS MODUL — mitigasi risiko RA-1 (ARCH-001 §10).
 *
 * Tanpa penegakan otomatis, batas modul pada modular monolith akan luntur
 * dalam 6 bulan. Ini pengalaman umum, bukan kekhawatiran teoretis. Bila itu
 * terjadi, sistem berubah menjadi "big ball of mud" dan syarat Project
 * Constitution — setiap modul dapat dipisahkan menjadi microservice —
 * menjadi mustahil dipenuhi (NFR-12).
 *
 * Uji ini GAGAL pada CI bila ada pelanggaran, sehingga tidak dapat
 * di-merge. Konvensi tim tidak cukup; harus ada pagar otomatis.
 */
final class ModuleBoundaryTest extends TestCase
{
    private const APP_PATH = __DIR__ . '/../../app';

    /**
     * ATURAN M-1 & M-2: modul hanya boleh mengakses modul lain melalui
     * namespace Contracts/. Akses ke Domain/, Application/, atau
     * Infrastructure/ modul lain DILARANG.
     */
    public function test_modul_hanya_mengakses_modul_lain_melalui_contracts(): void
    {
        $violations = [];

        foreach ($this->phpFilesIn(self::APP_PATH . '/Modules') as $file) {
            $module = $this->moduleNameOf($file);
            $contents = file_get_contents($file->getPathname());

            preg_match_all('/^use\s+(App\\\\Modules\\\\[^;]+);/m', $contents, $matches);

            foreach ($matches[1] as $import) {
                $parts = explode('\\', $import);
                $importedModule = $parts[2] ?? null;
                $importedLayer = $parts[3] ?? null;

                if ($importedModule === null || $importedModule === $module) {
                    continue; // impor internal modul sendiri — diizinkan
                }

                if ($importedLayer !== 'Contracts') {
                    $violations[] = sprintf(
                        '%s mengimpor %s (hanya Contracts/ yang diizinkan)',
                        $this->relativePath($file),
                        $import
                    );
                }
            }
        }

        $this->assertSame([], $violations, $this->explain(
            'Pelanggaran batas modul terdeteksi',
            $violations,
            'Gunakan interface pada Contracts/ atau Domain Event untuk komunikasi antar modul.'
        ));
    }

    /**
     * ATURAN LAPISAN: Domain tidak boleh bergantung pada apa pun di luar
     * dirinya — tidak pada Infrastructure, tidak pada framework.
     *
     * Alasan: aturan bisnis harus dapat diuji tanpa basis data, dan harus
     * tetap benar apa pun teknologi penyimpanannya.
     */
    public function test_domain_tidak_bergantung_pada_infrastructure_atau_framework(): void
    {
        $violations = [];
        $forbidden = [
            'Illuminate\\',
            'Infrastructure\\',
            'App\\Interfaces\\',
        ];

        foreach ($this->phpFilesIn(self::APP_PATH) as $file) {
            if (! str_contains($file->getPathname(), '/Domain/')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            preg_match_all('/^use\s+([^;]+);/m', $contents, $matches);

            foreach ($matches[1] as $import) {
                foreach ($forbidden as $prefix) {
                    if (str_starts_with($import, $prefix) || str_contains($import, '\\' . rtrim($prefix, '\\') . '\\')) {
                        $violations[] = sprintf(
                            '%s mengimpor %s',
                            $this->relativePath($file),
                            $import
                        );
                    }
                }
            }
        }

        $this->assertSame([], $violations, $this->explain(
            'Lapisan Domain terkontaminasi dependensi luar',
            $violations,
            'Domain harus murni. Definisikan interface di Domain, implementasikan di Infrastructure.'
        ));
    }

    /**
     * ATURAN M-3: ketergantungan antar modul harus SEARAH.
     * Ketergantungan melingkar membuat modul mustahil dipisahkan.
     */
    public function test_tidak_ada_ketergantungan_melingkar_antar_modul(): void
    {
        $graph = [];

        foreach ($this->phpFilesIn(self::APP_PATH . '/Modules') as $file) {
            $module = $this->moduleNameOf($file);
            $contents = file_get_contents($file->getPathname());

            preg_match_all('/^use\s+App\\\\Modules\\\\([A-Za-z0-9_]+)\\\\/m', $contents, $matches);

            foreach ($matches[1] as $dependency) {
                if ($dependency !== $module) {
                    $graph[$module][$dependency] = true;
                }
            }
        }

        $cycles = [];
        foreach ($graph as $module => $dependencies) {
            foreach (array_keys($dependencies) as $dependency) {
                if (isset($graph[$dependency][$module])) {
                    $pair = [$module, $dependency];
                    sort($pair);
                    $cycles[implode(' ⇄ ', $pair)] = true;
                }
            }
        }

        $this->assertSame([], array_keys($cycles), $this->explain(
            'Ketergantungan melingkar antar modul',
            array_keys($cycles),
            'Pecah ketergantungan dengan Domain Event, atau pindahkan kode bersama ke Shared/.'
        ));
    }

    /**
     * Shared/ dan Core/ tidak boleh bergantung pada modul bisnis mana pun.
     * Bila terjadi, komponen "bersama" itu sesungguhnya milik satu modul.
     */
    public function test_shared_dan_core_tidak_bergantung_pada_modul_bisnis(): void
    {
        $violations = [];

        foreach (['Shared', 'Core'] as $layer) {
            $path = self::APP_PATH . '/' . $layer;

            if (! is_dir($path)) {
                continue;
            }

            foreach ($this->phpFilesIn($path) as $file) {
                $contents = file_get_contents($file->getPathname());

                if (preg_match('/^use\s+App\\\\Modules\\\\/m', $contents)) {
                    $violations[] = $this->relativePath($file);
                }
            }
        }

        $this->assertSame([], $violations, $this->explain(
            'Shared/Core bergantung pada modul bisnis',
            $violations,
            'Balik arah ketergantungan: modul yang bergantung pada Shared, bukan sebaliknya.'
        ));
    }

    /** Nilai uang tidak boleh memakai float — DB-001 keputusan D-8. */
    public function test_tidak_ada_tipe_float_untuk_nilai_uang(): void
    {
        $violations = [];
        $moneyHints = ['amount', 'salary', 'rate', 'wage', 'allowance', 'tunjangan', 'gaji', 'upah'];

        foreach ($this->phpFilesIn(self::APP_PATH) as $file) {
            $contents = file_get_contents($file->getPathname());

            if (str_contains($file->getPathname(), '/Core/Domain/Money.php')) {
                continue; // Money sendiri boleh menerima float pada factory
            }

            preg_match_all('/(?:public|private|protected)\s+(?:readonly\s+)?float\s+\$(\w+)/', $contents, $matches);

            foreach ($matches[1] as $property) {
                foreach ($moneyHints as $hint) {
                    if (str_contains(strtolower($property), $hint)) {
                        $violations[] = sprintf('%s: $%s bertipe float', $this->relativePath($file), $property);
                    }
                }
            }
        }

        $this->assertSame([], $violations, $this->explain(
            'Nilai uang memakai float',
            $violations,
            'Gunakan App\\Core\\Domain\\Money. Kesalahan pembulatan tidak dapat diterima pada penggajian.'
        ));
    }

    /** @return iterable<SplFileInfo> */
    private function phpFilesIn(string $path): iterable
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }

    private function moduleNameOf(SplFileInfo $file): string
    {
        $relative = str_replace(self::APP_PATH . '/Modules/', '', $file->getPathname());

        return explode('/', $relative)[0];
    }

    private function relativePath(SplFileInfo $file): string
    {
        return str_replace(self::APP_PATH . '/', 'app/', $file->getPathname());
    }

    private function explain(string $title, array $violations, string $remedy): string
    {
        if ($violations === []) {
            return '';
        }

        return sprintf(
            "\n%s (%d):\n  - %s\n\nPerbaikan: %s\n",
            $title,
            count($violations),
            implode("\n  - ", $violations),
            $remedy
        );
    }
}
