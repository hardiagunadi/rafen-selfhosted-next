@extends('layouts.admin')

@section('title', 'Self-Hosted Toolkit')

@section('content')
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-box-open mr-2 text-primary"></i>Self-Hosted Toolkit</h4>
            <small class="text-muted">Jalankan workflow self-hosted dari UI super admin tanpa command bebas.</small>
        </div>
    </div>

    <div class="alert alert-info">
        <i class="fas fa-info-circle mr-1"></i>
        Semua aksi di halaman ini memakai direktori kerja internal <code>storage/framework</code> dan menyimpan output terakhir ke <code>{{ $historyFile }}</code>.
    </div>

    @if($worktreeStatus['is_dirty'])
        <div class="alert alert-warning">
            <div class="font-weight-bold mb-1">
                <i class="fas fa-exclamation-triangle mr-1"></i>Worktree repo utama masih kotor
            </div>
            <div class="small mb-2">
                Aksi rilis self-hosted seperti <code>stage</code>, <code>seed</code>, <code>audit</code>, <code>materialize</code>, dan <code>publish update notice</code> akan ditolak sampai perubahan SaaS/self-hosted dipisahkan ke commit atau di-stash.
            </div>
            <div class="small">
                Contoh perubahan:
                <code>{{ implode(' | ', array_slice($worktreeStatus['entries'], 0, 4)) }}</code>
                @if($worktreeStatus['count'] > 4)
                    <span class="ml-1 text-muted">dan {{ $worktreeStatus['count'] - 4 }} item lain.</span>
                @endif
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-stream mr-1"></i>Aksi Toolkit</h5>
                </div>
                <div class="card-body" style="display: grid; gap: 1rem;">
                    @foreach($actions as $action)
                        @php($history = $action['history'])
                        <div class="border rounded p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="pr-3">
                                    <div class="font-weight-bold text-{{ $action['tone'] }}">{{ $action['label'] }}</div>
                                    <div class="small text-muted mt-1">{{ $action['note'] }}</div>
                                    <div class="small mt-2"><code>{{ $action['command'] }}</code></div>
                                    @if($action['artifact_path'])
                                        <div class="small text-muted mt-1">Artifact: <code>{{ $action['artifact_path'] }}</code></div>
                                        <div class="small mt-1">
                                            <span class="badge badge-{{ $action['artifact_exists'] ? 'success' : 'secondary' }}">
                                                {{ $action['artifact_exists'] ? 'Artifact ready' : 'Belum ada artifact' }}
                                            </span>
                                        </div>
                                    @endif
                                    @if($history)
                                        <div class="small mt-2">
                                            <span class="badge badge-{{ $history['success'] ? 'success' : 'danger' }}">
                                                {{ $history['success'] ? 'Berhasil' : 'Error' }}
                                            </span>
                                            <span class="text-muted ml-2">Terakhir: {{ app(\App\Services\SelfHostedToolkitService::class)->formatRunTime($history['ran_at'] ?? null) ?? '-' }}</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="d-flex flex-column align-items-end" style="gap: .5rem;">
                                    <button
                                        type="button"
                                        class="btn btn-outline-{{ $action['tone'] }} btn-sm btn-run-toolkit-action"
                                        data-action="{{ $action['key'] }}"
                                        @disabled($action['blocked_by_dirty_worktree'])
                                        title="{{ $action['blocked_by_dirty_worktree'] ? 'Repo utama masih kotor. Bersihkan commit/stash dulu.' : '' }}"
                                    >
                                        Jalankan
                                    </button>
                                    @if($action['artifact_path'])
                                        @if($action['artifact_exists'])
                                            <a
                                                href="{{ route('super-admin.self-hosted-toolkit.download', $action['key']) }}"
                                                class="btn btn-outline-secondary btn-sm"
                                            >
                                                Download
                                            </a>
                                        @else
                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary btn-sm"
                                                disabled
                                                title="Jalankan aksi ini terlebih dahulu agar artifact tersedia."
                                            >
                                                Download
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-terminal mr-1"></i>Output Terakhir</h5>
                    <a href="{{ route('super-admin.terminal.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-terminal mr-1"></i>Buka Terminal
                    </a>
                </div>
                <div class="card-body">
                    <div id="toolkit-meta" class="small text-muted mb-3">Pilih aksi di panel kiri untuk menjalankan workflow self-hosted.</div>
                    <pre id="toolkit-output" class="mb-0 p-3 rounded border bg-dark text-light" style="min-height: 420px; max-height: 620px; overflow: auto; white-space: pre-wrap;">Belum ada output.</pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const toolkitRunUrl = '{{ route('super-admin.self-hosted-toolkit.run') }}';
const toolkitCsrfToken = '{{ csrf_token() }}';
const toolkitOutput = document.getElementById('toolkit-output');
const toolkitMeta = document.getElementById('toolkit-meta');

function setToolkitOutput(meta, output) {
    toolkitMeta.textContent = meta;
    toolkitOutput.textContent = output;
    toolkitOutput.scrollTop = toolkitOutput.scrollHeight;
}

async function runToolkitAction(action, button) {
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = 'Menjalankan...';

    setToolkitOutput('Menjalankan aksi ' + action + '...', 'Memproses...');

    try {
        const response = await fetch(toolkitRunUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': toolkitCsrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ action })
        });

        const data = await response.json();
        const result = data.result || {};
        const exitCode = result.exit_code === null || result.exit_code === undefined ? '-' : result.exit_code;
        const meta = [
            'Aksi: ' + (result.label || action),
            'Command: ' + (result.command || '-'),
            'Exit: ' + exitCode,
            'Durasi: ' + (result.duration_ms || '-') + ' ms',
            'Artifact: ' + (result.artifact_path || '-'),
            result.artifact_path ? 'Unduh: tersedia dari tombol Download di kartu aksi' : 'Unduh: -'
        ].join(' | ');

        if (!response.ok) {
            setToolkitOutput(meta, data.message || 'Terjadi error saat menjalankan toolkit.');
            window.AppAjax.showToast(data.message || 'Gagal menjalankan aksi toolkit.', 'danger');
            return;
        }

        setToolkitOutput(meta, result.output || data.message || '[tidak ada output]');
        window.AppAjax.showToast(data.message || 'Aksi toolkit diproses.', data.success ? 'success' : 'danger');
    } catch (error) {
        setToolkitOutput('Aksi: ' + action, 'Gagal menjalankan aksi toolkit self-hosted.');
        window.AppAjax.showToast('Gagal menjalankan aksi toolkit.', 'danger');
    } finally {
        button.disabled = false;
        button.innerHTML = originalHtml;
    }
}

document.querySelectorAll('.btn-run-toolkit-action').forEach(function (button) {
    button.addEventListener('click', function () {
        runToolkitAction(button.dataset.action, button);
    });
});
</script>
@endpush
