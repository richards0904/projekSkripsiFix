@extends('layout.master')
@section('content')
  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Input Data Transaksi</h1>
          </div></div></div></div>
    <section class="content">
      <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-10">
                {{-- Session Messages --}}
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        @if (session('batch_id_after_redirect')) {{-- Display Batch ID if redirected with it --}}
                            <small class="d-block">ID Proses Impor: {{ session('batch_id_after_redirect') }}</small>
                        @endif
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif
                @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @endif
                @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> Periksa kesalahan berikut:
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @endif

                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Upload File Excel Transaksi</h3>
                    </div>
                    {{-- Pastikan route name ini sesuai dengan yang ada di web.php untuk method importExcel di TransaksiController --}}
                    <form id="uploadForm" action="{{ route('transaksi.importExcel') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="card-body">
                            <div class="form-group">
                                <label for="inputExcel">Pilih File Excel</label>
                                <div class="input-group">
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input @error('file_excel') is-invalid @enderror" id="inputExcel" name="file_excel" required> {{-- Nama input disesuaikan dengan controller: file_excel --}}
                                        <label class="custom-file-label" for="inputExcel">Pilih file Excel...</label>
                                    </div>
                                </div>
                                @error('file_excel') {{-- Disesuaikan dengan nama input --}}
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div id="progressContainer" style="display: none; margin-top: 15px;">
                                <h5 id="progressTitle">Proses Impor: <span id="batchIdDisplay"></span></h5>
                                <div class="progress" style="height: 25px;">
                                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                                </div>
                                <p id="progressMessage" class="mt-2 text-info">Memulai proses...</p>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" id="uploadButton" class="btn btn-primary">Upload</button>
                        </div>
                    </form>
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
    // For displaying the filename in Bootstrap custom file input
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    const uploadForm = $('#uploadForm');
    const uploadButton = $('#uploadButton');
    const fileExcelInput = $('#inputExcel');
    const progressContainer = $('#progressContainer');
    const progressBar = $('#progressBar');
    const progressMessage = $('#progressMessage');
    const progressTitle = $('#progressTitle'); // Untuk menampilkan Batch ID jika perlu
    const batchIdDisplay = $('#batchIdDisplay');


    let currentBatchId = @json(session('batch_id_after_redirect') ?? ($active_batch_id ?? null));
    @if(session()->has('batch_id_after_redirect'))
        @php session()->forget('batch_id_after_redirect'); @endphp
    @endif

    let pollInterval;

    function updateProgressUI(progress, message, status, batchIdText = '') {
        progressBar.css('width', progress + '%').attr('aria-valuenow', progress).text(progress + '%');
        progressMessage.text(message);
        if(batchIdText) {
            batchIdDisplay.text(`(ID: ${batchIdText})`);
        } else {
            batchIdDisplay.text('');
        }


        progressBar.removeClass('bg-success bg-danger bg-warning progress-bar-animated');
        progressMessage.removeClass('text-success text-danger text-warning text-info');

        if (status === 'completed') {
            progressBar.addClass('bg-success');
            progressMessage.addClass('text-success');
        } else if (status === 'failed') {
            progressBar.addClass('bg-danger').css('width', '100%').text('Gagal');
            progressMessage.addClass('text-danger');
        } else if (status === 'processing') {
            progressBar.addClass('progress-bar-animated');
            progressMessage.addClass('text-info');
        } else if (status === 'pending_server_processing') {
            progressBar.addClass('progress-bar-animated').css('width', '5%'); // Small initial progress
            progressMessage.addClass('text-info');
        }
         else {
            progressMessage.addClass('text-muted');
        }
    }

    function disableForm(isProcessing = false) {
        uploadButton.prop('disabled', true);
        fileExcelInput.prop('disabled', true);
        if (isProcessing) {
            uploadButton.html('<i class="fas fa-hourglass-half"></i> Sedang Diproses...');
        } else {
            uploadButton.html('<i class="fas fa-spinner fa-spin"></i> Mengupload...');
        }
        progressContainer.show();
    }

    function enableForm() {
        uploadButton.prop('disabled', false).html('Upload');
        fileExcelInput.prop('disabled', false);
        // Jangan sembunyikan progressContainer jika statusnya completed atau failed
        // progressContainer.hide(); // Bisa dipertimbangkan jika ingin disembunyikan total saat idle
    }

    function stopPolling() {
        clearInterval(pollInterval);
        pollInterval = null;
    }

    function checkImportStatus(batchIdToCheck) {
        let url = "{{ route('transaksi.importStatus') }}"; // Pastikan route ini ada
        if (batchIdToCheck) {
            url += "?batch_id=" + batchIdToCheck;
        }

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.batch_id && !currentBatchId) { // Jika ada batch_id dari server (misal user refresh)
                    currentBatchId = data.batch_id;
                }

                updateProgressUI(data.progress < 0 ? 0 : data.progress, data.message, data.status, currentBatchId);

                if (data.status === 'processing' || (data.is_active && data.progress < 100 && data.progress >= 0) ) {
                    disableForm(true); // true menandakan sedang diproses server, bukan hanya upload
                    if (!pollInterval) {
                         pollInterval = setInterval(() => checkImportStatus(currentBatchId), 3000); // Poll every 3 seconds
                    }
                } else if (data.status === 'completed' || data.status === 'failed') {
                    enableForm();
                    stopPolling();
                    if (data.status === 'completed') {
                        // Me-refresh halaman setelah 2 detik untuk memberi waktu user membaca pesan sukses
                        setTimeout(() => location.reload(), 2000);
                         progressMessage.text(data.message + " Silakan refresh halaman jika data belum update.");
                    }
                    currentBatchId = null; // Reset batch ID
                } else { // idle, or unknown_completed, or status not active
                    enableForm();
                    stopPolling();
                    // Sembunyikan progress jika benar-benar idle dan tidak ada batch_id
                    if (!currentBatchId && data.status === 'idle') {
                        progressContainer.hide();
                    } else if (currentBatchId && (data.status === 'idle' || data.status === 'unknown_completed')) {
                        // Jika ada currentBatchId tapi status idle, mungkin job selesai tapi cache belum clear
                        // Anggap selesai dan reset
                         updateProgressUI(100, "Proses sebelumnya telah selesai atau tidak ditemukan.", 'completed', currentBatchId);
                         enableForm();
                         currentBatchId = null;
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Error checking import status:', error);
                updateProgressUI(0, 'Gagal memeriksa status. Coba refresh halaman.', 'failed', currentBatchId);
                // Pertimbangkan untuk tidak enable form sepenuhnya jika status error, atau beri pesan khusus
                enableForm(); // Atau uploadButton.html('Coba Lagi?');
                stopPolling();
            }
        });
    }

    uploadForm.on('submit', function(event) {
        if (fileExcelInput[0].files.length === 0) {
            alert('Silakan pilih file Excel terlebih dahulu.');
            event.preventDefault();
            return;
        }
        // Manually disable button and update its text for the initial upload phase.
        // Do NOT disable fileExcelInput here, as its value is needed for submission.
        // Calling disableForm(false) here would disable the file input.
        uploadButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Mengupload...');

        progressContainer.show();
        updateProgressUI(0, 'Mengunggah file dan memulai antrean...', 'pending_server_processing');
        // Form akan disubmit secara normal, dan controller akan redirect dengan session batch_id
    });

    // Initial check when page loads
    if (currentBatchId) {
        disableForm(true); // Langsung disable dan tampilkan progress jika ada batch_id aktif
        checkImportStatus(currentBatchId);
    } else {
        // Jika tidak ada batch_id dari session, cek status umum sekali
        // Ini untuk menangani kasus user refresh halaman saat job masih berjalan
        // atau jika tab ditutup lalu dibuka lagi
        checkImportStatus(null);
    }
  });
</script>
@endpush
