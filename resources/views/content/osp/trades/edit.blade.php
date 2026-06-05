@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Edit Trade')

@section('content')
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0">Edit Trade · {{ $trade->ticker }}</h5>
  <a href="{{ route('trades.show', $trade) }}" class="btn btn-outline-secondary">Back</a>
</div>

<form action="{{ route('trades.update', $trade) }}" method="POST" enctype="multipart/form-data">
  @csrf
  @method('PUT')
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
          <label class="form-label">Exit Price</label>
          <input type="number" step="0.0001" name="exit_price" class="form-control" value="{{ old('exit_price', $trade->exit_price) }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="status" class="form-select" required>
            @foreach(['open','closed','cancelled'] as $s)
              <option value="{{ $s }}" @selected(old('status', $trade->status) === $s)>{{ ucfirst($s) }}</option>
            @endforeach
          </select>
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
        <div class="col-md-4">
          <label class="form-label">P/L Override <small class="text-body-secondary">(blank = auto)</small></label>
          <input type="number" step="0.01" name="pnl" class="form-control" value="{{ old('pnl') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label">Emotion Score (1-10)</label>
          <input type="number" name="emotion_score" class="form-control" min="1" max="10" value="{{ old('emotion_score', $trade->emotion_score) }}">
        </div>

        <div class="col-md-6">
          <label class="form-label">Setup Name</label>
          <input type="text" name="setup_name" class="form-control" value="{{ old('setup_name', $trade->setup_name) }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">Contract Details</label>
          <input type="text" name="contract_details" class="form-control" value="{{ old('contract_details', $trade->contract_details) }}">
        </div>

        <div class="col-md-6">
          <label class="form-label">Opened At</label>
          <input type="datetime-local" name="opened_at" class="form-control" value="{{ old('opened_at', optional($trade->opened_at)->format('Y-m-d\TH:i')) }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Closed At</label>
          <input type="datetime-local" name="closed_at" class="form-control" value="{{ old('closed_at', optional($trade->closed_at)->format('Y-m-d\TH:i')) }}">
        </div>

        <div class="col-12">
          <label class="form-label">Reason for Entry</label>
          <textarea name="reason_for_entry" class="form-control" rows="2">{{ old('reason_for_entry', $trade->reason_for_entry) }}</textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Reason for Exit</label>
          <textarea name="reason_for_exit" class="form-control" rows="2">{{ old('reason_for_exit', $trade->reason_for_exit) }}</textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Mistake Notes</label>
          <textarea name="mistake_notes" class="form-control" rows="2">{{ old('mistake_notes', $trade->mistake_notes) }}</textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Lessons</label>
          <textarea name="lessons" class="form-control" rows="2">{{ old('lessons', $trade->lessons) }}</textarea>
        </div>

        <div class="col-md-8">
          <label class="form-label">Add Screenshot <small class="text-body-secondary">(jpg/png/webp, max 5MB)</small></label>
          <input type="file" name="screenshot" class="form-control" accept="image/*">
        </div>
        <div class="col-md-4">
          <label class="form-label">Caption</label>
          <input type="text" name="caption" class="form-control" value="{{ old('caption') }}">
        </div>
      </div>
    </div>
    <div class="card-footer">
      <button type="submit" class="btn btn-primary">Update trade</button>
    </div>
  </div>
</form>
@endsection
