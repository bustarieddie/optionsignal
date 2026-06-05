@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Edit Strategy')

@php
  $ruleTypes = ['buy_call' => 'BUY CALL', 'buy_put' => 'BUY PUT', 'exit' => 'EXIT'];
  $components = ['ema_crossover', 'rsi', 'vwap', 'volume', 'htf', 'sr'];
  $existing = old('rules', $strategy->rules->map(fn ($r) => [
      'rule_type' => $r->rule_type,
      'component' => $r->component,
      'condition_key' => $r->condition_key,
      'operator' => $r->operator,
      'value' => $r->value,
      'points' => $r->points,
  ])->values()->all());
  $rowCount = max(count($existing) + 3, 5);
  $selectedTfs = old('timeframes', $strategy->timeframes ?? []);
@endphp

@section('content')
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<form action="{{ route('strategies.update', $strategy) }}" method="POST">
  @csrf
  @method('PUT')
  <div class="card mb-4">
    <div class="card-header"><h5 class="card-title m-0">Edit {{ $strategy->name }}</h5></div>
    <div class="card-body">
      <div class="row g-4">
        <div class="col-md-8">
          <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $strategy->name) }}" required>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="active" name="active" value="1" @checked(old('active', $strategy->active))>
            <label class="form-check-label" for="active">Active</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label" for="description">Description</label>
          <textarea class="form-control" id="description" name="description" rows="2">{{ old('description', $strategy->description) }}</textarea>
        </div>
        <div class="col-12">
          <label class="form-label d-block">Timeframes</label>
          @foreach(['3m', '5m', '15m', '1h'] as $tf)
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" id="tf_{{ $tf }}" name="timeframes[]" value="{{ $tf }}"
                     @checked(in_array($tf, $selectedTfs))>
              <label class="form-check-label" for="tf_{{ $tf }}">{{ $tf }}</label>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h5 class="card-title m-0">Rules</h5>
      <small class="text-body-secondary">Saving replaces all existing rules. Leave a row blank to skip it.</small>
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
                    <option value="{{ $val }}" @selected(($existing[$i]['rule_type'] ?? '') === $val)>{{ $label }}</option>
                  @endforeach
                </select>
              </td>
              <td>
                <select class="form-select form-select-sm" name="rules[{{ $i }}][component]">
                  <option value="">—</option>
                  @foreach($components as $c)
                    <option value="{{ $c }}" @selected(($existing[$i]['component'] ?? '') === $c)>{{ $c }}</option>
                  @endforeach
                </select>
              </td>
              <td><input type="text" class="form-control form-control-sm" name="rules[{{ $i }}][condition_key]" value="{{ $existing[$i]['condition_key'] ?? '' }}"></td>
              <td><input type="text" class="form-control form-control-sm" name="rules[{{ $i }}][operator]" value="{{ $existing[$i]['operator'] ?? '' }}"></td>
              <td><input type="text" class="form-control form-control-sm" name="rules[{{ $i }}][value]" value="{{ $existing[$i]['value'] ?? '' }}"></td>
              <td><input type="number" class="form-control form-control-sm" name="rules[{{ $i }}][points]" value="{{ $existing[$i]['points'] ?? '' }}"></td>
            </tr>
          @endfor
        </tbody>
      </table>
    </div>
  </div>

  <div class="mb-6">
    <button type="submit" class="btn btn-primary">Save changes</button>
    <a href="{{ route('strategies.show', $strategy) }}" class="btn btn-label-secondary">Cancel</a>
  </div>
</form>
@endsection
