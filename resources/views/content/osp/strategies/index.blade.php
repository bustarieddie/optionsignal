@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Strategies')

@section('content')
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0">Strategies</h5>
  <a href="{{ route('strategies.create') }}" class="btn btn-primary">
    <i class="icon-base ri ri-add-line me-1"></i>New strategy
  </a>
</div>

<div class="row g-4">
  @forelse($strategies as $strategy)
    @php $isSystem = is_null($strategy->user_id); @endphp
    <div class="col-md-6 col-xl-4">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h5 class="card-title mb-0">{{ $strategy->name }}</h5>
            @if($isSystem)
              <span class="badge bg-label-info">System</span>
            @else
              <span class="badge bg-label-{{ $strategy->active ? 'success' : 'secondary' }}">{{ $strategy->active ? 'Active' : 'Inactive' }}</span>
            @endif
          </div>
          <p class="text-body-secondary">{{ $strategy->description ?: 'No description.' }}</p>
          <div class="d-flex flex-wrap gap-1 mb-3">
            @forelse(($strategy->timeframes ?? []) as $tf)
              <span class="badge bg-label-secondary">{{ $tf }}</span>
            @empty
              <span class="text-body-secondary small">No timeframes</span>
            @endforelse
          </div>
        </div>
        <div class="card-footer d-flex gap-2">
          <a href="{{ route('strategies.show', $strategy) }}" class="btn btn-sm btn-outline-primary">View</a>
          @unless($isSystem)
            <a href="{{ route('strategies.edit', $strategy) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
            <form action="{{ route('strategies.destroy', $strategy) }}" method="POST" class="ms-auto"
                  onsubmit="return confirm('Delete strategy {{ $strategy->name }}?');">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
          @endunless
        </div>
      </div>
    </div>
  @empty
    <div class="col-12">
      <div class="card"><div class="card-body text-center text-body-secondary py-5">No strategies yet.</div></div>
    </div>
  @endforelse
</div>
@endsection
