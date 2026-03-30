@extends('layouts.admin')

@section('title', 'Terminal Super Admin')

@section('content')
<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h4 class="mb-0"><i class="fas fa-terminal mr-2 text-primary"></i>Terminal Super Admin</h4>
            <small class="text-muted">Menjalankan command operasional RAFEN dan toolkit self-hosted sesuai referensi Pusat Bantuan.</small>
        </div>
    </div>

    <div class="alert alert-warning">
        <i class="fas fa-shield-alt mr-1"></i>
        Hanya command yang terdaftar pada cakupan bantuan RAFEN yang bisa dijalankan. Command infrastruktur (mis. <code>wg</code>, <code>systemctl</code>) otomatis dijalankan dengan <code>sudo -n</code>. Timeout per command: <strong>{{ $timeoutSeconds }} detik</strong>.
    </div>

    <div class="alert alert-info">
        <i class="fas fa-box-open mr-1"></i>
        Preset <strong>Self-Hosted</strong> akan memakai direktori kerja internal di <code>storage/framework</code> agar maintainer bisa membangun manifest, stage bundle, import, audit, dan candidate repo langsung dari UI super admin tanpa SSH.
    </div>

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list-ul mr-1"></i>Quick Command</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($presets as $preset)
                        <button type="button"
                            class="list-group-item list-group-item-action text-left btn-preset-command"
                            data-command="{{ $preset['command'] }}">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <strong>{{ $preset['label'] }}</strong>
                                    <div class="small text-muted mt-1">{{ $preset['note'] }}</div>
                                </div>
                                <code class="small text-nowrap">{{ $preset['command'] }}</code>
                            </div>
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-play-circle mr-1"></i>Eksekusi Command</h5>
                    <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-info">
                        <i class="fas fa-book mr-1"></i>Buka Pusat Bantuan
                    </a>
                </div>
                <div class="card-body">
                    <div class="form-group mb-2">
                        <label for="terminal-command" class="mb-1">Command</label>
                        <input type="text" id="terminal-command" class="form-control" autocomplete="off" placeholder="Contoh: php artisan radius:sync-replies">
                        <small class="text-muted">Tip: klik command di panel kiri untuk mengisi otomatis.</small>
                    </div>

                    <div class="d-flex gap-2 mb-3">
                        <button type="button" id="btn-terminal-run" class="btn btn-primary">
                            <i class="fas fa-play mr-1"></i>Jalankan
                        </button>
                        <button type="button" id="btn-terminal-clear" class="btn btn-outline-secondary">
                            <i class="fas fa-eraser mr-1"></i>Bersihkan Output
                        </button>
                    </div>

                    <div>
                        <div class="small text-muted mb-1">Output</div>
                        <pre id="terminal-output" class="mb-0 p-3 rounded border bg-dark text-light" style="min-height: 340px; max-height: 520px; overflow: auto; white-space: pre-wrap;">Belum ada command dijalankan.</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const runUrl = '{{ route('super-admin.terminal.run') }}';
const csrfToken = '{{ csrf_token() }}';
const commandInput = document.getElementById('terminal-command');
const outputBox = document.getElementById('terminal-output');
const runButton = document.getElementById('btn-terminal-run');
const clearButton = document.getElementById('btn-terminal-clear');

function setOutput(content) {
    outputBox.textContent = content;
    outputBox.scrollTop = outputBox.scrollHeight;
}

function setRunningState(isRunning) {
    runButton.disabled = isRunning;
    runButton.innerHTML = isRunning
        ? '<i class="fas fa-spinner fa-spin mr-1"></i>Menjalankan...'
        : '<i class="fas fa-play mr-1"></i>Jalankan';
}

$('.btn-preset-command').on('click', function () {
    const command = $(this).data('command');
    commandInput.value = command;
    commandInput.focus();
});

clearButton.addEventListener('click', function () {
    setOutput('Belum ada command dijalankan.');
});

async function runCommand() {
    const command = commandInput.value.trim();

    if (!command) {
        window.AppAjax.showToast('Isi command terlebih dahulu.', 'warning');
        return;
    }

    setRunningState(true);
    setOutput('$ ' + command + '\n\nMemproses...');

    try {
        const response = await fetch(runUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ command })
        });

        const data = await response.json();
        const header = [
            '$ ' + (data.command ?? command),
            'exit_code: ' + (data.exit_code === null || data.exit_code === undefined ? '-' : data.exit_code),
            'durasi: ' + (data.duration_ms ?? '-') + ' ms',
            ''
        ].join('\n');

        setOutput(header + (data.output ?? '[tidak ada output]'));
        window.AppAjax.showToast(data.message ?? 'Command diproses.', data.success ? 'success' : 'danger');
    } catch (error) {
        setOutput('$ ' + command + '\n\nGagal menjalankan command. Periksa koneksi atau log aplikasi.');
        window.AppAjax.showToast('Gagal menjalankan command.', 'danger');
    } finally {
        setRunningState(false);
    }
}

runButton.addEventListener('click', runCommand);
commandInput.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        runCommand();
    }
});
</script>
@endpush
