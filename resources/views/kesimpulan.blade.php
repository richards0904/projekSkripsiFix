@extends('layout.master')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Kesimpulan & Rekomendasi Paket Bundling</h1>
                </div>
            </div>
        </div>
    </div>
    <section class="content">
        <div class="container-fluid">
            @if(isset($parameter_input_mining) && !empty($parameter_input_mining))
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Parameter Digunakan</h3>
                    </div>
                    <div class="card-body">
                        <p>
                            Minimal Support: <strong>{{ $parameter_input_mining['min_support'] ?? 'N/A' }} %</strong> |
                            Minimal Confidence: <strong>{{ $parameter_input_mining['min_confidence'] ?? 'N/A' }} %</strong> |
                            Tanggal Awal: <strong>{{ $parameter_input_mining['tanggal_awal'] ?? 'N/A' }}</strong> |
                            Tanggal Akhir: <strong>{{ $parameter_input_mining['tanggal_akhir'] ?? 'N/A' }}</strong> |
                        </p>
                    </div>
                </div>
            @endif

            @if(isset($rekomendasi_paket_bundling) && is_array($rekomendasi_paket_bundling) && count($rekomendasi_paket_bundling) > 0 && !isset($rekomendasi_paket_bundling['error']) && !isset($rekomendasi_paket_bundling['info']))
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="card-title">Top Rekomendasi Paket Bundling</h3>
                    </div>
                    <div class="card-body">
                        <table id="tabelRekomendasiPaket" class="table table-bordered table-hover table-striped">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">No.</th>
                                    <th>Nama Paket Rekomendasi</th>
                                    <th style="width: 10%;">Support</th>
                                    <th style="width: 10%;">Confidence</th>
                                    <th style="width: 10%;">Lift</th>
                                    <th>Kesimpulan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rekomendasi_paket_bundling as $index => $paket)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $paket['nama_paket'] }}</td>
                                        <td>{{ number_format($paket['support'] * 100, 2) }}%</td>
                                        <td>{{ number_format($paket['confidence'] * 100, 2) }}%</td>
                                        <td>{{ number_format($paket['lift'], 4) }}</td>
                                        <td>{{ $paket['kesimpulan'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @elseif(isset($rekomendasi_paket_bundling) && isset($rekomendasi_paket_bundling['info']))
                <div class="alert alert-info mt-4">
                    {{ $rekomendasi_paket_bundling['info'] }}
                </div>
            @elseif(isset($rekomendasi_paket_bundling) && isset($rekomendasi_paket_bundling['error']))
                <div class="alert alert-danger mt-4">
                    <strong>Error:</strong> {{ $rekomendasi_paket_bundling['error'] }}
                    @if(isset($rekomendasi_paket_bundling['details']))
                        <pre style="white-space: pre-wrap; word-break: break-all; margin-top:10px;">{{ is_string($rekomendasi_paket_bundling['details']) ? $rekomendasi_paket_bundling['details'] : print_r($rekomendasi_paket_bundling['details'], true) }}</pre>
                    @endif
                </div>
            @else
                 <div class="alert alert-warning mt-4">
                    Belum ada data rekomendasi untuk ditampilkan. Silakan jalankan proses data mining terlebih dahulu.
                </div>
            @endif
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    // Inisialisasi DataTables untuk tabel rekomendasi paket jika ada
    if ($('#tabelRekomendasiPaket').length > 0 && $('#tabelRekomendasiPaket tbody tr').length > 0) {
        $('#tabelRekomendasiPaket').DataTable({
            "responsive": true,
            "lengthChange": false, // Mungkin tidak perlu length change untuk top 10
            "autoWidth": false,
            "searching": false, // Mungkin tidak perlu search untuk top 10
            "paging": false, // Mungkin tidak perlu paging untuk top 10
            "info": false, // Mungkin tidak perlu info untuk top 10
            // "buttons": ["copy", "excel"] // Aktifkan jika perlu
        }); //.buttons().container().appendTo('#tabelRekomendasiPaket_wrapper .col-md-6:eq(0)');
    }
});
</script>
@endpush
