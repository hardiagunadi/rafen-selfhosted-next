@extends('layouts.admin')

@section('title', 'Pelacakan Gangguan Jaringan')

@section('content')
<div class="row mb-2">
    <div class="col-sm-6">
        <h1 class="m-0">Gangguan Jaringan</h1>
    </div>
    <div class="col-sm-6 text-right">
        @if($user->isSuperAdmin() || in_array($user->role, ['administrator','noc','it_support']))
        <a href="{{ route('outages.create') }}" class="btn btn-danger">
            <i class="fas fa-exclamation-triangle"></i> Laporkan Gangguan
        </a>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="d-flex flex-wrap gap-2">
            <select id="filterStatus" class="form-control form-control-sm" style="width:auto">
                <option value="">Semua Status</option>
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
            </select>
            <select id="filterSeverity" class="form-control form-control-sm" style="width:auto">
                <option value="">Semua Severity</option>
                <option value="critical">Critical</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
            <input type="text" id="searchInput" class="form-control form-control-sm" style="width:200px" placeholder="Cari judul...">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" id="outageTable">
                <thead class="thead-light">
                    <tr>
                        <th>Judul</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Area</th>
                        <th>Mulai</th>
                        <th>Estimasi Selesai</th>
                        <th>Teknisi</th>
                        <th>WA Terkirim</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="outageTableBody">
                    <tr><td colspan="9" class="text-center py-3">Memuat...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer" id="paginationFooter"></div>
</div>
@endsection

@push('scripts')
<script>
@php
$severityBadge = ['critical'=>'badge-danger','high'=>'badge-warning','medium'=>'badge-info','low'=>'badge-secondary'];
$statusBadge   = ['open'=>'badge-danger','in_progress'=>'badge-warning','resolved'=>'badge-success'];
$statusLabel   = ['open'=>'Open','in_progress'=>'In Progress','resolved'=>'Resolved'];
@endphp

let currentPage = 1;

function severityBadge(s) {
    const map = {critical:'badge-danger',high:'badge-warning',medium:'badge-info',low:'badge-secondary'};
    return `<span class="badge ${map[s]||'badge-light'}">${s}</span>`;
}
function statusBadge(s) {
    const map = {open:'badge-danger',in_progress:'badge-warning',resolved:'badge-success'};
    const label = {open:'Open',in_progress:'In Progress',resolved:'Resolved'};
    return `<span class="badge ${map[s]||'badge-light'}">${label[s]||s}</span>`;
}

function loadData(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({
        page,
        status:   document.getElementById('filterStatus').value,
        severity: document.getElementById('filterSeverity').value,
        search:   document.getElementById('searchInput').value,
    });

    fetch(`{{ route('outages.datatable') }}?${params}`, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r => r.json())
        .then(res => {
            const tbody = document.getElementById('outageTableBody');
            if (!res.data.length) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Tidak ada data</td></tr>';
                document.getElementById('paginationFooter').innerHTML = '';
                return;
            }
            tbody.innerHTML = res.data.map(o => `
                <tr>
                    <td><strong>${o.title}</strong></td>
                    <td>${severityBadge(o.severity)}</td>
                    <td>${statusBadge(o.status)}</td>
                    <td>${o.affected_area_count} area</td>
                    <td>${o.started_at||'-'}</td>
                    <td>${o.estimated_resolved_at||'-'}</td>
                    <td>${o.assigned_teknisi}</td>
                    <td>${o.wa_blast_count > 0
                        ? '<i class="fab fa-whatsapp text-success"></i> '+o.wa_blast_count+(o.affected_users_count !== null ? '/'+o.affected_users_count : '')
                        : '-'}</td>
                    <td><a href="${o.show_url}" class="btn btn-xs btn-default"><i class="fas fa-eye"></i></a></td>
                </tr>
            `).join('');

            // Pagination
            let pages = '';
            for (let p = 1; p <= res.last_page; p++) {
                pages += `<li class="page-item ${p===res.current_page?'active':''}">
                    <a class="page-link" href="#" onclick="loadData(${p});return false">${p}</a></li>`;
            }
            document.getElementById('paginationFooter').innerHTML = res.last_page > 1
                ? `<ul class="pagination pagination-sm mb-0">${pages}</ul>` : '';
        });
}

document.getElementById('filterStatus').addEventListener('change', () => loadData(1));
document.getElementById('filterSeverity').addEventListener('change', () => loadData(1));
let searchTimer;
document.getElementById('searchInput').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadData(1), 400);
});

loadData();
</script>
@endpush
