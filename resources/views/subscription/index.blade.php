@extends('layouts.admin')

@section('title', 'Status Langganan')

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Status Langganan Anda</h3>
            </div>
            <div class="card-body">
                @if($user->isSuperAdmin())
                    <div class="text-center">
                        <i class="fas fa-crown fa-3x text-warning mb-3"></i>
                        <h4>Super Admin</h4>
                        <p class="text-muted">Anda memiliki akses penuh tanpa batas.</p>
                    </div>
                @elseif($user->hasActiveSubscription())
                    <div class="text-center">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h4>Langganan Aktif</h4>
                        <p class="text-muted">{{ $user->subscriptionPlan->name ?? 'Paket Standard' }}</p>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <h5 class="text-primary">{{ $user->getSubscriptionDaysRemaining() }}</h5>
                            <small class="text-muted">Hari Tersisa</small>
                        </div>
                        <div class="col-6">
                            <h5 class="text-primary">{{ $user->subscription_expires_at->format('d M Y') }}</h5>
                            <small class="text-muted">Berakhir</small>
                        </div>
                    </div>
                    <hr>
                    <a href="{{ route('subscription.renew') }}" class="btn btn-primary btn-block">
                        <i class="fas fa-sync-alt"></i> Perpanjang Langganan
                    </a>
                @elseif($user->isOnTrial())
                    <div class="text-center">
                        <i class="fas fa-hourglass-half fa-3x text-warning mb-3"></i>
                        <h4>Masa Percobaan</h4>
                        <p class="text-muted">{{ $user->trial_days_remaining }} hari tersisa</p>
                    </div>
                    <hr>
                    <a href="{{ route('subscription.plans') }}" class="btn btn-success btn-block">
                        <i class="fas fa-shopping-cart"></i> Langganan Sekarang
                    </a>
                @else
                    <div class="text-center">
                        <i class="fas fa-times-circle fa-3x text-danger mb-3"></i>
                        <h4>Langganan Berakhir</h4>
                        <p class="text-muted">Silakan perpanjang untuk melanjutkan.</p>
                    </div>
                    <hr>
                    <a href="{{ route('subscription.plans') }}" class="btn btn-success btn-block">
                        <i class="fas fa-shopping-cart"></i> Langganan Sekarang
                    </a>
                @endif
            </div>
        </div>

        @if($currentSubscription && $currentSubscription->plan)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Fitur Paket Anda</h3>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Mikrotik</span>
                        <strong>{{ $currentSubscription->plan->max_mikrotik == -1 ? 'Unlimited' : $currentSubscription->plan->max_mikrotik }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>PPP Users</span>
                        <strong>{{ $currentSubscription->plan->max_ppp_users == -1 ? 'Unlimited' : $currentSubscription->plan->max_ppp_users }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>VPN Peers</span>
                        <strong>{{ $currentSubscription->plan->max_vpn_peers == -1 ? 'Unlimited' : $currentSubscription->plan->max_vpn_peers }}</strong>
                    </li>
                    @if($currentSubscription->plan->features)
                        @foreach($currentSubscription->plan->features as $feature)
                        <li class="list-group-item">
                            <i class="fas fa-check text-success mr-2"></i> {{ $feature }}
                        </li>
                        @endforeach
                    @endif
                </ul>
            </div>
        </div>
        @endif
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Riwayat Langganan</h3>
            </div>
            <div class="card-body p-0">
                <table id="dt-subscriptions" class="table table-hover text-nowrap mb-0 w-100">
                    <thead>
                        <tr>
                            <th>Paket</th>
                            <th>Mulai</th>
                            <th>Berakhir</th>
                            <th>Status</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var dtTable = null;
    var dtUrl = '{{ route("subscription.subscriptions-datatable") }}';

    function init() {
        if (!document.getElementById('dt-subscriptions')) return;
        if (dtTable) { dtTable.destroy(); dtTable = null; }

        dtTable = $('#dt-subscriptions').DataTable({
            processing: true,
            serverSide: true,
            ajax: dtUrl,
            columns: [
                { data: 'plan' },
                { data: 'start_date' },
                { data: 'end_date' },
                { data: 'status', orderable: false },
                { data: 'amount' },
            ],
            language: { url: false, emptyTable: 'Belum ada riwayat langganan.', processing: 'Memuat...', search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ baris', info: 'Menampilkan _START_-_END_ dari _TOTAL_', paginate: { next: 'Berikutnya', previous: 'Sebelumnya' } },
            pageLength: 10,
            order: [],
        });
    }

    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endsection
