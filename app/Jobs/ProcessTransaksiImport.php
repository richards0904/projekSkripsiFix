<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TransaksiImport; // Pastikan namespace ini benar
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessTransaksiImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filePath;
    public $batchId;
    public $totalRows;
    public $userId;

    /**
     * Create a new job instance.
     *
     * @param string $filePath
     * @param string $batchId
     * @param int $totalRows
     * @param int $userId
     */
    public function __construct(string $filePath, string $batchId, int $totalRows, int $userId)
    {
        $this->filePath = $filePath;
        $this->batchId = $batchId;
        $this->totalRows = $totalRows;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $cacheKeyProgress = 'import_progress_' . $this->batchId;
        $cacheKeyActive = 'import_active_' . $this->userId;

        try {
            // Update TransaksiImport untuk menerima batchId dan totalRows
            // agar bisa mengupdate progres dari dalam importer jika menggunakan WithEvents dan AfterChunk
            $import = new TransaksiImport($this->batchId, $this->totalRows);

            Excel::import($import, storage_path('app/' . $this->filePath));

            Cache::put($cacheKeyProgress, 100, now()->addHours(2)); // Selesai 100%
            Log::info("Import batch {$this->batchId} completed for user {$this->userId}.");

        } catch (Throwable $e) {
            Log::error("Import batch {$this->batchId} failed for user {$this->userId}: " . $e->getMessage());
            Cache::put($cacheKeyProgress, -1, now()->addHours(2)); // Indikasi error
            // Lempar kembali error agar job ditandai gagal dan bisa masuk ke failed_jobs
            throw $e;
        } finally {
            Cache::forget($cacheKeyActive); // Hapus tanda aktif
            // Cache::forget('import_batch_id_' . $this->userId); // Bisa juga dihapus di sini atau di controller saat status final
            // Hapus file setelah diproses
            if (Storage::disk('local')->exists($this->filePath)) {
                Storage::disk('local')->delete($this->filePath);
            }
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        $cacheKeyProgress = 'import_progress_' . $this->batchId;
        $cacheKeyActive = 'import_active_' . $this->userId;

        Log::error("JOB FAILED: Import batch {$this->batchId} for user {$this->userId}. Reason: " . $exception->getMessage());
        Cache::put($cacheKeyProgress, -1, now()->addHours(2)); // Tandai error
        Cache::forget($cacheKeyActive); // Hapus tanda aktif
        // Cache::forget('import_batch_id_' . $this->userId); // Opsional
    }
}
