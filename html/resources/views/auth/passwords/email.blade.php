@extends('admin.layouts.app')
@section('title','Forgot Password')

@section('content')
  <div class="row justify-content-center"><div class="col-md-6">
      <div class="card">
        <div class="card-header"><strong>Forgot your password?</strong></div>
        <div class="card-body">
          @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
          @endif
          <form method="post" action="{{ route('password.email') }}">@csrf
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <button class="btn btn-primary">Send reset link</button>
          </form>
        </div>
      </div>
    </div></div>
@endsection
