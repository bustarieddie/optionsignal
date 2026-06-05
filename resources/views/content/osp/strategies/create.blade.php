@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'New Strategy')

@php
  $ruleTypes = ['buy_call' => 'BUY CALL', 'buy_put' => 'BUY PUT', 'exit' => 'EXIT'];
  $components = ['ema_crossover', 'rsi', 'vwap', 'volume', 'htf', 'sr'];
  $oldRules = old('rules', []);
  $rowCount = max(5, count($oldRules));
@endphp

@section('content')
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<form action="{{ route('strategies.store') }}" method="POST">
  @csrf
  <div class="card mb-4">
    <div class="card-header"><h5 class="card-title m-0">New Strategy</h5></div>
    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-8">
          <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="active" name="active" value="1" @checked(old('active', true))>
            <label class="form-check-label" for="active">Active</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label" for="description">Description</label>
          <textarea class="form-control" id="description" name="description" rows="2">{{ old('description') }}</textarea>
        </div>
        <div class="col-12">
          <label class="form-label d-block">Timeframes</label>
          @foreach(['3m', '5m', '15m', '1h'] as $tf)
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" id="tf_{{ $tf }}" name="timeframes[]" value="{{ $tf }}"
                     @checked(in_array($tf, old('timeframes', [])))>
              <label class="form-check-label" for="tf_{{ $tf }}">{{ $tf }}</label>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h5 class="card-title m-0">Rules</h5>
      <small class="text-body-secondary">Add one row per condition. Leave a row blank to skip it.</small>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr><th style="width:18%">Rule type</th><th style="width:18%">Component</th><th>Condition key</th><th style="width:12%">Operator</th><th style="width:12%">Value</th><th style="width:10%">Points</th></tr>
        </thead>
        <tbody>
          @for($i = 0; $i < $rowCount; $i++)
            <tr>
              <td>
                <select class="form-select form-select-sm" name="rules[{{ $i }}][rule_type]">
                  <option value="">—</option>
                  @foreach($ruleTypes as $val => $label)
                    <option value="{{ $val }}" @selected(($oldRules[$i]['rule_type'] ?? '') === $val)>{{ $label }}</option>
                  @endforeach
                </select>
              </td>
              <td>
                <select class="form-select form-select-sm" name="rules[{{ $i }}][component]">
                  <option value="">—</option>
                  @foreach($components as $c)
                    <option value="{{ $c }}" @selected(($oldRules[$i]['component'] ?? '') === $c)>{{ $c }}</option>
                  @endforeach
                </select>
              </td>
              <td><input type="text" class="form-control form-control-sm" name="rules[{{ $i }}][condition_key]" value="{{ $oldRules[$i]['condition_key'] ?? '' }}" placeholder="e.g. ema9_above_ema21"></td>
              <td><input type="text" class="form-control form-control-sm" name="rules[{{ $i }}][operator]" value="{{ $oldRules[$i]['operator'] ?? '' }}"></td>
              <td><input type="text" class="form-control form-control-sm" name="rules[{{ $i }}][value]" value="{{ $oldRules[$i]['value'] ?? '' }}"></td>
              <td><input type="number" class="form-control form-control-sm" name="rules[{{ $i }}][points]" value="{{ $oldRules[$i]['points'] ?? '' }}"></td>
            </tr>
          @endfor
        </tbody>
      </table>
    </div>
  </div>

  <div class="mb-6">
    <button type="submit" class="btn btn-primary">Create strategy</button>
    <a href="{{ route('strategies.index') }}" class="btn btn-label-secondary">Cancel</a>
  </div>
</form>
@endsection
