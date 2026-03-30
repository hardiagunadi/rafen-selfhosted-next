@extends('layouts.admin')

@section('title', 'Paket Langganan')

@section('content')
<div class="row">
    @forelse($plans as $plan)
    <div class="col-md-4 col-sm-6">
        <div class="card {{ $plan->is_featured ? 'card-primary' : '' }}">
            @if($plan->is_featured)
            <div class="ribbon-wrapper ribbon-lg">
                <div class="ribbon bg-warning text-dark">
                    Populer
                </div>
            </div>
            @endif
            <div class="card-header text-center">
                <h3 class="card-title">{{ $plan->name }}</h3>
            </div>
            <div class="card-body text-center">
                <h2 class="text-primary">
                    Rp {{ number_format($plan->price, 0, ',', '.') }}
                    <small class="text-muted">/ {{ $user ? $user->resolveSubscriptionDurationDays($plan) : $plan->duration_days }} hari</small>
                </h2>

                @if($plan->description)
                <p class="text-muted">{{ $plan->description }}</p>
                @endif

                <hr>

                <ul class="list-unstyled text-left">
                    <li class="mb-2">
                        <i class="fas fa-server text-primary mr-2"></i>
                        <strong>{{ $plan->max_mikrotik == -1 ? 'Unlimited' : $plan->max_mikrotik }}</strong> Mikrotik
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-users text-primary mr-2"></i>
                        <strong>{{ $plan->max_ppp_users == -1 ? 'Unlimited' : $plan->max_ppp_users }}</strong> PPP Users
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-shield-alt text-primary mr-2"></i>
                        <strong>{{ $plan->max_vpn_peers == -1 ? 'Unlimited' : $plan->max_vpn_peers }}</strong> VPN Peers
                    </li>
                    @if($plan->features)
                        @foreach($plan->features as $feature)
                        <li class="mb-2">
                            <i class="fas fa-check text-success mr-2"></i>
                            {{ $feature }}
                        </li>
                        @endforeach
                    @endif
                </ul>
            </div>
            <div class="card-footer text-center">
                <form action="{{ route('subscription.subscribe', $plan) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn {{ $plan->is_featured ? 'btn-warning' : 'btn-primary' }} btn-block">
                        <i class="fas fa-shopping-cart"></i> Pilih Paket
                    </button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Belum ada paket langganan tersedia.
        </div>
    </div>
    @endforelse
</div>
@endsection
