@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Add Ticker')

@section('content')
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card">
  <div class="card-header"><h5 class="card-title m-0">Add Ticker</h5></div>
  <div class="card-body">
    <form action="{{ route('watchlist.store') }}" method="POST">
      @csrf
      <div class="row g-4">
        <div class="col-md-4">
          <label class="form-label" for="ticker">Ticker <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="ticker" name="ticker" value="{{ old('ticker') }}" maxlength="10" placeholder="AAPL" required>
        </div>
        <div class="col-md-8">
          <label class="form-label" for="company">Company</label>
          <input type="text" class="form-control" id="company" name="company" value="{{ old('company') }}" placeholder="Apple Inc.">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="sector">Sector</label>
          <input type="text" class="form-control" id="sector" name="sector" value="{{ old('sector') }}" placeholder="Technology">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="preferred_timeframe">Preferred Timeframe</label>
          <select class="form-select" id="preferred_timeframe" name="preferred_timeframe">
            <option value="">— None —</option>
            @foreach(['3m', '5m', '15m', '1h'] as $tf)
              <option value="{{ $tf }}" @selected(old('preferred_timeframe') === $tf)>{{ $tf }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12">
          <label class="form-label" for="notes">Notes</label>
          <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
        </div>
        <div class="col-md-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="optionable" name="optionable" value="1" @checked(old('optionable', true))>
            <label class="form-check-label" for="optionable">Optionable</label>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="active" name="active" value="1" @checked(old('active', true))>
            <label class="form-check-label" for="active">Active</label>
          </div>
        </div>
      </div>
      <div class="mt-4">
        <button type="submit" class="btn btn-primary">Add ticker</button>
        <a href="{{ route('watchlist.index') }}" class="btn btn-label-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
