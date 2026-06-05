@php
$configData = Helper::appClasses();
@endphp
@extends('layouts/layoutMaster')

@section('title', 'Profile')

@section('content')
<div class="row">
  <div class="col-lg-8">
    <h4 class="mb-1">My Profile</h4>
    <p class="mb-6 text-body-secondary">Role: <span class="badge bg-label-primary">{{ $user->getRoleNames()->first() }}</span></p>

    @if (session('status') === 'profile-information-updated')
      <div class="alert alert-success">Profile updated.</div>
    @endif
    @if (session('status') === 'password-updated')
      <div class="alert alert-success">Password updated.</div>
    @endif
    @if ($errors->any())
      <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <!-- Profile information -->
    <div class="card mb-6">
      <div class="card-header"><h5 class="card-title m-0">Account details</h5></div>
      <div class="card-body">
        <form method="POST" action="{{ url('user/profile-information') }}">
          @csrf @method('PUT')
          <div class="mb-4">
            <label class="form-label" for="name">Name</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required>
          </div>
          <div class="mb-4">
            <label class="form-label" for="email">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $user->email) }}" required>
          </div>
          <button class="btn btn-primary">Save</button>
        </form>
      </div>
    </div>

    <!-- Update password -->
    <div class="card mb-6">
      <div class="card-header"><h5 class="card-title m-0">Change password</h5></div>
      <div class="card-body">
        <form method="POST" action="{{ url('user/password') }}">
          @csrf @method('PUT')
          <div class="mb-4">
            <label class="form-label" for="current_password">Current password</label>
            <input type="password" class="form-control" id="current_password" name="current_password" required>
          </div>
          <div class="mb-4">
            <label class="form-label" for="password">New password</label>
            <input type="password" class="form-control" id="password" name="password" required>
          </div>
          <div class="mb-4">
            <label class="form-label" for="password_confirmation">Confirm new password</label>
            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
          </div>
          <button class="btn btn-primary">Update password</button>
        </form>
      </div>
    </div>

    <!-- Two-factor -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Two-Factor Authentication</h5>
        <span class="badge {{ $twoFactorEnabled ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $twoFactorEnabled ? 'Enabled' : 'Disabled' }}</span>
      </div>
      <div class="card-body">
        <p class="text-body-secondary">Add an extra layer of security using a TOTP authenticator app.</p>
        @if (! $twoFactorEnabled)
          <form method="POST" action="{{ url('user/two-factor-authentication') }}">
            @csrf
            <button class="btn btn-primary">Enable 2FA</button>
          </form>
        @else
          @if (session('status') == 'two-factor-authentication-enabled')
            <div class="alert alert-info">
              <p class="mb-2">Scan this QR code with your authenticator app, then confirm with a code.</p>
              <div>{!! $user->twoFactorQrCodeSvg() !!}</div>
            </div>
            <form method="POST" action="{{ url('user/confirmed-two-factor-authentication') }}" class="mb-4">
              @csrf
              <div class="input-group" style="max-width:320px">
                <input type="text" class="form-control" name="code" inputmode="numeric" placeholder="6-digit code" required>
                <button class="btn btn-primary">Confirm</button>
              </div>
            </form>
          @endif
          <form method="POST" action="{{ url('user/two-factor-authentication') }}">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger">Disable 2FA</button>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
