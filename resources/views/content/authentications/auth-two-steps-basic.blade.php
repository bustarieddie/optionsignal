@php
  $configData = Helper::appClasses();
  $customizerHidden = 'customizer-hide';
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Two Steps Verifications Basic - Pages')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

@section('page-style')
  @vite(['resources/assets/vendor/scss/pages/page-auth.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/cleave-zen/cleave-zen.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/pages-auth.js', 'resources/assets/js/pages-auth-two-steps.js'])
@endsection

@section('content')
  <div class="positive-relative">
    <div class="authentication-wrapper authentication-basic p-4 p-sm-0">
      <div class="authentication-inner py-6">
        <!--  Two Steps Verification -->
        <div class="card p-md-7 p-1">
          <!-- Logo -->
          <div class="app-brand justify-content-center mt-5">
            <a href="{{ url('/') }}" class="app-brand-link gap-2">
              <span class="app-brand-logo demo">@include('_partials.macros')</span>
              <span class="app-brand-text demo text-heading fw-semibold">{{ config('variables.templateName') }}</span>
            </a>
          </div>
          <!-- /Logo -->
          <div class="card-body mt-1">
            <h4 class="mb-1">Two Step Verification 💬</h4>
            <p class="text-start mb-5">
              Enter the authentication code provided by your authenticator app to continue.
            </p>

            @if ($errors->any())
              <div class="alert alert-danger" role="alert">
                <ul class="mb-0 ps-3">
                  @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                  @endforeach
                </ul>
              </div>
            @endif

            <form id="formAuthentication" action="{{ route('two-factor.login') }}" method="POST">
              @csrf
              <div class="mb-5 form-floating form-floating-outline form-control-validation">
                <input type="text" class="form-control" id="code" name="code" inputmode="numeric"
                  autocomplete="one-time-code" placeholder="Enter authentication code" autofocus />
                <label for="code">Authentication Code</label>
              </div>
              <div class="mb-5 form-floating form-floating-outline form-control-validation d-none" id="recovery-code-wrapper">
                <input type="text" class="form-control" id="recovery_code" name="recovery_code"
                  autocomplete="one-time-code" placeholder="Enter recovery code" />
                <label for="recovery_code">Recovery Code</label>
              </div>
              <button class="btn btn-primary d-grid w-100 mb-5">Verify my account</button>
              <div class="text-center">
                <a href="javascript:void(0);" onclick="document.getElementById('recovery-code-wrapper').classList.toggle('d-none');">
                  Use a recovery code
                </a>
              </div>
            </form>
          </div>
        </div>
        <!-- / Two Steps Verification -->
        <img alt="mask"
          src="{{ asset('assets/img/illustrations/auth-basic-register-mask-' . $configData['theme'] . '.png') }}"
          class="authentication-image d-none d-lg-block"
          data-app-light-img="illustrations/auth-basic-register-mask-light.png"
          data-app-dark-img="illustrations/auth-basic-register-mask-dark.png" />
      </div>
    </div>
  </div>
@endsection
