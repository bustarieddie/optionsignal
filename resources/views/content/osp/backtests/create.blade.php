@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Import Backtest')

@section('content')
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0">Import Backtest CSV</h5>
  <a href="{{ route('backtests.index') }}" class="btn btn-outline-secondary">Back</a>
</div>

<div class="row">
  <div class="col-lg-8">
    <form action="{{ route('backtests.store') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <div class="card mb-4">
        <div class="card-body">
          <div class="mb-4">
            <label class="form-label">Backtest Name</label>
            <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. AAPL 5m EMA cross — Q1" required>
          </div>
          <div class="mb-2">
            <label class="form-label">CSV File <small class="text-body-secondary">(.csv / .txt, max 10MB)</small></label>
            <input type="file" name="file" class="form-control" accept=".csv,text/csv,text/plain" required>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-primary">Upload &amp; process</button>
        </div>
      </div>
    </form>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><h6 class="m-0">Supported formats</h6></div>
      <div class="card-body">
        <p class="mb-2"><strong>TradingView "List of Trades"</strong></p>
        <p class="text-body-secondary small">Export from a strategy's Strategy Tester → "List of Trades" tab. Entry and Exit appear as separate rows paired by a <code>Trade #</code> column; they are folded into one trade automatically.</p>
        <hr>
        <p class="mb-2"><strong>Generic CSV</strong></p>
        <p class="text-body-secondary small mb-0">One row per trade with headers like <code>ticker, direction, entry, exit, pnl</code>. Optional: <code>timeframe, grade, setup, occurred_at</code>.</p>
      </div>
    </div>
  </div>
</div>
@endsection
