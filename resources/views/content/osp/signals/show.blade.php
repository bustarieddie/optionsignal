@php
$configData = Helper::appClasses();
$gradeColors = ['A+' => 'success', 'A' => 'primary', 'B' => 'info', 'C' => 'warning', 'ignore' => 'secondary'];
$componentLabels = [
  'ema_crossover' => 'EMA 9/21 crossover', 'rsi' => 'RSI vs RSI MA', 'vwap' => 'VWAP alignment',
  'volume' => 'Volume confirmation', 'htf' => 'Higher-timeframe trend', 'sr' => 'Clean support/resistance',
];
$tvInterval = in_array($signal->timeframe, ['3m', '5m', '15m'], true) ? str_replace('m', '', $signal->timeframe) : '60';
@endphp
@extends('layouts/layoutMaster')

@section('title', $signal->ticker . ' signal')

@section('page-script')
<script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof TradingView === 'undefined') return;
    new TradingView.widget({
      container_id: 'tradingview-osp-chart',
      symbol: @json($signal->ticker),
      interval: @json($tvInterval),
      autosize: true,
      theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light',
      style: '1',
      timezone: 'America/New_York',
      hide_side_toolbar: true,
      allow_symbol_change: true,
    });
  });
</script>
@endsection

@section('content')
@if ($signal->signal_type !== 'exit')
  @can('manage trades')
    <div class="mb-6">
      <a href="{{ route('trades.create', ['signal' => $signal->id]) }}" class="btn btn-primary">
        <i class="icon-base ri ri-booklet-line me-1"></i> Log Trade from this signal
      </a>
    </div>
  @endcan
@endif

<div class="card mb-6">
  <div class="card-header"><h5 class="card-title m-0">{{ $signal->ticker }} Chart</h5></div>
  <div class="card-body">
    <div class="tradingview-widget-container">
      <div id="tradingview-osp-chart" style="height:420px"></div>
    </div>
  </div>
</div>

<div class="row g-6">
  <div class="col-lg-7">
    <div class="card mb-6">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="m-0">{{ $signal->ticker }}
          <span class="badge {{ $signal->signal_type === 'buy_call' ? 'bg-label-success' : ($signal->signal_type === 'buy_put' ? 'bg-label-danger' : 'bg-label-warning') }}">
            {{ strtoupper(str_replace('buy_', '', $signal->signal_type)) }}
          </span>
        </h5>
        <span class="badge bg-label-{{ $gradeColors[$signal->grade] ?? 'secondary' }} fs-6">Grade {{ $signal->grade }} · {{ $signal->total_score }} pts</span>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4">Timeframe</dt><dd class="col-sm-8">{{ $signal->timeframe }}</dd>
          <dt class="col-sm-4">Price</dt><dd class="col-sm-8">${{ number_format((float) $signal->price, 2) }}</dd>
          <dt class="col-sm-4">EMA 9 / 21</dt><dd class="col-sm-8">{{ $signal->ema9 }} / {{ $signal->ema21 }}</dd>
          <dt class="col-sm-4">RSI / RSI MA</dt><dd class="col-sm-8">{{ $signal->rsi }} / {{ $signal->rsi_ma }}</dd>
          <dt class="col-sm-4">VWAP</dt><dd class="col-sm-8">{{ $signal->vwap }}</dd>
          <dt class="col-sm-4">Volume</dt><dd class="col-sm-8">{{ $signal->volume_status }}</dd>
          @php($rs = $signal->rsBadge())
          @if ($rs)
            <dt class="col-sm-4">Relative strength</dt>
            <dd class="col-sm-8"><span class="badge {{ $rs[0] }}">{{ $rs[2] }}</span></dd>
          @endif
          <dt class="col-sm-4">Strategy</dt><dd class="col-sm-8">{{ $signal->strategy?->name ?? '—' }}</dd>
          <dt class="col-sm-4">Occurred</dt><dd class="col-sm-8">{{ optional($signal->occurred_at)->toDayDateTimeString() }}</dd>
        </dl>
      </div>
    </div>

    @if ($signal->optionSuggestion)
      <div class="card">
        <div class="card-header"><h5 class="m-0">Option Contract Suggestion</h5></div>
        <div class="card-body">
          <p class="mb-2"><span class="badge bg-label-primary">{{ strtoupper($signal->optionSuggestion->contract_type) }}</span>
            Delta {{ $signal->optionSuggestion->suggested_delta_min }}–{{ $signal->optionSuggestion->suggested_delta_max }}</p>
          <p class="mb-1"><strong>Expiry:</strong> {{ $signal->optionSuggestion->suggested_expiry }}</p>
          <p class="mb-1"><strong>Liquidity:</strong> {{ $signal->optionSuggestion->liquidity_note }}</p>
          <p class="mb-0 text-warning"><strong>Risk:</strong> {{ $signal->optionSuggestion->risk_note }}</p>
        </div>
      </div>
    @endif
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><h5 class="m-0">Confidence Breakdown</h5></div>
      <div class="card-body">
        @forelse ($signal->scores as $score)
          <div class="d-flex justify-content-between align-items-center mb-3">
            <span>{{ $componentLabels[$score->component] ?? $score->component }}</span>
            <span class="badge {{ $score->points > 0 ? 'bg-label-success' : 'bg-label-secondary' }}">+{{ $score->points }}</span>
          </div>
        @empty
          <p class="text-body-secondary mb-0">No score breakdown.</p>
        @endforelse
        <hr>
        <div class="d-flex justify-content-between fw-medium">
          <span>Total</span><span>{{ $signal->total_score }} / 100</span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="alert alert-warning mt-6 mb-0">
  <i class="icon-base ri ri-information-line me-1"></i>
  Decision support only — verify the live option chain manually. Not financial advice.
</div>
@endsection
