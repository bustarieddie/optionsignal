@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Admin · Webhook Logs')

@php
  $statusColors = ['processed' => 'success', 'rejected' => 'danger', 'pending' => 'warning', 'duplicate' => 'secondary'];
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0">TradingView Webhook Logs</h5>
  <span class="badge bg-label-secondary">{{ $webhooks->total() }} total</span>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Ticker</th><th>Signal</th><th>Status</th><th>Secret valid</th>
          <th>Source IP</th><th>Received</th><th>Payload (safe)</th>
        </tr>
      </thead>
      <tbody>
        @forelse($webhooks as $hook)
          <tr>
            <td class="fw-medium">{{ $hook->ticker ?? '—' }}</td>
            <td>{{ $hook->signal ?? '—' }}</td>
            <td><span class="badge bg-label-{{ $statusColors[$hook->status] ?? 'secondary' }}">{{ ucfirst($hook->status ?? 'unknown') }}</span></td>
            <td>
              @if($hook->secret_valid)
                <span class="badge bg-label-success">Valid</span>
              @else
                <span class="badge bg-label-danger">Invalid</span>
              @endif
            </td>
            <td><small class="text-body-secondary">{{ $hook->source_ip ?? '—' }}</small></td>
            <td><small class="text-body-secondary">{{ optional($hook->created_at)->format('Y-m-d H:i:s') }}</small></td>
            <td>
              <code class="small text-break">{{ \Illuminate\Support\Str::limit(json_encode($hook->safePayload()), 120) }}</code>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-body-secondary py-4">No webhooks received yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-4">{{ $webhooks->links() }}</div>
@endsection
