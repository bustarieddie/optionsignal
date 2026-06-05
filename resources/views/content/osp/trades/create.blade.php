@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Log Trade')

@section('content')
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0">Log Trade</h5>
  <a href="{{ route('trades.index') }}" class="btn btn-outline-secondary">Back</a>
</div>

@isset($signal)
  @if ($signal)
    <div class="alert alert-info" role="alert">
      <h6 class="alert-heading mb-0">Pre-filled from signal #{{ $signal->id }} — {{ $signal->ticker }}
        {{ strtoupper(str_replace('buy_', '', $signal->signal_type)) }} (Grade {{ $signal->grade }})</h6>
    </div>
  @endif
@endisset

<form action="{{ route('trades.store') }}" method="POST" enctype="multipart/form-data">
  @csrf
  <div class="card mb-4">
    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-4">
          <label class="form-label">Ticker</label>
          <input type="text" name="ticker" class="form-control" maxlength="10" value="{{ old('ticker', $trade->ticker) }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Direction</label>
          <select name="direction" class="form-select" required>
            <option value="call" @selected(old('direction', $trade->direction) === 'call')>Call</option>
            <option value="put" @selected(old('direction', $trade->direction) === 'put')>Put</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Quantity</label>
          <input type="number" name="quantity" class="form-control" min="1" value="{{ old('quantity', $trade->quantity) }}" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Entry Price</label>
          <input type="number" step="0.0001" name="entry_price" class="form-control" value="{{ old('entry_price', $trade->entry_price) }}" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Exit Price <small class="text-body-secondary">(optional)</small></label>
          <input type="number" step="0.0001" name="exit_price" class="form-control" value="{{ old('exit_price') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">Signal Grade</label>
          <select name="signal_grade" class="form-select">
            <option value="">—</option>
            @foreach(['A+','A','B','C'] as $g)
              <option value="{{ $g }}" @selected(old('signal_grade', $trade->signal_grade) === $g)>{{ $g }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Setup Name</label>
          <input type="text" name="setup_name" class="form-control" value="{{ old('setup_name', $trade->setup_name) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">Contract Details</label>
          <input type="text" name="contract_details" class="form-control" placeholder="e.g. AAPL 250C 06/20" value="{{ old('contract_details') }}">
        </div>

        <div class="col-md-6">
          <label class="form-label">Opened At</label>
          <input type="datetime-local" name="opened_at" class="form-control" value="{{ old('opened_at', optional($trade->opened_at)->format('Y-m-d\TH:i')) }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Closed At <small class="text-body-secondary">(set with exit to close)</small></label>
          <input type="datetime-local" name="closed_at" class="form-control" value="{{ old('closed_at') }}">
        </div>

        <div class="col-12">
          <label class="form-label">Linked Signal ID <small class="text-body-secondary">(optional)</small></label>
          <input type="number" name="signal_id" class="form-control" value="{{ old('signal_id', $trade->signal_id) }}">
        </div>

        <div class="col-12">
          <label class="form-label">Reason for Entry</label>
          <textarea name="reason_for_entry" class="form-control" rows="3">{{ old('reason_for_entry', $trade->reason_for_entry) }}</textarea>
        </div>

        <div class="col-md-8">
          <label class="form-label">Screenshot <small class="text-body-secondary">(jpg/png/webp, max 5MB)</small></label>
          <input type="file" name="screenshot" class="form-control" accept="image/*">
        </div>
        <div class="col-md-4">
          <label class="form-label">Caption</label>
          <input type="text" name="caption" class="form-control" value="{{ old('caption') }}">
        </div>
      </div>
    </div>
    <div class="card-footer">
      <button type="submit" class="btn btn-primary">Save trade</button>
    </div>
  </div>
</form>
@endsection
