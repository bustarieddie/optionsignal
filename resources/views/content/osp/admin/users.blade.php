@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Admin · Users')

@php
  $roleColors = ['Admin' => 'danger', 'Trader' => 'primary', 'Viewer' => 'secondary'];
@endphp

@section('content')
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="d-flex justify-content-between align-items-center mb-4">
  <h5 class="m-0">Users &amp; Roles</h5>
  <span class="badge bg-label-secondary">{{ $users->total() }} users</span>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>User</th><th>Email</th><th>Current role(s)</th><th style="min-width:280px">Change role</th>
        </tr>
      </thead>
      <tbody>
        @forelse($users as $user)
          <tr>
            <td class="fw-medium">{{ $user->name }}</td>
            <td><small class="text-body-secondary">{{ $user->email }}</small></td>
            <td>
              @forelse($user->roles as $role)
                <span class="badge bg-label-{{ $roleColors[$role->name] ?? 'secondary' }}">{{ $role->name }}</span>
              @empty
                <span class="text-body-secondary">—</span>
              @endforelse
            </td>
            <td>
              <form method="POST" action="{{ route('admin.users.roles', $user) }}" class="d-flex gap-2">
                @csrf
                @method('PUT')
                <select name="role" class="form-select form-select-sm">
                  @foreach($roles as $r)
                    <option value="{{ $r }}" @selected($user->roles->pluck('name')->contains($r))>{{ $r }}</option>
                  @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-primary">Save</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-body-secondary py-4">No users found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-4">{{ $users->links() }}</div>
@endsection
