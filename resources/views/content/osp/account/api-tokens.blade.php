@php
$configData = Helper::appClasses();
@endphp
@extends('layouts/layoutMaster')

@section('title', 'API Tokens')

@section('content')
<div class="row">
  <div class="col-12">
    <h4 class="mb-1">API Tokens</h4>
    <p class="mb-6 text-body-secondary">Personal access tokens authenticate REST API and MCP requests. Grant the minimum abilities needed — MCP write access is deliberately opt-in.</p>

    @if (session('status'))
      <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    @if ($plainTextToken)
      <div class="alert alert-warning" role="alert">
        <h6 class="alert-heading mb-2">Copy your new token now</h6>
        <code class="d-block text-break">{{ $plainTextToken }}</code>
        <small class="d-block mt-2">This is the only time it will be shown.</small>
      </div>
    @endif

    <div class="card mb-6">
      <div class="card-header"><h5 class="card-title m-0">Create token</h5></div>
      <div class="card-body">
        <form method="POST" action="{{ route('account.api-tokens.store') }}">
          @csrf
          <div class="row g-4">
            <div class="col-md-5">
              <label class="form-label" for="token-name">Token name</label>
              <input type="text" class="form-control" id="token-name" name="name" placeholder="e.g. Claude MCP" required value="{{ old('name') }}">
            </div>
            <div class="col-md-7">
              <label class="form-label d-block">Abilities</label>
              @foreach ($abilities as $key => $label)
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="abilities[]" value="{{ $key }}" id="ab-{{ $loop->index }}"
                    {{ $key === 'mcp:read' ? 'checked' : '' }}>
                  <label class="form-check-label" for="ab-{{ $loop->index }}"><code>{{ $key }}</code> — {{ $label }}</label>
                </div>
              @endforeach
            </div>
          </div>
          <button type="submit" class="btn btn-primary mt-4">Create token</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h5 class="card-title m-0">Your tokens</h5></div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Name</th><th>Abilities</th><th>Last used</th><th>Created</th><th></th></tr></thead>
          <tbody>
            @forelse ($tokens as $token)
              <tr>
                <td class="fw-medium">{{ $token->name }}</td>
                <td>
                  @foreach ($token->abilities ?? [] as $ability)
                    <span class="badge bg-label-secondary">{{ $ability }}</span>
                  @endforeach
                </td>
                <td><small>{{ $token->last_used_at ? $token->last_used_at->diffForHumans() : 'never' }}</small></td>
                <td><small>{{ $token->created_at->diffForHumans() }}</small></td>
                <td class="text-end">
                  <form method="POST" action="{{ route('account.api-tokens.destroy', $token->id) }}" onsubmit="return confirm('Revoke this token?');">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-text-danger">Revoke</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-body-secondary py-4">No tokens yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
