{{-- Dual QR verification block (borrower + Biro PPPA). Expects $signature. --}}
@php
    $signature = $signature ?? [];
    $borrowerName = $signature['borrowerName'] ?? '—';
    $approverName = $signature['approverName'] ?? null;
    $decisionLabel = $signature['decisionLabel'] ?? 'Disetujui oleh';
    $borrowerQr = $signature['borrowerQrDataUri'] ?? null;
    $approvalQr = $signature['approvalQrDataUri'] ?? null;
@endphp
<table class="signatures">
    <tr>
        <td class="sign-col">
            <div class="sign-head">PEMINJAM</div>
            @if($borrowerQr)
                <div class="sign-qr"><img src="{{ $borrowerQr }}" alt="QR Verifikasi Peminjam"></div>
            @endif
            <div class="sign-caption">Pindai untuk verifikasi transaksi</div>
            <div class="sign-name">{{ $borrowerName }}</div>
        </td>
        <td class="sign-gap">&nbsp;</td>
        <td class="sign-col">
            <div class="sign-head">BIRO PPPA<br><span class="sign-subhead">Universitas Dian Nusantara</span></div>
            @if($approvalQr)
                <div class="sign-qr"><img src="{{ $approvalQr }}" alt="QR Verifikasi Biro PPPA"></div>
            @endif
            <div class="sign-caption">Pindai untuk verifikasi keputusan</div>
            <div class="sign-name">
                <span class="sign-decision">{{ $decisionLabel }}:</span><br>
                {{ $approverName ?: 'Tidak tercatat' }}
            </div>
        </td>
    </tr>
</table>
