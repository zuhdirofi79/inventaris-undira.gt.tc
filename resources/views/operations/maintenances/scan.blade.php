@extends('layouts.app')

@section('title', 'Scan QR Maintenance - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('maintenances.index') }}">Maintenance</a></li>
    <li class="breadcrumb-item active">Scan QR</li>
@endsection

@section('content')
<x-ui.page-header
    title="Scan Inventory QR Code"
    subtitle="Arahkan kamera ke QR pada label inventaris. QR berisi nomor inventaris saja."
    icon="bi bi-qr-code-scan"
/>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card card-undira h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Kamera</h5>
                <button type="button" class="btn btn-undira btn-lg" id="btnToggleCamera">
                    <i class="fas fa-camera me-1"></i>Mulai Kamera
                </button>
            </div>
            <div class="card-body">
                <div id="qrReader" class="bg-light border rounded" style="min-height:280px;"></div>
                <p class="small text-muted mt-2 mb-0">Desktop: webcam. Mobile: kamera belakang (environment). Izinkan akses kamera di browser.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card card-undira mb-3">
            <div class="card-header"><h5 class="mb-0">Atau ketik / USB scanner</h5></div>
            <div class="card-body">
                <label class="form-label">Nomor Inventaris</label>
                <div class="input-group input-group-lg">
                    <input type="text" id="scanCode" class="form-control" placeholder="Contoh: CBB-HIBAH-26-EL-LPT-000001" autocomplete="off" autofocus>
                    <button type="button" class="btn btn-undira" id="btnLookup"><i class="fas fa-search"></i></button>
                </div>
                <div id="scanStatus" class="alert alert-light border mt-3 mb-0 d-none"></div>
            </div>
        </div>

        <div id="assetCard" class="card card-undira d-none">
            <div class="card-header"><h5 class="mb-0">Inventaris Teridentifikasi</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3 text-center">
                        <img id="assetPhoto" src="" alt="" class="img-fluid rounded border d-none" style="max-height:140px;">
                        <div id="assetPhotoEmpty" class="text-muted small py-4">Tanpa foto</div>
                    </div>
                    <div class="col-md-9">
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4">No. Inventaris</dt><dd class="col-sm-8"><code id="fNumber"></code></dd>
                            <dt class="col-sm-4">Nama</dt><dd class="col-sm-8" id="fName"></dd>
                            <dt class="col-sm-4">Kategori</dt><dd class="col-sm-8" id="fCategory"></dd>
                            <dt class="col-sm-4">Sub Kategori</dt><dd class="col-sm-8" id="fSub"></dd>
                            <dt class="col-sm-4">Kampus</dt><dd class="col-sm-8" id="fCampus"></dd>
                            <dt class="col-sm-4">Gedung / Lantai / Ruang</dt><dd class="col-sm-8" id="fLocation"></dd>
                            <dt class="col-sm-4">PIC</dt><dd class="col-sm-8" id="fPic"></dd>
                            <dt class="col-sm-4">Status</dt><dd class="col-sm-8" id="fStatus"></dd>
                            <dt class="col-sm-4">Tahun / Pengadaan</dt><dd class="col-sm-8" id="fProc"></dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <a href="#" id="btnContinue" class="btn btn-undira btn-lg flex-grow-1">
                    <i class="bi bi-wrench-adjustable me-1"></i>Lanjut Form Pemeliharaan
                </a>
                <a href="{{ route('maintenances.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const input = document.getElementById('scanCode');
    const status = document.getElementById('scanStatus');
    const toggleBtn = document.getElementById('btnToggleCamera');
    const card = document.getElementById('assetCard');
    let html5QrCode = null;
    let cameraOn = false;
    let lookingUp = false;

    function showStatus(msg, type) {
        status.className = 'alert alert-' + type + ' mt-3 mb-0';
        status.textContent = msg;
        status.classList.remove('d-none');
    }

    function fillCard(item) {
        document.getElementById('fNumber').textContent = item.inventory_number || '—';
        document.getElementById('fName').textContent = item.name || '—';
        document.getElementById('fCategory').textContent = item.category || '—';
        document.getElementById('fSub').textContent = item.sub_category || '—';
        document.getElementById('fCampus').textContent = item.campus || '—';
        document.getElementById('fLocation').textContent = [item.building, item.floor, item.room].filter(Boolean).join(' / ') || '—';
        document.getElementById('fPic').textContent = item.pic || '—';
        document.getElementById('fStatus').textContent = item.status_label || item.status || '—';
        document.getElementById('fProc').textContent = [item.purchase_year || '—', item.procurement_type || '—'].join(' · ');
        const img = document.getElementById('assetPhoto');
        const empty = document.getElementById('assetPhotoEmpty');
        if (item.photo_url) {
            img.src = item.photo_url;
            img.classList.remove('d-none');
            empty.classList.add('d-none');
        } else {
            img.classList.add('d-none');
            empty.classList.remove('d-none');
        }
        document.getElementById('btnContinue').href = item.create_url;
        card.classList.remove('d-none');
    }

    function lookup(code) {
        code = (code || '').trim();
        if (!code || lookingUp) return;
        lookingUp = true;
        showStatus('Mencari: ' + code + ' …', 'info');

        $.ajax({
            url: '{{ route('maintenances.scan.lookup') }}',
            method: 'POST',
            data: { code: code, _token: '{{ csrf_token() }}' },
            success: function (res) {
                lookingUp = false;
                if (res.found && res.item) {
                    showStatus('Inventaris ditemukan.', 'success');
                    fillCard(res.item);
                    stopCamera();
                    return;
                }
                showStatus('Inventaris tidak ditemukan.', 'danger');
            },
            error: function (xhr) {
                lookingUp = false;
                showStatus(xhr.responseJSON?.message || 'Inventaris tidak ditemukan.', 'danger');
                input.select();
            }
        });
    }

    document.getElementById('btnLookup').addEventListener('click', function () { lookup(input.value); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); lookup(input.value); }
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
                { fps: 10, qrbox: { width: 240, height: 240 } },
                function (decoded) {
                    if (lookingUp) return;
                    input.value = decoded;
                    lookup(decoded);
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
})();
</script>
@endpush
