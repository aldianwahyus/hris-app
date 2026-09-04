{{--
  Grafik garis SVG polos (TANPA pustaka eksternal) dipakai ulang lintas
  dasbor. Butuh var: $points (array of ['label'=>string,'value'=>number]).
  Opsional: $color, $suffix, $min, $max, $height (default disetel di sini
  karena @include TIDAK mendukung nilai default seperti @props komponen).
--}}
@php
  $color ??= 'var(--hijau-tua)';
  $suffix ??= '';
  $min ??= null;
  $max ??= null;
  $height ??= 140;
  $values = array_column($points, 'value');
  $dataMin = $min ?? (count($values) ? min($values) : 0);
  $dataMax = $max ?? (count($values) ? max($values) : 1);
  if ($dataMax <= $dataMin) { $dataMax = $dataMin + 1; }
  $width = 680;
  $padX = 14;
  $padY = 18;
  $n = count($points);
  $stepX = $n > 1 ? ($width - 2 * $padX) / ($n - 1) : 0;
  $scaleY = fn ($v) => $height - $padY - (($v - $dataMin) / ($dataMax - $dataMin)) * ($height - 2 * $padY);
  $coords = [];
  foreach ($points as $i => $p) {
      $coords[] = ['x' => $padX + $i * $stepX, 'y' => $scaleY($p['value']), 'label' => $p['label'], 'value' => $p['value']];
  }
  $path = '';
  foreach ($coords as $i => $c) {
      $path .= ($i === 0 ? 'M' : 'L') . round($c['x'], 1) . ',' . round($c['y'], 1) . ' ';
  }
@endphp
@if ($n === 0)
  <div style="padding:24px;text-align:center;color:var(--teks-lemah);font-size:12.5px">Belum ada data.</div>
@else
  <svg viewBox="0 0 {{ $width }} {{ $height }}" style="width:100%;height:{{ $height }}px;display:block" role="img" aria-label="Grafik tren">
    <line x1="{{ $padX }}" y1="{{ $height - $padY }}" x2="{{ $width - $padX }}" y2="{{ $height - $padY }}" stroke="var(--garis)" stroke-width="1"/>
    @if (count($coords) > 1)
      <path d="{{ trim($path) }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    @endif
    @foreach ($coords as $i => $c)
      <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="4.5" fill="{{ $color }}">
        <title>{{ $c['label'] }}: {{ $c['value'] }}{{ $suffix }}</title>
      </circle>
      @if ($i === count($coords) - 1)
        <text x="{{ $c['x'] }}" y="{{ max($c['y'] - 10, 10) }}" text-anchor="end" font-size="11" font-weight="700" fill="{{ $color }}">{{ $c['value'] }}{{ $suffix }}</text>
      @endif
    @endforeach
  </svg>
  <div style="display:flex;justify-content:space-between;font-size:10.5px;color:var(--teks-lemah);margin-top:2px">
    <span>{{ $points[0]['label'] ?? '' }}</span>
    <span>{{ $points[$n - 1]['label'] ?? '' }}</span>
  </div>
  <details style="margin-top:8px">
    <summary style="font-size:11px;color:var(--teks-lemah);cursor:pointer">Lihat sebagai tabel</summary>
    <table style="width:100%;border-collapse:collapse;margin-top:6px">
      <thead>
        <tr><th style="text-align:left;font-size:10.5px;color:var(--teks-lemah);padding:4px 0">Periode</th><th style="text-align:right;font-size:10.5px;color:var(--teks-lemah);padding:4px 0">Nilai</th></tr>
      </thead>
      <tbody>
        @foreach ($points as $p)
          <tr><td style="font-size:11.5px;padding:3px 0;border-top:1px solid var(--garis)">{{ $p['label'] }}</td><td style="font-size:11.5px;padding:3px 0;border-top:1px solid var(--garis);text-align:right">{{ $p['value'] }}{{ $suffix }}</td></tr>
        @endforeach
      </tbody>
    </table>
  </details>
@endif
