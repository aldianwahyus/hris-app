<tr>
  <td style="padding-left:{{ 8 + $depth * 20 }}px">{{ $depth > 0 ? '↳ ' : '' }}{{ $node->employee->full_name }}</td>
  <td>{{ $node->employee->position_name }}</td>
  <td>{{ $node->employee->nrp }}</td>
</tr>
@foreach ($node->children as $child)
  @include('admin._org-chart-pdf-row', ['node' => $child, 'depth' => $depth + 1])
@endforeach
