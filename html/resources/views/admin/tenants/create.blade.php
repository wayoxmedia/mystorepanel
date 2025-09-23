@extends('admin.layouts.app')

@section('title', 'Create Tenant')

@section('content')
  <div class="container py-4">
    <h1 class="h4 mb-3">Create Tenant</h1>
    @include('admin.tenants.form', [
      'tenant' => null,
      'action' => route('admin.tenants.store'),
      'method' => 'POST',
      // 'templates' => $templates ?? collect(), // si pasas templates desde el controller
    ])
  </div>
@endsection
