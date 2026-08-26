<li>
  <div class="kotak">
    <div class="avatar">{{ $node->initials }}</div>
    <div class="nm">{{ $node->employee->full_name }}</div>
    <div class="jb">{{ $node->employee->position_name }}</div>
    <div class="np">{{ $node->employee->nrp }}</div>
  </div>
  @if ($node->children->isNotEmpty())
    <ul>
      @foreach ($node->children as $child)
        @include('admin._org-chart-person-node', ['node' => $child])
      @endforeach
    </ul>
  @endif
</li>
