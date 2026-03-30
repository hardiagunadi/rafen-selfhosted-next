@extends('layouts.admin')

@section('title', 'Langganan Berakhir')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-exclamation-circle fa-5x text-warning mb-4"></i>
                <h2>Langganan Anda Telah Berakhir</h2>
                <p class="text-muted mb-4">
                    Untuk melanjutkan menggunakan aplikasi, silakan perpanjang langganan Anda.
                </p>
                <a href="{{ route('subscription.plans') }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-cart"></i> Lihat Paket Langganan
                </a>
            </div>
        </div>

        @if($plans->count() > 0)
        <h4 class="text-center mb-4">Pilih Paket yang Sesuai</h4>
        <div class="row">
            @foreach($plans->take(3) as $plan)
            <div class="col-md-4">
                <div class="card {{ $plan->is_featured ? 'card-primary' : '' }}">
                    <div class="card-header text-center">
                        <h5 class="card-title mb-0">{{ $plan->name }}</h5>
                    </div>
                    <div class="card-body text-center">
                        <h3 class="text-primary">
                            Rp {{ number_format($plan->price, 0, ',', '.') }}
                        </h3>
                        <p class="text-muted">{{ $user->resolveSubscriptionDurationDays($plan) }} hari</p>
                        <form action="{{ route('subscription.subscribe', $plan) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-block">
                                Pilih
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
