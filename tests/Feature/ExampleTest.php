<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Sejak seluruh layar operasional berada di belakang 'auth' (Tahap 1
     * — Autentikasi & Hak Akses), tamu diarahkan ke halaman masuk.
     */
    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/masuk');
    }
}
