{{-- Bar CSS buatan sendiri (bukan library chart) — konsisten dengan gaya
     seluruh aplikasi ini (.ring/.baris), tidak menambah dependency JS.
     Butuh var: $label, $value, $max, $color (opsional, default hijau-tua). --}}
@php
  $persen = $max > 0 ? min(100, max(0, ($value / $max) * 100)) : 0;
  $warna = $color ?? 'var(--hijau-tua)';
@endphp
<div class="baris-bar">
  <div class="baris-bar-label">
    <span>{{ $label }}</span>
    <span class="angka">{{ $value }}</span>
  </div>
  <div class="baris-bar-trek">
    <div class="baris-bar-isi" style="width:{{ $persen }}%;background:{{ $warna }}"></div>
  </div>
</div>
