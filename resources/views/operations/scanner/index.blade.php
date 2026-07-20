@extends('layouts.app')

@section('title', 'Scan Barcode - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item">Operasi</li>
    <li class="breadcrumb-item active">Scan Barcode</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-barcode me-2"></i>Scan Barcode / QR</h1>
    <p class="text-muted mb-0 small">USB scanner, kamera HP/laptop, atau ketik nomor inventaris — langsung ke detail aset.</p>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card card-undira h-100">
            <div class="card-header"><h5 class="mb-0">Input / USB Scanner</h5></div>
            <div class="card-body">
                <form id="scannerForm" method="POST" action="{{ route('scanner.lookup') }}">
                    @csrf
                    <label class="form-label">Nomor Inventaris / Barcode</label>
                    <input type="text" name="code" id="scanCode" class="form-control form-control-lg"
                           placeholder="Scan atau ketik lalu Enter" autocomplete="off" autofocus required>
                    <div class="form-text">Scanner USB (keyboard wedge) cukup scan — fokus otomatis di kolom ini.</div>
                    <button type="submit" class="btn btn-undira mt-3 w-100">
                        <i class="fas fa-search me-1"></i>Cari &amp; Buka Detail
                    </button>
                </form>
                <div id="scanStatus" class="alert alert-light border mt-3 mb-0 d-none"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-undira h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Kamera</h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnToggleCamera">
                    <i class="fas fa-camera me-1"></i>Mulai Kamera
                </button>
            </div>
            <div class="card-body">
                <div id="qrReader" class="bg-light border rounded" style="min-height:260px;"></div>
                <p class="small text-muted mt-2 mb-0">Mendukung kamera Android, iPhone, dan laptop. Izinkan akses kamera di browser.</p>
            </div>
        </div>
    </div>
</div>

<div class="card card-undira mt-3">
    <div class="card-body small text-muted">
        Setelah ditemukan, halaman detail inventaris memungkinkan: pinjam, maintenance, mutasi, stock opname, cetak ulang label, dan riwayat.
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const input = document.getElementById('scanCode');
    const form = document.getElementById('scannerForm');
    const status = document.getElementById('scanStatus');
    const toggleBtn = document.getElementById('btnToggleCamera');
    let html5QrCode = null;
    let cameraOn = false;
    let lookingUp = false;

    function showStatus(msg, type) {
        status.className = 'alert alert-' + type + ' mt-3 mb-0';
        status.textContent = msg;
        status.classList.remove('d-none');
    }

    function lookup(code) {
        code = (code || '').trim();
        if (!code || lookingUp) return;
        lookingUp = true;
        showStatus('Mencari: ' + code + ' ...', 'info');

        $.ajax({
            url: '{{ route('scanner.lookup') }}',
            method: 'POST',
            data: { code: code, _token: '{{ csrf_token() }}' },
            success: function (res) {
                if (res.redirect) {
                    showStatus('Ditemukan. Membuka detail...', 'success');
                    window.location.href = res.redirect;
                    return;
                }
                lookingUp = false;
            },
            error: function (xhr) {
                lookingUp = false;
                const msg = xhr.responseJSON?.message || 'Inventaris tidak ditemukan.';
                showStatus(msg, 'danger');
                input.select();
            }
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        lookup(input.value);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            lookup(input.value);
        }
    });

    async function startCamera() {
        if (!window.Html5Qrcode) {
            showStatus('Library kamera gagal dimuat.', 'warning');
            return;
        }
        html5QrCode = new Html5Qrcode('qrReader');
        try {
            await html5QrCode.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 220, height: 220 } },
                function (decoded) {
                    if (lookingUp) return;
                    input.value = decoded;
                    lookup(decoded);
                    stopCamera();
                }
            );
            cameraOn = true;
            toggleBtn.innerHTML = '<i class="fas fa-stop me-1"></i>Stop Kamera';
        } catch (err) {
            showStatus('Tidak dapat mengakses kamera: ' + (err?.message || err), 'warning');
        }
    }

    async function stopCamera() {
        if (html5QrCode && cameraOn) {
            try { await html5QrCode.stop(); } catch (e) {}
            try { html5QrCode.clear(); } catch (e) {}
        }
        cameraOn = false;
        toggleBtn.innerHTML = '<i class="fas fa-camera me-1"></i>Mulai Kamera';
    }

    toggleBtn.addEventListener('click', function () {
        if (cameraOn) stopCamera();
        else startCamera();
    });

    input.focus();
})();
</script>
@endpush
