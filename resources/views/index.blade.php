@extends('layout.master')
@section('content')
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Input Data Transaksi</h1>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <!-- Small boxes (Stat box) -->
        <div class="row justify-content-center">
            <div class="col-md-10">
                {{-- Session Messages --}}
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
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
                    <!-- /.card-header -->
                    <!-- form start -->
                    <form id="uploadForm" action="{{ route('transaksi.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="card-body">
                            <div class="form-group">
                                <label for="inputExcel">Pilih File Excel</label>
                                <div class="input-group">
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input @error('inputExcel') is-invalid @enderror" id="inputExcel" name="inputExcel" required>
                                        <label class="custom-file-label" for="inputExcel">Pilih file Excel...</label>
                                    </div>
                                </div>
                                @error('inputExcel')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div id="loadingIndicator" style="display: none; margin-top: 15px;">
                                <p id="loadingText" class="text-info"><strong><i class="fas fa-cog fa-spin"></i> Data sedang diproses, mohon tunggu...</strong></p>
                                <div class="progress" style="height: 20px; margin-top: 10px; display: none;">
                                    <div id="importProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
                                        <span id="importProgressText">0%</span>
                                    </div>
                                </div>
                                <p id="loadingSubText" class="text-muted small" style="margin-top: 5px;"></p>
                            </div>
                        </div>
                        <!-- /.card-body -->
                        <div class="card-footer">
                            <button type="submit" id="uploadButton" class="btn btn-primary">Upload</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- /.row -->

        <!-- Placeholder Table for Transaksi Data -->
        <div class="row justify-content-center mt-4">
            <div class="col-md-10"> {{-- Adjust column width as needed --}}
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Data Transaksi (Placeholder)</h3>
                    </div>
                    <!-- /.card-header -->
                    <div class="card-body">
                        <table id="transaksiTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Order Id</th>
                                    <th>Tanggal</th>
                                    <th>Item</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>TRX001</td>
                                    <td>2023-10-26</td>
                                    <td>Item A, Item B</td>
                                </tr>
                                <tr>
                                    <td>TRX002</td>
                                    <td>2023-10-27</td>
                                    <td>Item C</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
        </div>
        <!-- /.row (main row) -->
      </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->
@endsection

@push('scripts')
<script>
  $(function () {
    // Initialize DataTables
    $("#transaksiTable").DataTable({
      "responsive": true,       // Make table responsive
      "lengthChange": true,    // Hide "Show X entries" dropdown
      "autoWidth": false       // Disable auto-width calculation
    });

    // For displaying the filename in Bootstrap custom file input
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    // --- Persistent loading indicator for background queue ---
    var jobPollingInterval = null;
    var isCurrentlyShowingProcessing = false; // Tracks if the UI is *currently* showing the processing state

    function updateUIForProcessing(progress = 0) {
        $('#uploadButton').prop('disabled', true).html('<i class="fas fa-hourglass-half"></i> Sedang Diproses...');
        $('#loadingText').html('<strong><i class="fas fa-cog fa-spin"></i> Data sedang diproses di latar belakang...</strong>');
        $('#loadingSubText').text('Anda dapat meninggalkan halaman ini, proses akan tetap berjalan. Halaman akan kembali normal setelah selesai.');

        $('#importProgressBar').css('width', progress + '%').attr('aria-valuenow', progress).text(progress + '%');
        $('#loadingIndicator .progress').show(); // Show the progress bar
        $('#loadingIndicator').show();
        isCurrentlyShowingProcessing = true;
    }

    function updateUIForIdle(jobJustFinished) {
        $('#uploadButton').prop('disabled', false).html('Upload');
        $('#loadingIndicator').hide();
        $('#loadingIndicator .progress').hide(); // Hide progress bar
        $('#importProgressBar').css('width', '0%').attr('aria-valuenow', 0).text('0%'); // Reset progress bar
        isCurrentlyShowingProcessing = false;
        if (jobJustFinished) {
            // Server-side session flash messages should handle success/error display after reload.
            location.reload();
        }
    }

    function checkBackgroundJobStatus() {
        // Ensure you have a route named 'transaksi.import.status'
        $.ajax({
            url: "{{ route('transaksi.import.status') }}", // Make sure this route is defined
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'processing' || (response.status === 'pending' && response.progress > 0) ) {
                    let progress = response.progress || 0;
                    if (!isCurrentlyShowingProcessing || progress > 0) {
                        updateUIForProcessing(progress);
                    }
                    if (!jobPollingInterval) {
                        jobPollingInterval = setInterval(checkBackgroundJobStatus, 5000); // Poll every 5 seconds
                    }
                } else { // 'idle', 'completed', 'failed', etc.
                    if (jobPollingInterval) {
                        clearInterval(jobPollingInterval);
                        jobPollingInterval = null;
                    }
                    let shouldReload = isCurrentlyShowingProcessing && (response.status === 'completed' || response.status === 'failed' || response.status === 'idle');
                    updateUIForIdle(shouldReload);
                }
            },
            error: function() {
                console.error('Error checking background import status. Polling will stop.');
                if (jobPollingInterval) {
                    clearInterval(jobPollingInterval);
                    jobPollingInterval = null;
                }
                if (isCurrentlyShowingProcessing) {
                    $('#loadingText').html('<strong class="text-warning"><i class="fas fa-exclamation-triangle"></i> Gagal memeriksa status proses.</strong>');
                    $('#loadingSubText').text('Proses mungkin masih berjalan. Segarkan halaman manual nanti.');
                    $('#loadingIndicator .progress').hide(); // Hide progress bar on error
                    $('#uploadButton').prop('disabled', true).html('<i class="fas fa-hourglass-half"></i> Status Tidak Diketahui');
                } else {
                    updateUIForIdle(false); // Reset to idle if not already processing
                }
            }
        });
    }

    var wasJobDispatchedByController = {{ session()->get('import_job_dispatched', false) ? 'true' : 'false' }};
    @if(session()->has('import_job_dispatched'))
        @php session()->forget('import_job_dispatched'); @endphp
    @endif

    if (wasJobDispatchedByController) {
        updateUIForProcessing(0);
        checkBackgroundJobStatus();
    } else {
        checkBackgroundJobStatus(); // Check on any page load if a job might be running
    }

    $('#uploadForm').on('submit', function(event) {
        if ($('#inputExcel')[0].files.length === 0) {
            alert('Silakan pilih file Excel terlebih dahulu.');
            event.preventDefault(); // Prevent form submission
            return;
        }
        $('#uploadButton').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Mengupload...');
        $('#loadingText').html('<strong><i class="fas fa-spinner fa-spin"></i> Sedang mengupload file Anda...</strong>');
        $('#loadingSubText').text('Halaman akan dialihkan setelah file diterima untuk diproses.');
        $('#loadingIndicator .progress').hide(); // Hide progress bar during initial upload
        $('#loadingIndicator').show();
    });
});
</script>
@endpush
