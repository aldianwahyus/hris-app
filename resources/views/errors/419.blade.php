@include('errors._shell', [
    'icon' => '⏳',
    'title' => 'Sesi Telah Berakhir',
    'message' => 'Sesi Anda sudah tidak berlaku (mis. terlalu lama tidak aktif, atau masuk lewat tab lain). Silakan masuk kembali.',
    'swalIcon' => 'info',
    'linkUrl' => url('/masuk'),
    'linkText' => 'Masuk Kembali',
])
