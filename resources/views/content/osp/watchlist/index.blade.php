@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Watchlist')

@section('content')
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title m-0">Watchlist</h5>
    <a href="{{ route('watchlist.create') }}" class="btn btn-primary">
      <i class="icon-base ri ri-add-line me-1"></i>Add ticker
    </a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Ticker</th>
          <th>Company</th>
          <th>Sector</th>
          <th>Optionable</th>
          <th>Pref. TF</th>
          <th>Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($watchlists as $w)
          <tr>
            <td class="fw-medium">{{ $w->ticker }}</td>
            <td>{{ $w->company ?: '—' }}</td>
            <td>{{ $w->sector ?: '—' }}</td>
            <td>
              @if($w->optionable)
                <span class="badge bg-label-success">Yes</span>
              @else
                <span class="badge bg-label-secondary">No</span>
              @endif
            </td>
            <td>{{ $w->preferred_timeframe ?: '—' }}</td>
            <td>
              @if($w->active)
                <span class="badge bg-label-primary">Active</span>
              @else
                <span class="badge bg-label-secondary">Inactive</span>
              @endif
            </td>
            <td class="text-end">
              <a href="{{ route('watchlist.edit', $w) }}" class="btn btn-sm btn-icon btn-text-secondary" title="Edit">
                <i class="icon-base ri ri-pencil-line"></i>
              </a>
              <form action="{{ route('watchlist.destroy', $w) }}" method="POST" class="d-inline"
                    onsubmit="return confirm('Remove {{ $w->ticker }} from your watchlist?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-icon btn-text-secondary text-danger" title="Delete">
                  <i class="icon-base ri ri-delete-bin-line"></i>
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-body-secondary py-4">Your watchlist is empty. Add a ticker to get started.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
