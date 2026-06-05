@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Admin · Signal Performance')

@php
  $gradeColors = ['A+' => 'success', 'A' => 'primary', 'B' => 'info', 'C' => 'warning', 'ignore' => 'secondary'];
@endphp

@section('content')
<h5 class="mb-4">Platform Signal Performance</h5>

<div class="row g-4 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card"><div class="card-body">
      <p class="mb-1">Total Signals</p>
      <h4 class="mb-0">{{ number_format($stats['total_signals']) }}</h4>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card"><div class="card-body">
      <p class="mb-1">Actionable</p>
      <h4 class="mb-0">{{ number_format($stats['actionable']) }}</h4>
      <small class="text-body-secondary">non-ignored signals</small>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card"><div class="card-body">
      <p class="mb-1">Actionable Rate</p>
      <h4 class="mb-0">{{ $stats['actionable_rate'] }}%</h4>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card"><div class="card-body">
      <p class="mb-1">Distinct Tickers</p>
      <h4 class="mb-0">{{ number_format($stats['distinct_tickers']) }}</h4>
    </div></div>
  </div>
</div>

<div class="row g-4">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header"><h6 class="card-title m-0">Signals by Grade</h6></div>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead><tr><th>Grade</th><th class="text-end">Count</th><th class="text-end">Share</th></tr></thead>
          <tbody>
            @forelse($byGrade as $grade => $count)
              <tr>
                <td><span class="badge bg-label-{{ $gradeColors[$grade] ?? 'secondary' }}">{{ $grade ?? '—' }}</span></td>
                <td class="text-end">{{ number_format($count) }}</td>
                <td class="text-end"><small class="text-body-secondary">{{ $stats['total_signals'] > 0 ? round($count / $stats['total_signals'] * 100, 1) : 0 }}%</small></td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center text-body-secondary py-4">No signals yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header"><h6 class="card-title m-0">Top Tickers</h6></div>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead><tr><th>Ticker</th><th class="text-end">Signals</th></tr></thead>
          <tbody>
            @forelse($byTicker as $ticker => $count)
              <tr>
                <td class="fw-medium">{{ $ticker }}</td>
                <td class="text-end">{{ number_format($count) }}</td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-center text-body-secondary py-4">No signals yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
