@extends('layouts.admin')

@section('title', 'Monitoring OLT')

@section('content')
@php
    $currentUser = auth()->user();
    $canManageOlt = $currentUser->isSuperAdmin() || in_array($currentUser->role, ['administrator', 'noc'], true);
    $canPollOlt = $canManageOlt || $currentUser->role === 'teknisi';
@endphp
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Monitoring OLT</h3>
        @if($canManageOlt)
            <a href="{{ route('olt-connections.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah OLT HSGQ
            </a>
        @endif
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nama OLT</th>
                        <th>Host</th>
                        <th>Vendor</th>
                        <th>Model</th>
                        <th>ONU Terdeteksi</th>
                        <th>Status Polling</th>
                        <th>Polling Terakhir</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($connections as $connection)
                        <tr>
                            <td>
                                <strong>{{ $connection->name }}</strong>
                                @if(!$connection->is_active)
                                    <span class="badge badge-secondary ml-1">Nonaktif</span>
                                @endif
                            </td>
                            <td>{{ $connection->host }}:{{ $connection->snmp_port }}</td>
                            <td class="text-uppercase">{{ $connection->vendor }}</td>
                            <td>{{ $connection->olt_model ?: '-' }}</td>
                            <td>{{ $connection->onu_optics_count }}</td>
                            <td>
                                @if($connection->isPollingInProgress())
                                    <span class="badge badge-info">Sedang Polling</span>
                                @elseif($connection->last_poll_success === null)
                                    <span class="badge badge-secondary">Belum Pernah Polling</span>
                                @elseif($connection->last_poll_success)
                                    <span class="badge badge-success">Sukses</span>
                                @else
                                    <span class="badge badge-danger">Gagal</span>
                                @endif
                                @if($connection->pollingDisplayMessage())
                                    <div class="small text-muted mt-1">{{ $connection->pollingDisplayMessage() }}</div>
                                @endif
                                @if($connection->isPollingInProgress() && $connection->pollingProgressPercent() !== null)
                                    <div class="progress mt-2" style="height: 6px; max-width: 180px;">
                                        <div class="progress-bar bg-info" role="progressbar"
                                            style="width: {{ $connection->pollingProgressPercent() }}%;"
                                            aria-valuenow="{{ $connection->pollingProgressPercent() }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                @endif
                            </td>
                            <td>{{ $connection->last_polled_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                            <td class="text-right">
                                <a href="{{ route('olt-connections.show', [$connection, 'auto_poll' => 1]) }}" class="btn btn-outline-primary btn-sm" title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if($canPollOlt)
                                    <form action="{{ route('olt-connections.poll', $connection) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success btn-sm" title="Polling Sekarang">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>
                                @endif
                                @if($canManageOlt)
                                    <a href="{{ route('olt-connections.edit', $connection) }}" class="btn btn-outline-warning btn-sm" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <form action="{{ route('olt-connections.destroy', $connection) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Hapus koneksi OLT ini? Data ONU optik yang terkait juga akan dihapus.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Belum ada data OLT HSGQ.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
