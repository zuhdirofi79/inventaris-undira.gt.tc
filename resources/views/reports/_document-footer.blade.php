<section class="rpt-grand">
    <div class="rpt-grand-title">Grand Total</div>
    <table>
        <tr>
            <td>Grand Total Inventaris: <strong>{{ number_format($summary['total_items'] ?? 0) }}</strong></td>
            <td>Grand Total Aktif: <strong>{{ number_format($summary['aktif'] ?? 0) }}</strong></td>
            <td>Grand Total Rusak: <strong>{{ number_format($summary['rusak'] ?? 0) }}</strong></td>
        </tr>
        <tr>
            <td>Grand Total Maintenance: <strong>{{ number_format($summary['maintenance'] ?? 0) }}</strong></td>
            <td>Grand Total Dipinjam: <strong>{{ number_format($summary['dipinjam'] ?? 0) }}</strong></td>
            <td>Grand Total Nilai Aset: <strong>Rp {{ number_format($summary['nilai_aset'] ?? 0, 0, ',', '.') }}</strong></td>
        </tr>
    </table>
</section>
