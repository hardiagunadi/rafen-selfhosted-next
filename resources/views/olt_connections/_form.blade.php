@php
    $currentUserRole = auth()->user()->role;
    $showNocOnlyOidFields = $currentUserRole === 'noc';
    $showRebootOidField = in_array($currentUserRole, ['administrator', 'noc'], true);
@endphp

{{-- Section: Identitas OLT --}}
<div class="mf-section">
    <div class="mf-section-header">
        <div class="mf-section-icon" style="background:linear-gradient(140deg,#7c3aed,#a78bfa);">
            <i class="fas fa-network-wired"></i>
        </div>
        <div class="mf-section-title">Identitas OLT</div>
    </div>
    <div class="mf-section-body">
        <div class="mf-row mf-row-4">
            <div class="mf-field">
                <label class="mf-label">Vendor OLT <span class="mf-req">*</span></label>
                <select name="vendor" id="olt-vendor" class="mf-input @error('vendor') mf-input-error @enderror" required>
                    <option value="hsgq" {{ old('vendor', $oltConnection->vendor ?? 'hsgq') === 'hsgq' ? 'selected' : '' }}>HSGQ</option>
                </select>
                @error('vendor')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mf-field">
                <label class="mf-label">Model OLT HSGQ <span class="mf-req">*</span></label>
                <input type="text" name="olt_model" id="olt-model" list="olt-model-options" class="mf-input @error('olt_model') mf-input-error @enderror"
                    value="{{ old('olt_model', $oltConnection->olt_model ?? '') }}" required placeholder="Contoh: HSGQ-E04I (EPON)">
                <datalist id="olt-model-options">
                    @foreach($hsgqModels as $hsgqModel)
                        <option value="{{ $hsgqModel }}"></option>
                    @endforeach
                </datalist>
                @error('olt_model')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mf-field">
                <label class="mf-label">Nama OLT <span class="mf-req">*</span></label>
                <input type="text" name="name" id="olt-name" class="mf-input @error('name') mf-input-error @enderror"
                    value="{{ old('name', $oltConnection->name ?? '') }}" required>
                @error('name')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mf-field">
                <label class="mf-label">Host / IP OLT <span class="mf-req">*</span></label>
                <input type="text" name="host" id="olt-host" class="mf-input @error('host') mf-input-error @enderror"
                    value="{{ old('host', $oltConnection->host ?? '') }}" required placeholder="192.168.1.10">
                @error('host')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function initOltDetection() {
        var detectModelButton = document.getElementById('btn-auto-detect-model');
        var detectOidButton = document.getElementById('btn-auto-detect-oid');
        var modelResultElement = document.getElementById('model-detect-result');
        var oidResultElement = document.getElementById('oid-detect-result');
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');

        if (!detectOidButton || !modelResultElement || !oidResultElement || !csrfMeta) {
            return;
        }

        function setModelResult(message, className) {
            modelResultElement.textContent = message;
            modelResultElement.className = className;
        }

        function setOidResult(message, className) {
            oidResultElement.textContent = message;
            oidResultElement.className = className;
        }

        function valueOf(id) {
            var element = document.getElementById(id);

            return element ? element.value : '';
        }

        function getElement(id) {
            return document.getElementById(id);
        }

        function clearFieldError(id) {
            var element = getElement(id);
            if (!element) {
                return;
            }

            element.classList.remove('mf-input-error');

            var feedback = element.parentElement ? element.parentElement.querySelector('.mf-feedback') : null;
            if (feedback) {
                feedback.textContent = '';
                feedback.style.display = 'none';
            }
        }

        function requireField(id, message, setResult) {
            var element = getElement(id);
            if (!element) {
                setResult(message, 'text-danger d-block mt-2');

                return false;
            }

            if (String(element.value || '').trim() !== '') {
                return true;
            }

            element.classList.add('mf-input-error');
            var feedback = element.parentElement ? element.parentElement.querySelector('.mf-feedback') : null;
            if (feedback) {
                feedback.textContent = message;
                feedback.style.display = 'block';
            }

            setResult(message, 'text-danger d-block mt-2');
            element.focus();

            return false;
        }

        function validateSnmpFields(setResult) {
            clearFieldError('olt-model');

            return requireField('olt-host', 'Host / IP OLT wajib diisi.', setResult)
                && requireField('olt-snmp-port', 'Port SNMP wajib diisi.', setResult)
                && requireField('olt-snmp-version', 'Versi SNMP wajib dipilih.', setResult)
                && requireField('olt-snmp-community', 'SNMP Community wajib diisi.', setResult)
                && requireField('olt-snmp-timeout', 'SNMP Timeout wajib diisi.', setResult)
                && requireField('olt-snmp-retries', 'SNMP Retries wajib diisi.', setResult);
        }

        [
            'olt-model',
            'olt-host',
            'olt-snmp-port',
            'olt-snmp-version',
            'olt-snmp-community',
            'olt-snmp-write-community',
            'olt-snmp-timeout',
            'olt-snmp-retries',
        ].forEach(function (fieldId) {
            var field = getElement(fieldId);
            if (!field) {
                return;
            }

            field.addEventListener('input', function () {
                clearFieldError(fieldId);
            });

            field.addEventListener('change', function () {
                clearFieldError(fieldId);
            });
        });

        function snmpPayload() {
            return {
                vendor: valueOf('olt-vendor'),
                host: valueOf('olt-host'),
                snmp_version: valueOf('olt-snmp-version'),
                snmp_port: valueOf('olt-snmp-port'),
                snmp_community: valueOf('olt-snmp-community'),
                snmp_timeout: valueOf('olt-snmp-timeout'),
                snmp_retries: valueOf('olt-snmp-retries'),
            };
        }

        async function postJson(url, payload, fallbackErrorMessage) {
            var response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfMeta.getAttribute('content'),
                },
                body: JSON.stringify(payload),
            });
            var body = await response.json();
            if (!response.ok || body.status !== 'ok') {
                var validationMessage = null;
                if (body && body.errors && typeof body.errors === 'object') {
                    var errorKeys = Object.keys(body.errors);
                    if (errorKeys.length > 0 && Array.isArray(body.errors[errorKeys[0]]) && body.errors[errorKeys[0]].length > 0) {
                        validationMessage = body.errors[errorKeys[0]][0];
                    }
                }

                throw new Error(validationMessage || body.message || fallbackErrorMessage);
            }

            return body;
        }

        function fillOid(fieldId, value) {
            var input = document.getElementById(fieldId);
            if (input && typeof value === 'string' && value !== '') {
                input.value = value;
            }
        }

        async function detectModelSilently() {
            if (!validateSnmpFields(setModelResult)) {
                return null;
            }

            var payload = snmpPayload();

            if (detectModelButton) {
                detectModelButton.disabled = true;
                detectModelButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Deteksi Model...';
            }
            setModelResult('Mendeteksi model perangkat dari SNMP...', 'text-info d-block mt-1');

            try {
                var body = await postJson(
                    '{{ route('olt-connections.auto-detect-model') }}',
                    payload,
                    'Deteksi model gagal.'
                );
                var data = body.data || {};
                if (data.matched_model) {
                    var modelInput = document.getElementById('olt-model');
                    if (modelInput) {
                        modelInput.value = data.matched_model;
                    }
                    clearFieldError('olt-model');
                    setModelResult('Model terdeteksi: ' + data.matched_model + '.', 'text-success d-block mt-1');
                } else {
                    var sysDescr = data.sys_descr ? ' (' + data.sys_descr + ')' : '';
                    setModelResult(
                        'SNMP terhubung' + sysDescr + ', tetapi model belum ada pada profil. Isi model sesuai perangkat.',
                        'text-warning d-block mt-1'
                    );
                }

                return data;
            } catch (error) {
                var message = error instanceof Error ? error.message : 'Deteksi model gagal.';
                setModelResult(message, 'text-danger d-block mt-1');

                return null;
            } finally {
                if (detectModelButton) {
                    detectModelButton.disabled = false;
                    detectModelButton.innerHTML = '<i class="fas fa-microchip mr-1"></i>Deteksi Model dari SNMP';
                }
            }
        }

        if (detectModelButton) {
            detectModelButton.addEventListener('click', async function () {
                await detectModelSilently();
            });
        }

        detectOidButton.addEventListener('click', async function () {
            if (!validateSnmpFields(setOidResult)) {
                return;
            }

            var payload = snmpPayload();
            payload.olt_model = valueOf('olt-model');

            if (!payload.olt_model) {
                var detectedModelData = await detectModelSilently();
                if (detectedModelData && detectedModelData.matched_model) {
                    payload.olt_model = detectedModelData.matched_model;
                }
            }

            if (!payload.olt_model) {
                var modelInput = getElement('olt-model');
                if (modelInput) {
                    modelInput.classList.add('mf-input-error');
                    var feedback = modelInput.parentElement ? modelInput.parentElement.querySelector('.mf-feedback') : null;
                    if (feedback) {
                        feedback.textContent = 'Model OLT HSGQ wajib dipilih.';
                        feedback.style.display = 'block';
                    }
                    modelInput.focus();
                }
                setOidResult('Isi model OLT atau lakukan deteksi model terlebih dahulu.', 'text-danger d-block mt-2');

                return;
            }

            detectOidButton.disabled = true;
            detectOidButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Deteksi OID...';
            setOidResult('Menghubungi OLT dan mendeteksi OID sesuai model...', 'text-info d-block mt-2');

            try {
                var body = await postJson(
                    '{{ route('olt-connections.auto-detect-oid') }}',
                    payload,
                    'Auto detect OID gagal.'
                );

                var oids = body.data && body.data.oids ? body.data.oids : {};
                fillOid('oid-serial', oids.oid_serial || '');
                fillOid('oid-onu-name', oids.oid_onu_name || '');
                fillOid('oid-rx-onu', oids.oid_rx_onu || '');
                fillOid('oid-tx-onu', oids.oid_tx_onu || '');
                fillOid('oid-rx-olt', oids.oid_rx_olt || '');
                fillOid('oid-tx-olt', oids.oid_tx_olt || '');
                fillOid('oid-distance', oids.oid_distance || '');
                fillOid('oid-status', oids.oid_status || '');
                fillOid('oid-reboot-onu', oids.oid_reboot_onu || '');

                var detectedFields = body.data && typeof body.data.detected_fields === 'number'
                    ? body.data.detected_fields
                    : 0;
                setOidResult('OID berhasil diisi otomatis. Field terdeteksi: ' + detectedFields + '.', 'text-success d-block mt-2');
            } catch (error) {
                var message = error instanceof Error ? error.message : 'Auto detect OID gagal.';
                setOidResult(message, 'text-danger d-block mt-2');
            } finally {
                detectOidButton.disabled = false;
                detectOidButton.innerHTML = '<i class="fas fa-magic mr-1"></i>Auto Detect OID Dari Model';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOltDetection);
    } else {
        initOltDetection();
    }
})();
</script>

{{-- Section: SNMP --}}
<div class="mf-section">
    <div class="mf-section-header">
        <div class="mf-section-icon" style="background:linear-gradient(140deg,#0369a1,#0ea5e9);">
            <i class="fas fa-satellite-dish"></i>
        </div>
        <div class="mf-section-title">Konfigurasi SNMP</div>
    </div>
    <div class="mf-section-body">
        <div class="mf-row mf-row-4">
            <div class="mf-field">
                <label class="mf-label">SNMP Version <span class="mf-req">*</span></label>
                <select name="snmp_version" id="olt-snmp-version" class="mf-input @error('snmp_version') mf-input-error @enderror" required>
                    <option value="2c" {{ old('snmp_version', $oltConnection->snmp_version ?? '2c') === '2c' ? 'selected' : '' }}>v2c</option>
                </select>
                @error('snmp_version')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mf-field">
                <label class="mf-label">SNMP Port <span class="mf-req">*</span></label>
                <input type="number" min="1" max="65535" name="snmp_port" id="olt-snmp-port" class="mf-input @error('snmp_port') mf-input-error @enderror"
                    value="{{ old('snmp_port', $oltConnection->snmp_port ?? 161) }}" required>
                @error('snmp_port')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mf-field">
                <label class="mf-label">SNMP Timeout (detik) <span class="mf-req">*</span></label>
                <input type="number" min="1" max="30" name="snmp_timeout" id="olt-snmp-timeout" class="mf-input @error('snmp_timeout') mf-input-error @enderror"
                    value="{{ old('snmp_timeout', $oltConnection->snmp_timeout ?? 5) }}" required>
                @error('snmp_timeout')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mf-field">
                <label class="mf-label">SNMP Retries <span class="mf-req">*</span></label>
                <input type="number" min="0" max="5" name="snmp_retries" id="olt-snmp-retries" class="mf-input @error('snmp_retries') mf-input-error @enderror"
                    value="{{ old('snmp_retries', $oltConnection->snmp_retries ?? 1) }}" required>
                @error('snmp_retries')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="mf-row mf-row-3">
            <div class="mf-field">
                <label class="mf-label">SNMP Read Community <span class="mf-req">*</span></label>
                <input type="text" name="snmp_community" id="olt-snmp-community" class="mf-input @error('snmp_community') mf-input-error @enderror"
                    value="{{ old('snmp_community', $oltConnection->snmp_community ?? '') }}" required>
                @error('snmp_community')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mf-field">
                <label class="mf-label">SNMP Write Community <span class="mf-opt">Opsional</span></label>
                <input type="text" name="snmp_write_community" id="olt-snmp-write-community" class="mf-input @error('snmp_write_community') mf-input-error @enderror"
                    value="{{ old('snmp_write_community', $oltConnection->snmp_write_community ?? '') }}" placeholder="Opsional untuk aksi tulis SNMP">
                @error('snmp_write_community')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mf-field" style="justify-content:flex-end;padding-bottom:.25rem;">
                <label class="mf-switch">
                    <input type="checkbox" id="is_active" name="is_active" value="1"
                        {{ old('is_active', $oltConnection->is_active ?? true) ? 'checked' : '' }}>
                    <span class="mf-switch-track"></span>
                    <span class="mf-switch-label">Aktifkan OLT Monitoring</span>
                </label>
            </div>
        </div>
        <div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-auto-detect-model">
                <i class="fas fa-microchip mr-1"></i>Deteksi Model dari SNMP
            </button>
            <button type="button" class="btn btn-outline-info btn-sm" id="btn-auto-detect-oid">
                <i class="fas fa-magic mr-1"></i>Auto Detect OID Dari Model
            </button>
            <small class="text-muted d-block mt-2" id="model-detect-result">
                Pilih dari daftar atau isi model manual sesuai perangkat OLT.
            </small>
            <small class="text-muted d-block mt-2" id="oid-detect-result">
                Deteksi model dahulu atau isi model manual, lalu klik auto detect OID.
            </small>
        </div>
    </div>
</div>

{{-- Section: Mapping OID SNMP --}}
<div class="mf-section">
    <div class="mf-section-header">
        <div class="mf-section-icon" style="background:linear-gradient(140deg,#065f46,#10b981);">
            <i class="fas fa-code-branch"></i>
        </div>
        <div class="mf-section-title">Mapping OID SNMP HSGQ</div>
    </div>
    <div class="mf-section-body">
        <p class="mf-hint" style="margin-bottom:.25rem;">
            Isi OID sesuai MIB OLT HSGQ Anda. Format wajib angka dan titik, contoh: <code>1.3.6.1.4.1.12345.1.1</code>
        </p>
        <div class="mf-row">
            @if($showNocOnlyOidFields)
                <div class="mf-field">
                    <label class="mf-label">OID MAC / Identifier ONU</label>
                    <input type="text" name="oid_serial" id="oid-serial" class="mf-input @error('oid_serial') mf-input-error @enderror"
                        value="{{ old('oid_serial', $oltConnection->oid_serial ?? '') }}" placeholder="1.3.6.x.x.x">
                    @error('oid_serial')<div class="mf-feedback">{{ $message }}</div>@enderror
                </div>
            @endif
            <div class="mf-field">
                <label class="mf-label">OID Nama ONU</label>
                <input type="text" name="oid_onu_name" id="oid-onu-name" class="mf-input @error('oid_onu_name') mf-input-error @enderror"
                    value="{{ old('oid_onu_name', $oltConnection->oid_onu_name ?? '') }}" placeholder="1.3.6.x.x.x">
                @error('oid_onu_name')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="mf-row">
            @if($showNocOnlyOidFields)
                <div class="mf-field">
                    <label class="mf-label">OID Rx ONU (dBm)</label>
                    <input type="text" name="oid_rx_onu" id="oid-rx-onu" class="mf-input @error('oid_rx_onu') mf-input-error @enderror"
                        value="{{ old('oid_rx_onu', $oltConnection->oid_rx_onu ?? '') }}" placeholder="1.3.6.x.x.x">
                    @error('oid_rx_onu')<div class="mf-feedback">{{ $message }}</div>@enderror
                </div>
            @endif
            <div class="mf-field">
                <label class="mf-label">OID Tx ONU (dBm)</label>
                <input type="text" name="oid_tx_onu" id="oid-tx-onu" class="mf-input @error('oid_tx_onu') mf-input-error @enderror"
                    value="{{ old('oid_tx_onu', $oltConnection->oid_tx_onu ?? '') }}" placeholder="1.3.6.x.x.x">
                @error('oid_tx_onu')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="mf-row">
            <div class="mf-field">
                <label class="mf-label">OID Rx OLT (dBm)</label>
                <input type="text" name="oid_rx_olt" id="oid-rx-olt" class="mf-input @error('oid_rx_olt') mf-input-error @enderror"
                    value="{{ old('oid_rx_olt', $oltConnection->oid_rx_olt ?? '') }}" placeholder="1.3.6.x.x.x">
                @error('oid_rx_olt')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mf-field">
                <label class="mf-label">OID Tx OLT (dBm)</label>
                <input type="text" name="oid_tx_olt" id="oid-tx-olt" class="mf-input @error('oid_tx_olt') mf-input-error @enderror"
                    value="{{ old('oid_tx_olt', $oltConnection->oid_tx_olt ?? '') }}" placeholder="1.3.6.x.x.x">
                @error('oid_tx_olt')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        @if($showNocOnlyOidFields || $showRebootOidField)
            <div class="mf-row">
                @if($showNocOnlyOidFields)
                    <div class="mf-field">
                        <label class="mf-label">OID Distance (m)</label>
                        <input type="text" name="oid_distance" id="oid-distance" class="mf-input @error('oid_distance') mf-input-error @enderror"
                            value="{{ old('oid_distance', $oltConnection->oid_distance ?? '') }}" placeholder="1.3.6.x.x.x">
                        @error('oid_distance')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">OID Status ONU</label>
                        <input type="text" name="oid_status" id="oid-status" class="mf-input @error('oid_status') mf-input-error @enderror"
                            value="{{ old('oid_status', $oltConnection->oid_status ?? '') }}" placeholder="1.3.6.x.x.x">
                        @error('oid_status')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                @endif
                @if($showRebootOidField)
                    <div class="mf-field">
                        <label class="mf-label">OID Reboot ONU</label>
                        <input type="text" name="oid_reboot_onu" id="oid-reboot-onu" class="mf-input @error('oid_reboot_onu') mf-input-error @enderror"
                            value="{{ old('oid_reboot_onu', $oltConnection->oid_reboot_onu ?? '') }}" placeholder="1.3.6.x.x.x">
                        @error('oid_reboot_onu')<div class="mf-feedback">{{ $message }}</div>@enderror
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

@if($showRebootOidField)
{{-- Section: Akses CLI OLT --}}
<div class="mf-section">
    <div class="mf-section-header">
        <div class="mf-section-icon" style="background:linear-gradient(140deg,#374151,#6b7280);">
            <i class="fas fa-terminal"></i>
        </div>
        <div class="mf-section-title">Akses CLI OLT (Telnet / SSH)</div>
    </div>
    <div class="mf-section-body">
        <p class="mf-hint" style="margin-bottom:.25rem;">
            Digunakan untuk konfigurasi WiFi ONU (SSID &amp; password) dan operasi lain yang tidak tersedia via SNMP.
            Biarkan protokol <strong>Tidak Digunakan</strong> jika fitur ini tidak diperlukan.
        </p>
        <div class="mf-row mf-row-4">
            <div class="mf-field">
                <label class="mf-label">Protokol CLI</label>
                <select name="cli_protocol" id="cli-protocol" class="mf-input @error('cli_protocol') mf-input-error @enderror">
                    <option value="none" {{ old('cli_protocol', $oltConnection->cli_protocol ?? 'none') === 'none' ? 'selected' : '' }}>Tidak Digunakan</option>
                    <option value="telnet" {{ old('cli_protocol', $oltConnection->cli_protocol ?? '') === 'telnet' ? 'selected' : '' }}>Telnet</option>
                    <option value="ssh" {{ old('cli_protocol', $oltConnection->cli_protocol ?? '') === 'ssh' ? 'selected' : '' }}>SSH</option>
                </select>
                @error('cli_protocol')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mf-field" id="cli-port-group">
                <label class="mf-label">Port CLI</label>
                <input type="number" min="1" max="65535" name="cli_port" id="cli-port" class="mf-input @error('cli_port') mf-input-error @enderror"
                    value="{{ old('cli_port', $oltConnection->cli_port ?? '') }}"
                    placeholder="23 / 2211">
                @error('cli_port')<div class="mf-feedback">{{ $message }}</div>@enderror
                <span class="mf-hint">Telnet: 23, SSH: 2211</span>
            </div>
            <div class="mf-field" id="cli-username-group">
                <label class="mf-label">Username CLI</label>
                <input type="text" name="cli_username" id="cli-username" class="mf-input @error('cli_username') mf-input-error @enderror"
                    value="{{ old('cli_username', $oltConnection->cli_username ?? '') }}" autocomplete="off">
                @error('cli_username')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mf-field" id="cli-password-group">
                <label class="mf-label">Password CLI</label>
                <div style="display:flex;gap:0;">
                    <input type="password" name="cli_password" id="cli-password" class="mf-input @error('cli_password') mf-input-error @enderror"
                        style="border-radius:9px 0 0 9px;"
                        value="{{ old('cli_password', ($oltConnection->cli_password ?? null) ? '••••••••' : '') }}"
                        autocomplete="new-password" placeholder="{{ ($oltConnection->cli_password ?? null) ? 'Kosongkan jika tidak diubah' : '' }}">
                    <button type="button" class="btn btn-outline-secondary" id="btn-toggle-cli-password" title="Tampilkan/sembunyikan password"
                        style="border-radius:0 9px 9px 0;border-left:none;height:38px;flex-shrink:0;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                @error('cli_password')<div class="mf-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    function initCliConfig() {
        var protocolSelect = document.getElementById('cli-protocol');
        var portInput = document.getElementById('cli-port');
        var togglePasswordBtn = document.getElementById('btn-toggle-cli-password');
        var passwordInput = document.getElementById('cli-password');

        if (!protocolSelect) return;

        function setDefaultPort(protocol) {
            if (!portInput) return;
            var current = String(portInput.value || '').trim();
            if (protocol === 'telnet' && (current === '' || current === '2211')) {
                portInput.value = '23';
            } else if (protocol === 'ssh' && (current === '' || current === '23')) {
                portInput.value = '2211';
            } else if (protocol === 'none') {
                portInput.value = '';
            }
        }

        protocolSelect.addEventListener('change', function () {
            setDefaultPort(this.value);
        });

        if (togglePasswordBtn && passwordInput) {
            togglePasswordBtn.addEventListener('click', function () {
                var isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                this.querySelector('i').className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCliConfig);
    } else {
        initCliConfig();
    }
})();
</script>
@endif
