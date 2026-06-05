@php
$configData = Helper::appClasses();
@endphp
@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/apex-charts/apexcharts.js'])
@endsection

@section('page-script')
@vite(['resources/assets/js/osp-dashboard.js', 'resources/assets/js/osp-quotes.js'])
@endsection

@php
  $gradeColors = ['A+' => 'success', 'A' => 'primary', 'B' => 'info', 'C' => 'warning', 'ignore' => 'secondary'];
  $sideBadge = fn ($s) => $s === 'buy_call' ? 'bg-label-success' : ($s === 'buy_put' ? 'bg-label-danger' : 'bg-label-warning');
@endphp

@section('content')
<div class="row g-6 mb-6">
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <span class="badge bg-label-primary rounded p-2"><i class="icon-base ri ri-line-chart-line icon-lg"></i></span>
        </div>
        <p class="mb-1 mt-3">Net P/L</p>
        <h4 class="mb-1 {{ $stats['net_pnl'] >= 0 ? 'text-success' : 'text-danger' }}">${{ number_format($stats['net_pnl'], 2) }}</h4>
        <small class="text-body-secondary">Today: <span class="{{ $stats['daily_pnl'] >= 0 ? 'text-success' : 'text-danger' }}">${{ number_format($stats['daily_pnl'], 2) }}</span></small>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <span class="badge bg-label-success rounded p-2"><i class="icon-base ri ri-trophy-line icon-lg"></i></span>
        </div>
        <p class="mb-1 mt-3">Win Rate</p>
        <h4 class="mb-1">{{ $stats['win_rate'] }}%</h4>
        <small class="text-body-secondary">{{ $stats['wins'] }}W / {{ $stats['losses'] }}L ({{ $stats['closed_trades'] }} closed)</small>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <span class="badge bg-label-info rounded p-2"><i class="icon-base ri ri-flashlight-line icon-lg"></i></span>
        </div>
        <p class="mb-1 mt-3">Signal Accuracy</p>
        <h4 class="mb-1">{{ $stats['signal_accuracy'] }}%</h4>
        <small class="text-body-secondary">{{ $stats['active_signals'] }} active signals</small>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <span class="badge bg-label-warning rounded p-2"><i class="icon-base ri ri-star-line icon-lg"></i></span>
        </div>
        <p class="mb-1 mt-3">Best Ticker</p>
        <h4 class="mb-1">{{ $stats['best_ticker'] ?? '—' }}</h4>
        <small class="text-body-secondary">{{ $stats['open_trades'] }} open trades</small>
      </div>
    </div>
  </div>
</div>

<script type="application/json" id="osp-chart-data">@json($charts)</script>

<div class="row g-6 mb-6">
  <div class="col-xl-8">
    <div class="card h-100">
      <div class="card-header"><h5 class="card-title m-0">Equity Curve <small class="text-body-secondary">(cumulative realised P/L)</small></h5></div>
      <div class="card-body"><div id="osp-equity-chart"></div></div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card mb-6">
      <div class="card-header"><h5 class="card-title m-0">Win / Loss</h5></div>
      <div class="card-body"><div id="osp-winloss-chart"></div></div>
    </div>
    <div class="card">
      <div class="card-header"><h5 class="card-title m-0">Signal Grades</h5></div>
      <div class="card-body"><div id="osp-grades-chart"></div></div>
    </div>
  </div>
</div>

<div class="row g-6">
  <!-- Latest signals -->
  <div class="col-xl-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Latest Signals</h5>
        <a href="{{ route('signals.index') }}" class="btn btn-sm btn-outline-primary">View all</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr><th>Ticker</th><th>Side</th><th>TF</th><th>Grade</th><th>Score</th><th>Price</th><th>Signal time</th></tr>
          </thead>
          <tbody>
            @forelse($latestSignals as $signal)
              <tr>
                <td><a href="{{ route('signals.show', $signal) }}" class="fw-medium">{{ $signal->ticker }}</a></td>
                <td><span class="badge {{ $sideBadge($signal->signal_type) }}">{{ strtoupper(str_replace('buy_', '', $signal->signal_type)) }}</span></td>
                <td>{{ $signal->timeframe }}</td>
                <td><span class="badge bg-label-{{ $gradeColors[$signal->grade] ?? 'secondary' }}">{{ $signal->grade }}</span></td>
                <td>{{ $signal->total_score }}</td>
                <td>${{ number_format((float) $signal->price, 2) }}</td>
                <td>
                  <span class="d-block">{{ optional($signal->occurred_at)->format('M j, H:i:s') }}</span>
                  <small class="text-body-secondary">{{ optional($signal->occurred_at)->diffForHumans() }}</small>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-body-secondary py-4">No signals yet. Wire up your TradingView alert to start receiving them.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Active ideas + watchlist -->
  <div class="col-xl-4">
    <div class="card mb-6">
      <div class="card-header"><h5 class="card-title m-0">Active Trade Ideas</h5></div>
      <div class="card-body">
        @forelse($activeIdeas as $idea)
          <div class="d-flex align-items-center mb-4">
            <div class="badge {{ $sideBadge($idea->signal_type) }} rounded p-2 me-3">{{ $idea->ticker }}</div>
            <div class="flex-grow-1">
              <h6 class="mb-0">{{ strtoupper(str_replace('buy_', '', $idea->signal_type)) }} · {{ $idea->timeframe }}</h6>
              <small class="text-body-secondary">Grade {{ $idea->grade }} · {{ $idea->total_score }} pts</small>
            </div>
          </div>
        @empty
          <p class="text-body-secondary mb-0">No active A/B-grade ideas.</p>
        @endforelse
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Watchlist</h5>
        <small class="text-body-secondary"><span class="badge badge-dot bg-success"></span> live</small>
      </div>
      <div class="card-body">
        @forelse($watchlist as $w)
          <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="fw-medium">{{ $w->ticker }}</span>
            <div class="text-end">
              <span class="fw-medium" data-quote-price="{{ $w->ticker }}">—</span>
              <small class="d-block" data-quote-change="{{ $w->ticker }}"></small>
            </div>
          </div>
        @empty
          <span class="text-body-secondary">Empty watchlist.</span>
        @endforelse
        <small class="text-body-secondary">Quotes may be delayed · decision support only.</small>
      </div>
    </div>
  </div>
</div>

<div class="card mt-6">
  <div class="card-header"><h5 class="card-title m-0">Strategy Performance</h5></div>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>Strategy</th><th class="text-end">Trades</th><th class="text-end">Win rate</th><th class="text-end">Net P/L</th></tr></thead>
      <tbody>
        @forelse ($stats['strategy_performance'] as $row)
          <tr>
            <td class="fw-medium">{{ $row['strategy'] }}</td>
            <td class="text-end">{{ $row['trades'] }}</td>
            <td class="text-end">{{ $row['win_rate'] }}%</td>
            <td class="text-end {{ $row['pnl'] >= 0 ? 'text-success' : 'text-danger' }}">${{ number_format($row['pnl'], 2) }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-body-secondary py-4">No closed trades linked to a strategy yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-warning mt-6 mb-0" role="alert">
  <h6 class="alert-heading mb-1"><i class="icon-base ri ri-information-line me-1"></i>Decision support only</h6>
  OptionSignal Pro is not financial advice and does not execute trades. Always verify the live option chain manually before any trade.
</div>
@endsection
