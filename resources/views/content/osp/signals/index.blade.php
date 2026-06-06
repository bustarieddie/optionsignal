@php
$configData = Helper::appClasses();
$gradeColors = ['A+' => 'success', 'A' => 'primary', 'B' => 'info', 'C' => 'warning', 'ignore' => 'secondary'];
$sideBadge = fn ($s) => $s === 'buy_call' ? 'bg-label-success' : ($s === 'buy_put' ? 'bg-label-danger' : 'bg-label-warning');
@endphp
@extends('layouts/layoutMaster')

@section('title', 'Signals')

@section('page-script')
@vite(['resources/assets/js/osp-signals-live.js'])
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="m-0">Signals</h4>
  <small class="text-body-secondary"><span class="badge badge-dot bg-success"></span> live</small>
</div>

<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="ticker">Ticker</label>
        <select class="form-select" id="ticker" name="ticker">
          <option value="">All</option>
          @foreach ($tickers as $t)
            <option value="{{ $t }}" {{ request('ticker') === $t ? 'selected' : '' }}>{{ $t }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="grade">Grade</label>
        <select class="form-select" id="grade" name="grade">
          <option value="">All</option>
          @foreach (['A+','A','B','C','ignore'] as $g)
            <option value="{{ $g }}" {{ request('grade') === $g ? 'selected' : '' }}>{{ $g }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="type">Side</label>
        <select class="form-select" id="type" name="type">
          <option value="">All</option>
          <option value="buy_call" {{ request('type') === 'buy_call' ? 'selected' : '' }}>CALL</option>
          <option value="buy_put" {{ request('type') === 'buy_put' ? 'selected' : '' }}>PUT</option>
          <option value="exit" {{ request('type') === 'exit' ? 'selected' : '' }}>EXIT</option>
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100">Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover">
      <thead>
        <tr><th>Ticker</th><th>Side</th><th>TF</th><th>Grade</th><th>Score</th><th>Price</th><th>Strategy</th><th>Signal time</th><th></th></tr>
      </thead>
      <tbody id="osp-signals-body">
        @forelse ($signals as $signal)
          <tr>
            <td class="fw-medium">{{ $signal->ticker }}</td>
            <td>
              <span class="badge {{ $sideBadge($signal->signal_type) }}">{{ strtoupper(str_replace('buy_', '', $signal->signal_type)) }}</span>
              @php($rs = $signal->rsBadge())
              @if ($rs)<span class="badge {{ $rs[0] }}" title="{{ $rs[2] }}">{{ $rs[1] }}</span>@endif
            </td>
            <td>{{ $signal->timeframe }}</td>
            <td><span class="badge bg-label-{{ $gradeColors[$signal->grade] ?? 'secondary' }}">{{ $signal->grade }}</span></td>
            <td>{{ $signal->total_score }}</td>
            <td>${{ number_format((float) $signal->price, 2) }}</td>
            <td><small>{{ $signal->strategy?->name ?? '—' }}</small></td>
            <td>
              <span class="d-block text-nowrap">{{ optional($signal->occurred_at)->format('M j, Y H:i:s') }}</span>
              <small class="text-body-secondary">{{ optional($signal->occurred_at)->diffForHumans() }}</small>
            </td>
            <td><a href="{{ route('signals.show', $signal) }}" class="btn btn-sm btn-text-primary">View</a></td>
          </tr>
        @empty
          <tr data-empty-row><td colspan="9" class="text-center text-body-secondary py-4">No signals yet — new ones appear here live.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-body">
    {{ $signals->links() }}
  </div>
</div>
@endsection
