@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Backtest · ' . $backtest->name)

@php
  $statusColors = ['pending' => 'secondary', 'processing' => 'info', 'done' => 'success', 'failed' => 'danger'];
  $resultColors = ['win' => 'success', 'loss' => 'danger', 'be' => 'secondary'];
  $m = $backtest->metrics ?? [];
@endphp

@section('content')
@if (session('status'))<div class="alert alert-info">{{ session('status') }}</div>@endif

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0">{{ $backtest->name }}
    <span class="badge bg-label-{{ $statusColors[$backtest->status] ?? 'secondary' }} ms-2">{{ ucfirst($backtest->status) }}</span>
  </h5>
  <div class="d-flex gap-2">
    <a href="{{ route('backtests.index') }}" class="btn btn-outline-secondary">Back</a>
    <form action="{{ route('backtests.destroy', $backtest) }}" method="POST" onsubmit="return confirm('Delete this backtest?');">
      @csrf @method('DELETE')
      <button type="submit" class="btn btn-outline-danger">Delete</button>
    </form>
  </div>
</div>

@if(in_array($backtest->status, ['pending', 'processing']))
  <div class="alert alert-warning">
    <h6 class="alert-heading mb-1"><i class="icon-base ri ri-loader-4-line me-1"></i>Processing</h6>
    This backtest is still being processed. Refresh this page in a moment to see the results.
  </div>
@elseif($backtest->status === 'failed')
  <div class="alert alert-danger">
    <h6 class="alert-heading mb-1"><i class="icon-base ri ri-error-warning-line me-1"></i>Import failed</h6>
    {{ $backtest->error ?: 'An unknown error occurred while parsing the CSV.' }}
  </div>
@else
  <div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="card"><div class="card-body">
        <p class="mb-1">Win Rate</p>
        <h4 class="mb-0">{{ $m['win_rate'] ?? 0 }}%</h4>
        <small class="text-body-secondary">{{ $m['wins'] ?? 0 }}W / {{ $m['losses'] ?? 0 }}L · {{ $m['total_trades'] ?? 0 }} trades</small>
      </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card"><div class="card-body">
        <p class="mb-1">Profit Factor</p>
        <h4 class="mb-0">{{ array_key_exists('profit_factor', $m) ? ($m['profit_factor'] ?? '∞') : '—' }}</h4>
      </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card"><div class="card-body">
        <p class="mb-1">Expectancy</p>
        <h4 class="mb-0 {{ ($m['expectancy'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">${{ number_format($m['expectancy'] ?? 0, 2) }}</h4>
        <small class="text-body-secondary">per trade</small>
      </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card"><div class="card-body">
        <p class="mb-1">Max Drawdown</p>
        <h4 class="mb-0 text-danger">${{ number_format($m['max_drawdown'] ?? 0, 2) }}</h4>
      </div></div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card"><div class="card-body">
        <p class="mb-1">Avg Win</p>
        <h4 class="mb-0 text-success">${{ number_format($m['avg_win'] ?? 0, 2) }}</h4>
      </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card"><div class="card-body">
        <p class="mb-1">Avg Loss</p>
        <h4 class="mb-0 text-danger">${{ number_format($m['avg_loss'] ?? 0, 2) }}</h4>
      </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card"><div class="card-body">
        <p class="mb-1">Gross P/L</p>
        @php $net = ($m['gross_profit'] ?? 0) - ($m['gross_loss'] ?? 0); @endphp
        <h4 class="mb-0 {{ $net >= 0 ? 'text-success' : 'text-danger' }}">${{ number_format($net, 2) }}</h4>
        <small class="text-body-secondary">+${{ number_format($m['gross_profit'] ?? 0, 2) }} / -${{ number_format($m['gross_loss'] ?? 0, 2) }}</small>
      </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card"><div class="card-body">
        <p class="mb-1">Rows Parsed</p>
        <h4 class="mb-0">{{ $backtest->rows_count }}</h4>
      </div></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h6 class="m-0">Best Performers</h6></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3"><small class="text-body-secondary d-block">Best Ticker</small><span class="fw-medium">{{ $m['best_ticker'] ?? '—' }}</span></div>
        <div class="col-md-3"><small class="text-body-secondary d-block">Best Timeframe</small><span class="fw-medium">{{ $m['best_timeframe'] ?? '—' }}</span></div>
        <div class="col-md-3"><small class="text-body-secondary d-block">Best Grade</small><span class="fw-medium">{{ $m['best_grade'] ?? '—' }}</span></div>
        <div class="col-md-3"><small class="text-body-secondary d-block">Best Setup</small><span class="fw-medium">{{ $m['best_setup'] ?? '—' }}</span></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h6 class="m-0">Parsed Trades</h6></div>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr><th>Ticker</th><th>TF</th><th>Direction</th><th>Entry</th><th>Exit</th><th>P/L</th><th>Result</th><th>Grade</th><th>Setup</th><th>When</th></tr>
        </thead>
        <tbody>
          @forelse($backtest->trades as $t)
            <tr>
              <td>{{ $t->ticker ?? '—' }}</td>
              <td>{{ $t->timeframe ?? '—' }}</td>
              <td><span class="badge {{ $t->direction === 'put' ? 'bg-label-danger' : 'bg-label-success' }}">{{ strtoupper($t->direction ?? '—') }}</span></td>
              <td>{{ $t->entry !== null ? number_format((float) $t->entry, 4) : '—' }}</td>
              <td>{{ $t->exit !== null ? number_format((float) $t->exit, 4) : '—' }}</td>
              <td><span class="{{ (float) $t->pnl >= 0 ? 'text-success' : 'text-danger' }}">${{ number_format((float) $t->pnl, 2) }}</span></td>
              <td><span class="badge bg-label-{{ $resultColors[$t->result] ?? 'secondary' }}">{{ strtoupper($t->result) }}</span></td>
              <td>{{ $t->grade ?? '—' }}</td>
              <td>{{ $t->setup ?? '—' }}</td>
              <td><small class="text-body-secondary">{{ optional($t->occurred_at)->format('Y-m-d H:i') ?: '—' }}</small></td>
            </tr>
          @empty
            <tr><td colspan="10" class="text-center text-body-secondary py-4">No trades parsed.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endif
@endsection
