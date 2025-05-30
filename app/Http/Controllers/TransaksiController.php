<?php

namespace App\Http\Controllers;

use App\Imports\TransaksiImport;
use App\Jobs\ProcessTransaksiImport;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class TransaksiController extends Controller
{
    public function index()
    {
        // You might fetch existing transactions here if you display them on the page
        // $transaksis = Transaksi::orderBy('date', 'desc')->paginate(10);
        // return view('index', compact('transaksis'));
        return view('index');    }

    public function uploadExcel(Request $request)
    {
        $request->validate([
            'inputExcel' => 'required|file|mimes:xls,xlsx|max:2048', // Max 2MB, only .xls or .xlsx
        ],[
            'inputExcel.required' => 'File Excel wajib diisi.',
            'inputExcel.file' => 'Yang diupload harus berupa file.',
            'inputExcel.mimes' => 'File harus berformat .xls atau .xlsx.',
            'inputExcel.max' => 'Ukuran file maksimal adalah 2MB.',
        ]);
        $file = $request->file('inputExcel');

        try{
            // Store the file in 'storage/app/imports' directory.
            // The store method returns the path relative to the disk's root.
            // For the 'local' disk, this is 'imports/filename.xlsx'.
            $path = $file->store('imports', 'local');

            // Dispatch the job to the queue
            ProcessTransaksiImport::dispatch($path);

            // Flash a session variable to indicate background processing has started.
            // This will only be available for the *next* request.
            session()->flash('import_job_dispatched', true);



            return redirect()->route('transaksi.index')->with('success', 'File Anda telah diterima dan akan segera diproses di latar belakang. Ini mungkin memerlukan beberapa waktu.');
        }catch (\Exception $e) {
            Log::error('Error dispatching Excel import job: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return redirect()->route('transaksi.index')->with('error', 'Terjadi kesalahan saat memproses file Excel: ' . $e->getMessage())->withInput();
        }
    }
    public function getImportStatus()
    {
        // Check if there are any pending jobs of our specific type.
        // The 'payload' column in the 'jobs' table contains JSON,
        // and 'displayName' usually holds the job class name.
        // Note: Using LIKE for JSON is not the most performant but simple for this case.
        $pendingJobs = DB::table('jobs')
                        ->where('queue', config('queue.default')) // Check the default queue
                        ->whereNull('reserved_at') // Not currently being processed by a worker
                        ->where('payload', 'like', '%App\\\\Jobs\\\\ProcessTransaksiImport%')
                        ->count();

        $processingJobs = DB::table('jobs')
                        ->where('queue', config('queue.default'))
                        ->whereNotNull('reserved_at') // Currently being processed
                        ->where('payload', 'like', '%App\\\\Jobs\\\\ProcessTransaksiImport%')
                        ->count();

        if ($pendingJobs > 0 || $processingJobs > 0) {
            return response()->json(['status' => 'processing']);
        } else {
            // If no jobs are processing, clear the session flag that might have initiated polling
            session()->forget('import_job_dispatched');
            return response()->json(['status' => 'idle']);
        }
    }
}
