@php
$configData = Helper::appClasses();
@endphp
@extends('layouts/layoutMaster')

@section('title', 'Pine Script Template')

@section('content')
<h4 class="mb-1">TradingView Pine Script Template</h4>
<p class="mb-6 text-body-secondary">Paste this Pine v6 indicator into TradingView, set an alert with condition <strong>"Any alert() function call"</strong>, and point the webhook at the URL below. The JSON it emits matches this app's webhook exactly.</p>

<div class="row g-6">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
          @foreach ($scripts as $i => $s)
            <li class="nav-item">
              <button class="nav-link {{ $i === 0 ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#pine-{{ $s['key'] }}" type="button" role="tab">{{ $s['name'] }}</button>
            </li>
          @endforeach
        </ul>
      </div>
      <div class="card-body">
        <div class="tab-content p-0">
          @foreach ($scripts as $i => $s)
            <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}" id="pine-{{ $s['key'] }}" role="tabpanel">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <code>{{ $s['file'] }}</code>
                  <span class="badge bg-label-secondary ms-2">strategy: {{ $s['strategy'] }}</span>
                </div>
                <button class="btn btn-sm btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('pine-src-{{ $s['key'] }}').textContent); this.textContent='Copied!'; setTimeout(()=>this.textContent='Copy',1500)">Copy</button>
              </div>
              <pre class="mb-0" style="max-height:480px;overflow:auto"><code id="pine-src-{{ $s['key'] }}">{{ $s['code'] }}</code></pre>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-6">
      <div class="card-header"><h5 class="m-0">Webhook URL</h5></div>
      <div class="card-body">
        <code class="d-block text-break">{{ $webhookUrl }}</code>
        <small class="text-body-secondary d-block mt-2">The <code>secret</code> travels inside the JSON body (TradingView sends no custom headers). Set it in the Pine input and in <code>.env</code> as <code>TRADINGVIEW_WEBHOOK_SECRET</code>.</small>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h5 class="m-0">Sample payload</h5></div>
      <div class="card-body">
        <pre class="mb-0"><code>{{ $sampleJson }}</code></pre>
      </div>
    </div>
  </div>
</div>

<div class="alert alert-warning mt-6 mb-0">
  <i class="icon-base ri ri-information-line me-1"></i>
  Decision support only. The app tracks and scores these signals — it never executes trades.
</div>
@endsection
