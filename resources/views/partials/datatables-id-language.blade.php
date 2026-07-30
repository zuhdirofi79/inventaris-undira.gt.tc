{{--
  Emits: const datatablesIdLanguage = {...};
  Source: public/vendor/datatables/i18n/id.json (local — no CDN/CORS)
--}}
@php
    $datatablesIdLanguagePath = public_path('vendor/datatables/i18n/id.json');
    $datatablesIdLanguage = null;

    if (is_readable($datatablesIdLanguagePath)) {
        $decoded = json_decode((string) file_get_contents($datatablesIdLanguagePath), true);
        if (is_array($decoded)) {
            $datatablesIdLanguage = $decoded;
        }
    }

    if (! is_array($datatablesIdLanguage)) {
        $datatablesIdLanguage = [
            'emptyTable' => 'Tidak ada data yang tersedia pada tabel ini',
            'info' => 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri',
            'infoEmpty' => 'Menampilkan 0 sampai 0 dari 0 entri',
            'infoFiltered' => '(disaring dari _MAX_ entri keseluruhan)',
            'lengthMenu' => 'Tampilkan _MENU_ entri',
            'loadingRecords' => 'Sedang memuat...',
            'processing' => 'Sedang memproses...',
            'search' => 'Cari:',
            'zeroRecords' => 'Tidak ditemukan data yang sesuai',
            'paginate' => [
                'first' => 'Pertama',
                'last' => 'Terakhir',
                'next' => 'Selanjutnya',
                'previous' => 'Sebelumnya',
            ],
        ];
    }
@endphp
const datatablesIdLanguage = @json($datatablesIdLanguage);
