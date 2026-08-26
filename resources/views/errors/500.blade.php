@include('errors._shell', [
    'icon' => '⚠️',
    'title' => 'Terjadi Kesalahan',
    'message' => $message ?? 'Terjadi kesalahan pada sistem. Silakan coba lagi beberapa saat lagi. Jika masalah berlanjut, laporkan ke Admin/IT.',
    'reference' => $reference ?? null,
    'swalIcon' => 'error',
])
