@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Risk Settings')

@section('content')
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<form action="{{ route('risk.update') }}" method="POST">
  @csrf
  @method('PUT')

  <div class="card mb-4">
    <div class="card-header"><h5 class="card-title m-0">Account Limits</h5></div>
    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-4">
          <label class="form-label" for="max_daily_loss">Max daily loss (%)</label>
          <input type="number" step="0.01" min="0" class="form-control" id="max_daily_loss" name="max_daily_loss" value="{{ old('max_daily_loss', $risk->max_daily_loss) }}">
          <small class="text-body-secondary">Stop trading once this % of the account is lost in a day.</small>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="max_trades_per_day">Max trades per day</label>
          <input type="number" min="0" class="form-control" id="max_trades_per_day" name="max_trades_per_day" value="{{ old('max_trades_per_day', $risk->max_trades_per_day) }}">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="max_position_size">Max position size (%)</label>
          <input type="number" step="0.01" min="0" class="form-control" id="max_position_size" name="max_position_size" value="{{ old('max_position_size', $risk->max_position_size) }}">
          <small class="text-body-secondary">Max % of account in a single position.</small>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h5 class="card-title m-0">Per-Trade Risk</h5></div>
    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-4">
          <label class="form-label" for="risk_per_trade_pct">Risk per trade (%)</label>
          <input type="number" step="0.01" min="0" class="form-control" id="risk_per_trade_pct" name="risk_per_trade_pct" value="{{ old('risk_per_trade_pct', $risk->risk_per_trade_pct) }}">
          <small class="text-body-secondary">1–2% recommended.</small>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="stop_loss_pct">Stop loss (%)</label>
          <input type="number" step="0.01" min="0" class="form-control" id="stop_loss_pct" name="stop_loss_pct" value="{{ old('stop_loss_pct', $risk->stop_loss_pct) }}">
          <small class="text-body-secondary">% of option premium (20–30%).</small>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="take_profit_pct">Take profit (%)</label>
          <input type="number" step="0.01" min="0" class="form-control" id="take_profit_pct" name="take_profit_pct" value="{{ old('take_profit_pct', $risk->take_profit_pct) }}">
          <small class="text-body-secondary">% of option premium (30–50%).</small>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h5 class="card-title m-0">Timing Controls</h5></div>
    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-4">
          <label class="form-label" for="cooldown_minutes_after_loss">Cooldown after loss (minutes)</label>
          <input type="number" min="0" class="form-control" id="cooldown_minutes_after_loss" name="cooldown_minutes_after_loss" value="{{ old('cooldown_minutes_after_loss', $risk->cooldown_minutes_after_loss) }}">
          <small class="text-body-secondary">Pause new entries after a losing trade.</small>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="no_trade_window_start">No-trade window start</label>
          <input type="time" class="form-control" id="no_trade_window_start" name="no_trade_window_start" value="{{ old('no_trade_window_start', $risk->no_trade_window_start) }}">
          <small class="text-body-secondary">e.g. 09:30 (market open volatility).</small>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="no_trade_window_end">No-trade window end</label>
          <input type="time" class="form-control" id="no_trade_window_end" name="no_trade_window_end" value="{{ old('no_trade_window_end', $risk->no_trade_window_end) }}">
          <small class="text-body-secondary">e.g. 09:45.</small>
        </div>
      </div>
    </div>
  </div>

  <div class="mb-4">
    <button type="submit" class="btn btn-primary">Save risk settings</button>
  </div>
</form>

<div class="alert alert-warning mb-0" role="alert">
  <h6 class="alert-heading mb-1"><i class="icon-base ri ri-information-line me-1"></i>Decision support only</h6>
  These settings are guardrails for your own discipline. OptionSignal Pro does not execute trades and this is not financial advice.
</div>
@endsection
