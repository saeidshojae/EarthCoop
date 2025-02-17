@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg rounded">
                <div class="card-header bg-primary text-white text-center">
                    <h4>{{ __('Dashboard') }}</h4>
                </div>

                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('status') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <div class="text-center mt-4">
                        <h5>{{ __('You are logged in!') }}</h5>
                        <p class="mt-3">Welcome to your dashboard. You can manage your settings and view your content here.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
