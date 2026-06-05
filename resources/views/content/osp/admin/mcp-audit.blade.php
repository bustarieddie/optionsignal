@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Admin · MCP Audit Log')

@php
  $statusColors = ['ok' => 'success', 'denied' => 'warning', 'error' => 'danger'];
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0">MCP Audit Log</h5>
  <span class="badge bg-label-secondary">{{ $logs->total() }} calls</span>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Tool</th><th>Type</th><th>Result</th><th>User</th>
          <th>Token</th><th>Duration</th><th>Source IP</th><th>When</th>
        </tr>
      </thead>
      <tbody>
        @forelse($logs as $log)
          <tr>
            <td><code class="small">{{ $log->tool_name }}</code></td>
            <td>
              @if($log->is_write)
                <span class="badge bg-label-warning">Write</span>
              @else
                <span class="badge bg-label-info">Read</span>
              @endif
            </td>
            <td><span class="badge bg-label-{{ $statusColors[$log->result_status] ?? 'secondary' }}">{{ ucfirst($log->result_status) }}</span></td>
            <td>
              @if($log->user)
                <span class="fw-medium">{{ $log->user->name }}</span>
                <br><small class="text-body-secondary">{{ $log->user->email }}</small>
              @else
                <span class="text-body-secondary">—</span>
              @endif
            </td>
            <td><small class="text-body-secondary">#{{ $log->token_id ?? '—' }}</small></td>
            <td><small class="text-body-secondary">{{ $log->duration_ms }} ms</small></td>
            <td><small class="text-body-secondary">{{ $log->source_ip ?? '—' }}</small></td>
            <td><small class="text-body-secondary">{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</small></td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-body-secondary py-4">No MCP calls recorded yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-4">{{ $logs->links() }}</div>
@endsection
