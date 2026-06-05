@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Trade Journal')

@php
  $gradeColors = ['A+' => 'success', 'A' => 'primary', 'B' => 'info', 'C' => 'warning'];
  $statusColors = ['open' => 'warning', 'closed' => 'secondary', 'cancelled' => 'danger'];
@endphp

@section('content')
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0">Trade Journal</h5>
  <a href="{{ route('trades.create') }}" class="btn btn-primary">
    <i class="icon-base ri ri-add-line me-1"></i>Log trade
  </a>
</div>

<div class="row g-4 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card"><div class="card-body">
      <p class="mb-1">Net P/L</p>
      <h4 class="mb-0 {{ $summary['net_pnl'] >= 0 ? 'text-success' : 'text-danger' }}">${{ number_format($summary['net_pnl'], 2) }}</h4>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card"><div class="card-body">
      <p class="mb-1">Win Rate</p>
      <h4 class="mb-0">{{ $summary['win_rate'] }}%</h4>
      <small class="text-body-secondary">{{ $summary['closed'] }} closed</small>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card"><div class="card-body">
      <p class="mb-1">Wins</p>
      <h4 class="mb-0 text-success">{{ $summary['wins'] }}</h4>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card"><div class="card-body">
      <p class="mb-1">Losses</p>
      <h4 class="mb-0 text-danger">{{ $summary['losses'] }}</h4>
      <small class="text-body-secondary">{{ $summary['open'] }} open</small>
    </div></div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Ticker</th><th>Direction</th><th>Status</th><th>Entry</th><th>Exit</th>
          <th>P/L</th><th>Grade</th><th>Opened</th><th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($trades as $trade)
          <tr>
            <td><a href="{{ route('trades.show', $trade) }}" class="fw-medium">{{ $trade->ticker }}</a></td>
            <td><span class="badge {{ $trade->direction === 'call' ? 'bg-label-success' : 'bg-label-danger' }}">{{ strtoupper($trade->direction) }}</span></td>
            <td><span class="badge bg-label-{{ $statusColors[$trade->status] ?? 'secondary' }}">{{ ucfirst($trade->status) }}</span></td>
            <td>${{ number_format((float) $trade->entry_price, 2) }}</td>
            <td>{{ $trade->exit_price !== null ? '$'.number_format((float) $trade->exit_price, 2) : '—' }}</td>
            <td>
              @if($trade->pnl !== null)
                <span class="{{ (float) $trade->pnl >= 0 ? 'text-success' : 'text-danger' }}">${{ number_format((float) $trade->pnl, 2) }}</span>
              @else — @endif
            </td>
            <td>@if($trade->signal_grade)<span class="badge bg-label-{{ $gradeColors[$trade->signal_grade] ?? 'secondary' }}">{{ $trade->signal_grade }}</span>@else — @endif</td>
            <td><small class="text-body-secondary">{{ optional($trade->opened_at)->format('Y-m-d H:i') }}</small></td>
            <td class="text-end">
              <a href="{{ route('trades.edit', $trade) }}" class="btn btn-sm btn-icon btn-text-secondary"><i class="icon-base ri ri-edit-line"></i></a>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-body-secondary py-4">No trades logged yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-4">{{ $trades->links() }}</div>
@endsection
