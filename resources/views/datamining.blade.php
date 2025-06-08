@extends('layout.master')
@push('styles')
<style>
    /* Sembunyikan panah untuk browser Webkit (Chrome, Safari, Edge, Opera) */
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    /* Sembunyikan panah untuk browser Firefox */
    input[type=number] {
      -moz-appearance: textfield;
    }
</style>
@endpush
@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Data Mining</h1>
                </div>
            </div>
        </div>
    </div>
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Parameter Data Mining</h3>
                        </div>
                        {{-- Pastikan action mengarah ke rute yang akan memproses form --}}
                        <form action="{{ route('datamining.process') }}" method="POST" id="prosesMiningForm">
                            @csrf
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="min_support">Minimal Support (%)</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control @error('min_support') is-invalid @enderror" id="min_support" name="min_support" placeholder="Contoh: 3" step="any" value="{{ old('min_support', $input_sebelumnya['min_support'] ?? 6) }}" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                            @error('min_support')
                                                <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="min_confidence">Minimal Confidence (%)</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control @error('min_confidence') is-invalid @enderror" id="min_confidence" name="min_confidence" placeholder="Contoh: 50" step="any" value="{{ old('min_confidence', $input_sebelumnya['min_confidence'] ?? 60) }}" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                            @error('min_confidence')
                                                <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="tanggal_awal">Tanggal Awal</label>
                                            <input type="date" class="form-control @error('tanggal_awal') is-invalid @enderror @if($errors->has('tanggal_custom')) is-invalid @endif" id="tanggal_awal" name="tanggal_awal" value="{{ old('tanggal_awal') }}">
                                            @error('tanggal_awal')
                                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="tanggal_akhir">Tanggal Akhir</label>
                                            <input type="date" class="form-control @error('tanggal_akhir') is-invalid @enderror @if($errors->has('tanggal_custom')) is-invalid @endif" id="tanggal_akhir" name="tanggal_akhir" value="{{ old('tanggal_akhir') }}">
                                            @error('tanggal_akhir')
                                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                {{-- Menampilkan error validasi tanggal kustom dari controller --}}
                                @if ($errors->has('tanggal_custom'))
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <span class="text-danger d-block" role="alert">
                                                <strong>{{ $errors->first('tanggal_custom') }}</strong>
                                            </span>
                                        </div>
                                    </div>
                                @endif
                                <div id="miningProgressContainer" style="display: none; margin-top: 15px;">
                                    <div class="alert alert-info">
                                        <i class="fas fa-spinner fa-spin"></i> Sedang memproses data mining, mohon tunggu... Ini mungkin memakan waktu beberapa saat.
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" id="prosesMiningButton" class="btn btn-success">Proses Mining</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>



{{-- --- AWAL BAGIAN HASIL MINING DENGAN TABS --- --}}
            @if(isset($hasil_mining_terorganisir) && is_array($hasil_mining_terorganisir) && !isset($hasil_mining_terorganisir['error']))
                @php
                    $totalAturan = 0;
                    foreach($hasil_mining_terorganisir as $key => $aturanGrup) {
                        if(is_array($aturanGrup)) $totalAturan += count($aturanGrup);
                    }
                @endphp

                @if($totalAturan > 0)
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Hasil Data Mining (Total: {{ $totalAturan }} Aturan)</h3>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs" id="hasilMiningTab" role="tablist">
                                @if(count($hasil_mining_terorganisir['1_item_antecedent']) > 0)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="tab1-tab" data-toggle="tab" data-target="#tab1" type="button" role="tab" aria-controls="tab1" aria-selected="true">1 Item &rarr; 1 Item ({{ count($hasil_mining_terorganisir['1_item_antecedent']) }})</button>
                                </li>
                                @endif
                                @if(count($hasil_mining_terorganisir['2_items_antecedent']) > 0)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link @if(count($hasil_mining_terorganisir['1_item_antecedent']) == 0) active @endif" id="tab2-tab" data-toggle="tab" data-target="#tab2" type="button" role="tab" aria-controls="tab2" aria-selected="{{ count($hasil_mining_terorganisir['1_item_antecedent']) == 0 ? 'true' : 'false' }}">2 Item &rarr; 1 Item ({{ count($hasil_mining_terorganisir['2_items_antecedent']) }})</button>
                                </li>
                                @endif
                                @if(count($hasil_mining_terorganisir['3_items_antecedent']) > 0)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link @if(count($hasil_mining_terorganisir['1_item_antecedent']) == 0 && count($hasil_mining_terorganisir['2_items_antecedent']) == 0) active @endif" id="tab3-tab" data-toggle="tab" data-target="#tab3" type="button" role="tab" aria-controls="tab3" aria-selected="{{ (count($hasil_mining_terorganisir['1_item_antecedent']) == 0 && count($hasil_mining_terorganisir['2_items_antecedent']) == 0) ? 'true' : 'false' }}">3 Item &rarr; 1 Item ({{ count($hasil_mining_terorganisir['3_items_antecedent']) }})</button>
                                </li>
                                @endif
                                @if(count($hasil_mining_terorganisir['4_items_antecedent']) > 0)
                                <li class="nav-item" role="presentation">
                                     <button class="nav-link @if(count($hasil_mining_terorganisir['1_item_antecedent']) == 0 && count($hasil_mining_terorganisir['2_items_antecedent']) == 0 && count($hasil_mining_terorganisir['3_items_antecedent']) == 0) active @endif" id="tab4-tab" data-toggle="tab" data-target="#tab4" type="button" role="tab" aria-controls="tab4" aria-selected="{{ (count($hasil_mining_terorganisir['1_item_antecedent']) == 0 && count($hasil_mining_terorganisir['2_items_antecedent']) == 0 && count($hasil_mining_terorganisir['3_items_antecedent']) == 0) ? 'true' : 'false' }}">4 Item &rarr; 1 Item ({{ count($hasil_mining_terorganisir['4_items_antecedent']) }})</button>
                                </li>
                                @endif
                                 @if(count($hasil_mining_terorganisir['lainnya_antecedent']) > 0)
                                <li class="nav-item" role="presentation">
                                     <button class="nav-link @if(count($hasil_mining_terorganisir['1_item_antecedent']) == 0 && count($hasil_mining_terorganisir['2_items_antecedent']) == 0 && count($hasil_mining_terorganisir['3_items_antecedent']) == 0 && count($hasil_mining_terorganisir['4_items_antecedent']) == 0) active @endif" id="tabLain-tab" data-toggle="tab" data-target="#tabLain" type="button" role="tab" aria-controls="tabLain" aria-selected="{{ (count($hasil_mining_terorganisir['1_item_antecedent']) == 0 && count($hasil_mining_terorganisir['2_items_antecedent']) == 0 && count($hasil_mining_terorganisir['3_items_antecedent']) == 0 && count($hasil_mining_terorganisir['4_items_antecedent']) == 0) ? 'true' : 'false' }}">5+ Item &rarr; 1 Item ({{ count($hasil_mining_terorganisir['lainnya_antecedent']) }})</button>
                                </li>
                                @endif
                            </ul>

                            <div class="tab-content pt-3" id="hasilMiningTabContent">
                                @php $isFirstTab = true; @endphp
                                @foreach(['1_item_antecedent' => 'tab1', '2_items_antecedent' => 'tab2', '3_items_antecedent' => 'tab3', '4_items_antecedent' => 'tab4', 'lainnya_antecedent' => 'tabLain'] as $key => $tabId)
                                    @if(isset($hasil_mining_terorganisir[$key]) && count($hasil_mining_terorganisir[$key]) > 0)
                                    <div class="tab-pane fade @if($isFirstTab) show active @endif" id="{{ $tabId }}" role="tabpanel" aria-labelledby="{{ $tabId }}-tab">
                                        <table class="table table-bordered table-striped hasil-mining-dt">
                                            <thead>
                                                <tr>
                                                    <th>Antecedents</th>
                                                    <th>Consequents</th>
                                                    <th>Support</th>
                                                    <th>Confidence</th>
                                                    <th>Lift</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($hasil_mining_terorganisir[$key] as $rule)
                                                    <tr>
                                                        <td>{{ implode(', ', $rule['antecedents']) }}</td>
                                                        <td>{{ implode(', ', $rule['consequents']) }}</td>
                                                        <td>{{ number_format($rule['support'] * 100, 2) }}%</td>
                                                        <td>{{ number_format($rule['confidence'] * 100, 2) }}%</td>
                                                        <td>{{ number_format($rule['lift'], 4) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @php $isFirstTab = false; @endphp
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                @elseif(is_array($hasil_mining_terorganisir) && !isset($hasil_mining_terorganisir['error']) && $totalAturan == 0)
                    <div class="alert alert-info mt-4">Tidak ada aturan asosiasi yang ditemukan dengan parameter yang diberikan.</div>
                @endif
            @elseif(isset($hasil_mining_terorganisir) && isset($hasil_mining_terorganisir['error']))
                 <div class="alert alert-danger mt-4">
                    <strong>Error Saat Proses Mining:</strong> {{ $hasil_mining_terorganisir['error'] }}
                    @if(isset($hasil_mining_terorganisir['details']) && is_string($hasil_mining_terorganisir['details']))
                        <pre style="white-space: pre-wrap; word-break: break-all; margin-top:10px;">{{ $hasil_mining_terorganisir['details'] }}</pre>
                    @endif
                </div>
            @endif
            {{-- --- AKHIR BAGIAN HASIL MINING DENGAN TABS --- --}}

            {{-- Tabel Data Transaksi (Database) --}}
            <div class="row justify-content-center mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Data Transaksi (Database)</h3>
                        </div>
                        <div class="card-body">
                            <table id="transaksiTableDataMining" class="table table-bordered table-striped" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Order Id</th>
                                        <th>Tanggal</th>
                                        <th>Item</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Diisi oleh DataTables server-side --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    $('#transaksiTableDataMining').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('datamining.getTransaksi') }}",
        columns: [
            { data: 'order_id', name: 'order_id' },
            { data: 'date', name: 'date' },
            { data: 'item', name: 'item' }
        ],
        responsive: true,
        lengthChange: true,
        autoWidth: false,
    });

    // Inisialisasi DataTables untuk semua tabel hasil mining yang memiliki class 'hasil-mining-dt'
    // Ini akan dijalankan jika tabelnya di-render dengan data oleh Blade
    $('.hasil-mining-dt').each(function() {
        if ($(this).find('tbody tr').length > 0 || $(this).find('tbody td').length > 1) { // Cek jika ada baris atau bukan hanya 'tidak ada data'
             $(this).DataTable({
                "responsive": true,
                "lengthChange": true,
                "autoWidth": false,
                "pageLength": 10, // Atur jumlah baris per halaman
                // "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"] // Aktifkan jika perlu
            }); //.buttons().container().appendTo($(this).closest('.card-body').find('.col-md-6:eq(0)')); // Contoh penempatan tombol
        }
    });
     // Mengaktifkan tab pertama secara dinamis jika ada
    $('#hasilMiningTab button.nav-link.active').tab('show');

    // --- AWAL untuk Loading Proses Mining ---
    const prosesMiningForm = $('#prosesMiningForm');
    const prosesMiningButton = $('#prosesMiningButton');
    const miningProgressContainer = $('#miningProgressContainer');

    prosesMiningForm.on('submit', function() {
        // Validasi sederhana di sisi klien untuk memastikan field utama tidak kosong
        let minSupport = $('#min_support').val();
        let minConfidence = $('#min_confidence').val();
        if (!minSupport || !minConfidence) {
            alert('Minimal Support dan Minimal Confidence harus diisi.');
            return false; // Mencegah form submit jika kosong
        }

        // Menonaktifkan tombol dan menampilkan pesan loading
        prosesMiningButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
        miningProgressContainer.show();

        // Form akan melanjutkan proses submit ke server
    });

    // Cek jika halaman dimuat dengan error validasi dari server
    // Jika ya, kembalikan tombol ke keadaan normal.
    @if ($errors->any())
        prosesMiningButton.prop('disabled', false).html('Proses Mining');
        miningProgressContainer.hide();
    @endif

    // Cek jika halaman dimuat dengan hasil (baik data maupun error dari proses Python)
    // Jika ya, kembalikan tombol ke keadaan normal.
    @if(isset($hasil_mining_terorganisir))
        prosesMiningButton.prop('disabled', false).html('Proses Mining');
        miningProgressContainer.hide();
    @endif
});
</script>
@endpush
