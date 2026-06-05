@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Trade · ' . $trade->ticker)

@php
  $gradeColors = ['A+' => 'success', 'A' => 'primary', 'B' => 'info', 'C' => 'warning'];
  $statusColors = ['open' => 'warning', 'closed' => 'secondary', 'cancelled' => 'danger'];
@endphp

@section('content')
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0">
    {{ $trade->ticker }}
    <span class="badge {{ $trade->direction === 'call' ? 'bg-label-success' : 'bg-label-danger' }} ms-2">{{ strtoupper($trade->direction) }}</span>
    <span class="badge bg-label-{{ $statusColors[$trade->status] ?? 'secondary' }} ms-1">{{ ucfirst($trade->status) }}</span>
  </h5>
  <div class="d-flex gap-2">
    <a href="{{ route('trades.edit', $trade) }}" class="btn btn-outline-primary">Edit</a>
    <a href="{{ route('trades.index') }}" class="btn btn-outline-secondary">Back</a>
    <form action="{{ route('trades.destroy', $trade) }}" method="POST" onsubmit="return confirm('Delete this trade?');">
      @csrf @method('DELETE')
      <button type="submit" class="btn btn-outline-danger">Delete</button>
    </form>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card mb-4">
      <div class="card-header"><h6 class="m-0">Trade Detail</h6></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3"><small class="text-body-secondary d-block">Entry</small>${{ number_format((float) $trade->entry_price, 2) }}</div>
          <div class="col-md-3"><small class="text-body-secondary d-block">Exit</small>{{ $trade->exit_price !== null ? '$'.number_format((float) $trade->exit_price, 2) : '—' }}</div>
          <div class="col-md-3"><small class="text-body-secondary d-block">Quantity</small>{{ $trade->quantity }}</div>
          <div class="col-md-3"><small class="text-body-secondary d-block">P/L</small>
            @if($trade->pnl !== null)<span class="{{ (float) $trade->pnl >= 0 ? 'text-success' : 'text-danger' }}">${{ number_format((float) $trade->pnl, 2) }}</span>@else — @endif
          </div>
          <div class="col-md-3"><small class="text-body-secondary d-block">Grade</small>@if($trade->signal_grade)<span class="badge bg-label-{{ $gradeColors[$trade->signal_grade] ?? 'secondary' }}">{{ $trade->signal_grade }}</span>@else — @endif</div>
          <div class="col-md-3"><small class="text-body-secondary d-block">Setup</small>{{ $trade->setup_name ?: '—' }}</div>
          <div class="col-md-3"><small class="text-body-secondary d-block">Emotion</small>{{ $trade->emotion_score ? $trade->emotion_score.'/10' : '—' }}</div>
          <div class="col-md-3"><small class="text-body-secondary d-block">Linked Signal</small>{{ $trade->signal_id ?? '—' }}</div>
          <div class="col-md-6"><small class="text-body-secondary d-block">Opened</small>{{ optional($trade->opened_at)->format('Y-m-d H:i') }}</div>
          <div class="col-md-6"><small class="text-body-secondary d-block">Closed</small>{{ optional($trade->closed_at)->format('Y-m-d H:i') ?: '—' }}</div>
          <div class="col-12"><small class="text-body-secondary d-block">Contract</small>{{ $trade->contract_details ?: '—' }}</div>
        </div>
        <hr>
        <div class="row g-3">
          <div class="col-md-6"><small class="text-body-secondary d-block">Reason for Entry</small>{{ $trade->reason_for_entry ?: '—' }}</div>
          <div class="col-md-6"><small class="text-body-secondary d-block">Reason for Exit</small>{{ $trade->reason_for_exit ?: '—' }}</div>
          <div class="col-md-6"><small class="text-body-secondary d-block">Mistake Notes</small>{{ $trade->mistake_notes ?: '—' }}</div>
          <div class="col-md-6"><small class="text-body-secondary d-block">Lessons</small>{{ $trade->lessons ?: '—' }}</div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header"><h6 class="m-0">Notes</h6></div>
      <div class="card-body">
        @forelse($trade->notes as $note)
          <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
            <div>
              <span class="badge bg-label-{{ $note->source === 'mcp' ? 'info' : 'secondary' }} me-2">{{ $note->source }}</span>
              {{ $note->body }}
            </div>
            <small class="text-body-secondary text-nowrap ms-3">{{ optional($note->created_at)->diffForHumans() }}</small>
          </div>
        @empty
          <p class="text-body-secondary mb-0">No notes yet.</p>
        @endforelse

        <form action="{{ route('trades.notes.store', $trade) }}" method="POST" class="mt-3">
          @csrf
          <div class="mb-2">
            <textarea name="body" class="form-control" rows="2" placeholder="Add a note…" required></textarea>
          </div>
          <button type="submit" class="btn btn-sm btn-primary">Add note</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><h6 class="m-0">Screenshots</h6></div>
      <div class="card-body">
        <div class="row g-2">
          @forelse($trade->screenshots as $shot)
            <div class="col-6">
              <a href="{{ asset('storage/' . $shot->path) }}" target="_blank">
                <img src="{{ asset('storage/' . ($shot->thumb_path ?? $shot->path)) }}" class="img-fluid rounded" alt="{{ $shot->caption }}">
              </a>
              @if($shot->caption)<small class="text-body-secondary d-block">{{ $shot->caption }}</small>@endif
            </div>
          @empty
            <div class="col-12"><p class="text-body-secondary mb-0">No screenshots.</p></div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
