@extends('layouts.admin')

@section('title', 'Pengaturan FreeRADIUS')

@section('content')
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h4 class="mb-0">Status Sinkronisasi</h4>
                <div class="d-flex gap-2 mt-1 mt-md-0">
                    <form action="{{ route('settings.freeradius.sync') }}" method="POST" class="mr-2">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fas fa-sync-alt"></i> Sync NAS Clients
                        </button>
                    </form>
                    <form action="{{ route('settings.freeradius.sync-replies') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-sync-alt"></i> Sync Radcheck/Radreply
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif
            <div class="mb-2"><strong>Clients Path:</strong> {{ $clientsPath ?: '-' }}</div>
            <div class="mb-2"><strong>Status:</strong> {{ $syncStatus['message'] }}</div>
            <div class="mb-2"><strong>Terakhir Update:</strong> {{ $syncStatus['updated_at'] ?? '-' }}</div>
            <div class="mb-2"><strong>Ukuran File:</strong> {{ $syncStatus['size'] !== null ? $syncStatus['size'].' bytes' : '-' }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Log FreeRADIUS Terbaru</h4>
        </div>
        <div class="card-body">
            <div class="mb-2"><strong>Log Path:</strong> {{ $logPath ?: '-' }}</div>
            @if ($logPayload['error'])
                <div class="alert alert-danger mb-0">
                    {{ $logPayload['error'] }}
                </div>
            @else
                <pre class="mb-0" style="max-height: 420px; overflow:auto;">{{ implode("\n", $logPayload['lines']) }}</pre>
            @endif
        </div>
    </div>
@endsection
