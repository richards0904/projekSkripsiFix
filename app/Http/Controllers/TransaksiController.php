<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ProcessTransaksiImport;
use Illuminate\Support\Facades\Cache;
use App\Models\Transaksi; // Import the Transaksi model
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory; // Untuk membaca file Excel

class TransaksiController extends Controller
{
    // Asumsikan method index sudah ada untuk menampilkan halaman index.blade.php
    public function index()
    {
        // Cek apakah ada proses impor yang sedang berjalan untuk user ini saat halaman dimuat
        $userId = auth()->id();
        $activeBatchId = Cache::get('import_batch_id_' . $userId);
        $isActive = $activeBatchId ? Cache::get('import_active_' . $userId, false) : false;
        $progress = $activeBatchId ? Cache::get('import_progress_' . $activeBatchId, 0) : 0;


        $viewData = [
        ];

        if ($isActive && $progress < 100 && $progress !== -1) {
            // Jika ada proses aktif, kirim batch_id ke view
            $viewData['active_batch_id'] = $activeBatchId;
        }
        return view('index', $viewData); // Pass all data to the view
    }


    public function importExcel(Request $request)
    {
        $request->validate([
            'file_excel' => 'required|mimes:xls,xlsx|max:204800', // Max 200MB, sesuaikan
        ]);

        $userId = auth()->id();
        $cacheKeyActive = 'import_active_' . $userId;

        if (Cache::get($cacheKeyActive)) {
            return redirect()->back()->with('error', 'Proses impor sebelumnya masih berjalan. Harap tunggu hingga selesai.');
        }

        $file = $request->file('file_excel');
        $namaFile = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('excel-imports', $namaFile, 'local'); // Simpan di storage/app/excel-imports

        $totalRows = 0;
        try {
            $spreadsheet = IOFactory::load(storage_path('app/' . $filePath));
            $worksheet = $spreadsheet->getActiveSheet();
            $totalRows = $worksheet->getHighestRow() - 1; // Kurangi 1 untuk baris heading
            if ($totalRows < 0) $totalRows = 0;
        } catch (\Exception $e) {
            Storage::disk('local')->delete($filePath); // Hapus file jika gagal baca
            Log::error('Gagal membaca file Excel untuk menghitung baris: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal memproses file Excel. Pastikan formatnya benar.');
        }

        if ($totalRows === 0) {
             Storage::disk('local')->delete($filePath);
            return redirect()->back()->with('error', 'File Excel kosong atau tidak ada data untuk diimpor.');
        }

        $batchId = 'import_' . uniqid() . '_' . $userId;

        Cache::put($cacheKeyActive, true, now()->addHours(3)); // Tandai proses aktif, timeout 3 jam
        Cache::put('import_batch_id_' . $userId, $batchId, now()->addHours(3)); // Simpan batch_id user
        Cache::put('import_progress_' . $batchId, 0, now()->addHours(3)); // Inisialisasi progres

        ProcessTransaksiImport::dispatch($filePath, $batchId, $totalRows, $userId);

        return redirect()->route('transaksi.index') // Ganti dengan nama route yang benar ke halaman index
                         ->with('success', 'File telah diterima dan sedang diproses di latar belakang.')
                         ->with('batch_id_after_redirect', $batchId);
    }

    public function importStatus(Request $request)
    {
        $userId = auth()->id();
        $batchId = $request->input('batch_id', Cache::get('import_batch_id_' . $userId));

        if (!$batchId) {
            // Jika tidak ada batch_id spesifik, cek apakah ada proses aktif secara umum untuk user ini
            if (Cache::get('import_active_' . $userId)) {
                 return response()->json([
                    'status' => 'processing',
                    'progress' => 0, // Atau progres terakhir yang diketahui jika bisa diambil
                    'message' => 'Ada proses impor yang sedang berjalan, mengambil status...',
                    'batch_id' => null, // Tidak tahu batch_id spesifiknya
                    'is_active' => true,
                ]);
            }
            return response()->json(['status' => 'idle', 'progress' => 0, 'message' => 'Tidak ada proses impor.']);
        }

        $progress = Cache::get('import_progress_' . $batchId, 0);
        $isActive = Cache::get('import_active_' . $userId, false); // Cek status aktif user

        $status = 'idle';
        $message = 'Menunggu...';

        if ($progress === -1) {
            $status = 'failed';
            $message = 'Impor gagal. Silakan coba lagi atau hubungi administrator.';
            Cache::forget('import_batch_id_' . $userId); // Hapus batch ID jika gagal
            Cache::forget('import_active_' . $userId); // Hapus status aktif
        } elseif ($progress == 100) {
            $status = 'completed';
            $message = 'Impor selesai!';
            Cache::forget('import_batch_id_' . $userId); // Hapus batch ID jika selesai
            Cache::forget('import_active_' . $userId); // Hapus status aktif
        } elseif ($isActive) { // Jika masih aktif dan progres belum 100 atau -1
            $status = 'processing';
            $message = "Sedang memproses... ({$progress}%)";
        } else { // Jika tidak aktif, dan progres bukan 100 atau -1, anggap idle/selesai tapi belum di-clear
            if ($progress > 0 && $progress < 100) { // Kemungkinan job selesai tapi cache belum bersih total
                $status = 'unknown_completed'; // Status ini menandakan mungkin selesai tapi cache aktif sudah hilang
                $message = "Proses impor sebelumnya mungkin telah selesai ({$progress}%).";
            } else {
                $status = 'idle';
                $message = 'Tidak ada proses impor aktif.';
            }
            Cache::forget('import_batch_id_' . $userId);
        }


        return response()->json([
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
            'batch_id' => $batchId,
            'is_active' => ($status === 'processing' || $status === 'unknown_completed'), // Anggap aktif jika sedang proses atau selesai tapi belum di-refresh
        ]);
    }
}
