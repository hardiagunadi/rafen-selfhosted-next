@extends('layouts.admin')

@section('title', 'Device Request WA')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-mobile-alt mr-2 text-primary"></i> Device Request WA Platform</h4>
        <a href="{{ route('super-admin.wa-gateway') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Kembali ke WA Gateway
        </a>
    </div>
    <div class="card-body p-0">
        @if(session('success'))
            <div class="alert alert-success mx-3 mt-3 mb-0">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger mx-3 mt-3 mb-0">{{ session('error') }}</div>
        @endif

        @if($platformDevices->isEmpty())
            <div class="alert alert-warning mx-3 mt-3 mb-0">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Belum ada <strong>Platform Device</strong> yang aktif. Tandai device sebagai Platform Device di
                <a href="{{ route('super-admin.wa-gateway') }}">halaman WA Gateway</a> terlebih dahulu.
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Tenant</th>
                        <th style="width:110px;">Status</th>
                        <th>Alasan</th>
                        <th>Device Digunakan</th>
                        <th>Catatan SA</th>
                        <th style="width:140px;">Tanggal</th>
                        <th style="width:200px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    <tr>
                        <td>
                            <div class="font-weight-bold">{{ $req->tenant->name ?? '-' }}</div>
                            <div class="text-muted small">{{ $req->tenant->email ?? '' }}</div>
                        </td>
                        <td>
                            @if($req->isPending())
                                <span class="badge badge-warning">Pending</span>
                            @elseif($req->isApproved())
                                <span class="badge badge-success">Disetujui</span>
                            @else
                                <span class="badge badge-danger">Ditolak</span>
                            @endif
                        </td>
                        <td>{{ $req->reason ?: '-' }}</td>
                        <td>
                            @if($req->device)
                                <span class="badge badge-primary">{{ $req->device->device_name }}</span>
                                <div class="text-muted small">{{ $req->device->wa_number }}</div>
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $req->notes ?: '-' }}</td>
                        <td>
                            <div>{{ $req->created_at->format('d/m/Y H:i') }}</div>
                            @if($req->approved_at)
                                <div class="text-muted small">Diproses: {{ $req->approved_at->format('d/m/Y H:i') }}</div>
                            @endif
                        </td>
                        <td>
                            @if($req->isPending())
                                <button class="btn btn-xs btn-success btn-approve" data-id="{{ $req->id }}" data-name="{{ $req->tenant->name ?? '-' }}">
                                    <i class="fas fa-check mr-1"></i>Setujui
                                </button>
                                <button class="btn btn-xs btn-danger btn-reject" data-id="{{ $req->id }}" data-name="{{ $req->tenant->name ?? '-' }}">
                                    <i class="fas fa-times mr-1"></i>Tolak
                                </button>
                            @elseif($req->isApproved())
                                <form method="POST" action="{{ route('super-admin.wa-platform-device-requests.revoke', $req) }}"
                                      onsubmit="return confirm('Cabut akses platform device dari {{ addslashes($req->tenant->name ?? '-') }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger">
                                        <i class="fas fa-ban mr-1"></i>Cabut Akses
                                    </button>
                                </form>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Belum ada permintaan device WA platform.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
        <div class="px-3 py-2">
            {{ $requests->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Modal Approve --}}
<div class="modal fade" id="modalApprove" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form-approve" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Setujui Request Device WA</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Setujui permintaan dari <strong id="approve-tenant-name"></strong>?</p>
                    <div class="form-group">
                        <label>Pilih Platform Device <span class="text-danger">*</span></label>
                        <select name="device_id" class="form-control" required>
                            <option value="">-- Pilih Device --</option>
                            @foreach($platformDevices as $dev)
                                <option value="{{ $dev->id }}">
                                    {{ $dev->device_name }} ({{ $dev->wa_number }})
                                    @if($dev->is_default) [Default] @endif
                                </option>
                            @endforeach
                        </select>
                        @if($platformDevices->isEmpty())
                            <small class="text-danger">Tidak ada platform device aktif. Tambahkan dulu di WA Gateway.</small>
                        @endif
                    </div>
                    <div class="form-group mb-0">
                        <label>Catatan (opsional)</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="500" placeholder="Catatan untuk tenant..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" {{ $platformDevices->isEmpty() ? 'disabled' : '' }}>
                        <i class="fas fa-check mr-1"></i>Setujui
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Reject --}}
<div class="modal fade" id="modalReject" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="form-reject" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tolak Request Device WA</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Tolak permintaan dari <strong id="reject-tenant-name"></strong>?</p>
                    <div class="form-group mb-0">
                        <label>Alasan penolakan (opsional)</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="500" placeholder="Alasan penolakan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times mr-1"></i>Tolak
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var approveBase = '{{ url("super-admin/wa-platform-device-requests") }}';

    document.querySelectorAll('.btn-approve').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id   = this.dataset.id;
            var name = this.dataset.name;
            document.getElementById('approve-tenant-name').textContent = name;
            document.getElementById('form-approve').action = approveBase + '/' + id + '/approve';
            $('#modalApprove').modal('show');
        });
    });

    document.querySelectorAll('.btn-reject').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id   = this.dataset.id;
            var name = this.dataset.name;
            document.getElementById('reject-tenant-name').textContent = name;
            document.getElementById('form-reject').action = approveBase + '/' + id + '/reject';
            $('#modalReject').modal('show');
        });
    });
});
</script>
@endsection
