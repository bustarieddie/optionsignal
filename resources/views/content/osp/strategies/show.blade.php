@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Strategy')

@php
  $isSystem = is_null($strategy->user_id);
  $typeLabels = ['buy_call' => 'BUY CALL', 'buy_put' => 'BUY PUT', 'exit' => 'EXIT'];
  $typeColors = ['buy_call' => 'success', 'buy_put' => 'danger', 'exit' => 'warning'];
@endphp

@section('content')
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <h4 class="mb-1">{{ $strategy->name }}
          @if($isSystem)<span class="badge bg-label-info align-middle ms-2">System</span>@endif
        </h4>
        <p class="text-body-secondary mb-2">{{ $strategy->description ?: 'No description.' }}</p>
        <div class="d-flex flex-wrap gap-1">
          @foreach(($strategy->timeframes ?? []) as $tf)
            <span class="badge bg-label-secondary">{{ $tf }}</span>
          @endforeach
        </div>
      </div>
      <div class="text-end">
        <a href="{{ route('strategies.index') }}" class="btn btn-sm btn-label-secondary">Back</a>
        @unless($isSystem)
          <a href="{{ route('strategies.edit', $strategy) }}" class="btn btn-sm btn-primary">Edit</a>
        @endunless
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  @foreach(['buy_call', 'buy_put', 'exit'] as $type)
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="card-title m-0"><span class="badge bg-label-{{ $typeColors[$type] }}">{{ $typeLabels[$type] }}</span></h6>
        </div>
        <ul class="list-group list-group-flush">
          @forelse(($rulesByType[$type] ?? []) as $rule)
            <li class="list-group-item">
              <div class="fw-medium">{{ $rule->condition_key }}</div>
              <small class="text-body-secondary">
                @if($rule->component){{ $rule->component }}@endif
                @if($rule->operator) · {{ $rule->operator }}@endif
                @if(!is_null($rule->value)) {{ $rule->value }}@endif
                @if(!is_null($rule->points)) · {{ $rule->points }} pts@endif
              </small>
            </li>
          @empty
            <li class="list-group-item text-body-secondary">No rules.</li>
          @endforelse
        </ul>
      </div>
    </div>
  @endforeach
</div>
@endsection
