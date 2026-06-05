@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Backtests')

@php
  $statusColors = ['pending' => 'secondary', 'processing' => 'info', 'done' => 'success', 'failed' => 'danger'];
@endphp

@section('content')
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0">Backtests</h5>
  <a href="{{ route('backtests.create') }}" class="btn btn-primary">
    <i class="icon-base ri ri-upload-2-line me-1"></i>Import CSV
  </a>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover">
      <thead>
        <tr><th>Name</th><th>Status</th><th>Trades</th><th>Win Rate</th><th>Profit Factor</th><th>Net P/L</th><th>Created</th><th></th></tr>
      </thead>
      <tbody>
        @forelse($backtests as $bt)
          @php $m = $bt->metrics ?? []; @endphp
          <tr>
            <td><a href="{{ route('backtests.show', $bt) }}" class="fw-medium">{{ $bt->name }}</a></td>
            <td><span class="badge bg-label-{{ $statusColors[$bt->status] ?? 'secondary' }}">{{ ucfirst($bt->status) }}</span></td>
            <td>{{ $bt->rows_count }}</td>
            <td>{{ isset($m['win_rate']) ? $m['win_rate'].'%' : '—' }}</td>
            <td>{{ array_key_exists('profit_factor', $m) ? ($m['profit_factor'] ?? '∞') : '—' }}</td>
            <td>
              @if(isset($m['gross_profit'], $m['gross_loss']))
                @php $net = $m['gross_profit'] - $m['gross_loss']; @endphp
                <span class="{{ $net >= 0 ? 'text-success' : 'text-danger' }}">${{ number_format($net, 2) }}</span>
              @else — @endif
            </td>
            <td><small class="text-body-secondary">{{ optional($bt->created_at)->format('Y-m-d H:i') }}</small></td>
            <td class="text-end">
              <form action="{{ route('backtests.destroy', $bt) }}" method="POST" onsubmit="return confirm('Delete backtest {{ $bt->name }}?');">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-icon btn-text-danger"><i class="icon-base ri ri-delete-bin-line"></i></button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-body-secondary py-4">No backtests yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-4">{{ $backtests->links() }}</div>
@endsection
