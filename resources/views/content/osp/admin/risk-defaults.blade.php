@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Admin · Risk Defaults')

@php
  $labels = [
    'max_daily_loss'              => ['Max daily loss', '%'],
    'max_trades_per_day'          => ['Max trades per day', ''],
    'risk_per_trade_pct'          => ['Risk per trade', '%'],
    'max_position_size'           => ['Max position size', '%'],
    'stop_loss_pct'               => ['Stop loss', '% of premium'],
    'take_profit_pct'             => ['Take profit', '% of premium'],
    'cooldown_minutes_after_loss' => ['Cooldown after loss', 'min'],
    'no_trade_window_start'       => ['No-trade window start', ''],
    'no_trade_window_end'         => ['No-trade window end', ''],
  ];
@endphp

@section('content')
<h5 class="mb-4">Default Risk Settings</h5>

<div class="alert alert-info" role="alert">
  <i class="icon-base ri ri-information-line me-1"></i>
  These read-only defaults (from <code>config/risk.defaults</code>) seed each new user's risk settings on registration.
  Editing per-user settings happens in the user's own Risk Management page.
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr><th>Setting</th><th>Default</th><th>Key</th></tr>
      </thead>
      <tbody>
        @forelse($defaults as $key => $value)
          @php [$label, $unit] = $labels[$key] ?? [\Illuminate\Support\Str::headline($key), '']; @endphp
          <tr>
            <td class="fw-medium">{{ $label }}</td>
            <td>
              @if($value === null)
                <span class="text-body-secondary">Not set</span>
              @else
                {{ $value }}@if($unit) <small class="text-body-secondary">{{ $unit }}</small>@endif
              @endif
            </td>
            <td><code class="small">{{ $key }}</code></td>
          </tr>
        @empty
          <tr><td colspan="3" class="text-center text-body-secondary py-4">No defaults configured.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-warning mt-4 mb-0" role="alert">
  <h6 class="alert-heading mb-1"><i class="icon-base ri ri-shield-check-line me-1"></i>Decision support only</h6>
  These are conservative risk-management defaults, not financial advice.
</div>
@endsection
