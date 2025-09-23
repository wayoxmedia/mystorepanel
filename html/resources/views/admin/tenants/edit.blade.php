@extends('layouts.app')

@section('title', 'Edit Tenant')

@section('content')
  <div class="container py-4">
    <h1 class="h4 mb-3">Edit Tenant</h1>
    @include('admin.tenants._form', [
      'tenant' => $tenant,
      'action' => route('admin.tenants.update', $tenant),
      'method' => 'PUT',
      // 'templates' => $templates ?? collect(),
    ])
  </div>
@endsection
