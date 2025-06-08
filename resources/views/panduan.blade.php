@extends('layout.master')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Panduan Penggunaan Sistem</h1>
                </div>
            </div>
        </div>
    </div>
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12" id="accordion">
                    {{-- KARTU 1: IMPOR DATA TRANSAKSI --}}
                    <div class="card card-primary card-outline">
                        <a class="d-block w-100" data-toggle="collapse" href="#collapseOne">
                            <div class="card-header">
                                <h4 class="card-title w-100">
                                    1. Impor Data Transaksi
                                </h4>
                            </div>
                        </a>
                        <div id="collapseOne" class="collapse show" data-parent="#accordion">
                            <div class="card-body">
                                <p>Fungsi ini digunakan untuk memasukkan data transaksi dari file Excel ke dalam sistem. Pastikan file Excel Anda memiliki format yang benar sebelum diunggah.</p>
                                <h5>Langkah-langkah:</h5>
                                <ol>
                                    <li>Buka halaman <strong>"Input Data Transaksi"</strong> dari menu sidebar.</li>
                                    <li>Siapkan file Excel Anda dengan format kolom sebagai berikut:
                                        <table class="table table-sm table-bordered mt-2" style="width: auto;">
                                            <thead>
                                                <tr style="background-color: #e9ecef;">
                                                    <th>order_id</th>
                                                    <th>date</th>
                                                    <th>item</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>16602</td>
                                                    <td>25/05/2025</td>
                                                    <td>Kerapu Saos Padang</td>
                                                </tr>
                                                <tr>
                                                    <td>16602</td>
                                                    <td>25/05/2025</td>
                                                    <td>Nasi Putih</td>
                                                </tr>
                                                 <tr>
                                                    <td>16602</td>
                                                    <td>25/05/2025</td>
                                                    <td>Es Teh Manis</td>
                                                </tr>
                                                <tr>
                                                    <td>16603</td>
                                                    <td>26/05/2025</td>
                                                    <td>Ayam Bakar</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </li>
                                    <li>Klik tombol "Pilih file Excel...", pilih file Anda, lalu klik tombol <strong>"Upload"</strong>.</li>
                                    <li>Proses impor akan berjalan di latar belakang. Anda bisa memantau progresnya melalui progress bar yang muncul. Proses ini bisa memakan waktu beberapa menit jika datanya sangat banyak.</li>
                                    <li>Setelah selesai, data transaksi akan masuk ke dalam database sistem.</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    {{-- KARTU 2: PROSES DATA MINING --}}
                    <div class="card card-primary card-outline">
                        <a class="d-block w-100" data-toggle="collapse" href="#collapseTwo">
                            <div class="card-header">
                                <h4 class="card-title w-100">
                                    2. Proses Data Mining
                                </h4>
                            </div>
                        </a>
                        <div id="collapseTwo" class="collapse" data-parent="#accordion">
                            <div class="card-body">
                                <p>Halaman ini adalah inti dari sistem, tempat Anda menjalankan analisis Apriori untuk menemukan pola pembelian dan aturan asosiasi.</p>
                                <h5>Langkah-langkah:</h5>
                                <ol>
                                    <li>Buka halaman <strong>"Data Mining"</strong> dari menu sidebar.</li>
                                    <li>Anda akan melihat form <strong>Parameter Data Mining</strong>. Isi parameter berikut:
                                        <ul>
                                            <li><strong>Minimal Support (%)</strong>: Menentukan seberapa sering sebuah kombinasi produk harus muncul agar dianggap penting. Nilai yang lebih rendah akan menemukan lebih banyak pola. Disarankan memulai dengan nilai antara <strong>4% hingga 6%</strong>.</li>
                                            <li><strong>Minimal Confidence (%)</strong>: Menentukan seberapa kuat hubungan antar produk. Nilai ini menunjukkan seberapa yakin kita bahwa jika item A dibeli, item B juga akan dibeli. Disarankan memulai dengan nilai antara <strong>50% hingga 70%</strong>.</li>
                                            <li><strong>Tanggal Awal & Tanggal Akhir</strong>: Pilih rentang tanggal data transaksi yang ingin Anda analisis. Kosongkan jika ingin menganalisis semua data.</li>
                                        </ul>
                                    </li>
                                    <li>Klik tombol <strong>"Proses Mining"</strong>. Sistem akan menampilkan indikator loading karena proses ini bisa memakan waktu.</li>
                                    <li>Setelah selesai, hasil analisis (aturan asosiasi) akan ditampilkan dalam bentuk tabel yang terorganisir dalam beberapa tab berdasarkan jumlah item (misalnya, 1 ke 1 Item, 2 ke 1 Item, dst.).</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    {{-- KARTU 3: KESIMPULAN & REKOMENDASI --}}
                    <div class="card card-primary card-outline">
                        <a class="d-block w-100" data-toggle="collapse" href="#collapseThree">
                            <div class="card-header">
                                <h4 class="card-title w-100">
                                    3. Kesimpulan & Rekomendasi Paket
                                </h4>
                            </div>
                        </a>
                        <div id="collapseThree" class="collapse" data-parent="#accordion">
                            <div class="card-body">
                                <p>Halaman ini menerjemahkan hasil teknis dari data mining menjadi rekomendasi paket bundling yang lebih mudah dipahami dan dapat ditindaklanjuti.</p>
                                <h5>Cara Menggunakan:</h5>
                                <ol>
                                    <li>Pastikan Anda sudah menjalankan "Proses Mining" setidaknya satu kali. Halaman ini menggunakan hasil dari proses mining terakhir yang Anda lakukan.</li>
                                    <li>Buka halaman <strong>"Kesimpulan Rekomendasi"</strong> dari menu sidebar.</li>
                                    <li>Halaman ini akan menampilkan Top 5-10 rekomendasi paket bundling terbaik berdasarkan hasil data mining.</li>
                                    <li>Setiap rekomendasi paket disertai dengan nama paket yang disarankan, serta metrik pendukungnya (Support, Confidence, dan Lift) agar Anda bisa menilai kekuatan rekomendasinya.</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

