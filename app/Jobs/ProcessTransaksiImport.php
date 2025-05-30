<?php

namespace App\Jobs;

use App\Imports\TransaksiImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage; // To potentially delete the file later
use Illuminate\Support\Facades\Cache;

class ProcessTransaksiImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $filePath;

    protected string $jobId;

    /**
     * Create a new job instance.
     *
     * @param string $filePath The path to the stored Excel file within storage/app.
     * @param string $jobId    A unique identifier for this import job.
     * @return void
     */
    public function __construct(string $filePath, string $jobId)
    {
        $this->filePath = $filePath;
        $this->jobId = $jobId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $statusCacheKey = 'excel_import_status_job_' . $this->jobId;
        $progressCacheKey = 'excel_import_progress_job_' . $this->jobId;
        $ttl = now()->addHours(2); // Time to live for cache entries

        Log::info("Queue Job: Starting import for job {$this->jobId}, file: {$this->filePath}");

        try {
            // Set initial status and progress
            // The controller that dispatches this job might have already set 'pending' or 'processing'.
            // This ensures it's 'processing' when the job actually starts.
            Cache::put($statusCacheKey, 'processing', $ttl);
            Cache::put($progressCacheKey, 0, $ttl); // Start progress at 0%

            // IMPORTANT:
            // Your TransaksiImport class needs to accept the $jobId in its constructor.
            // 2. Periodically update Cache::put($progressCacheKey, $currentPercentage, $ttl)
            //    as it processes rows or chunks.
            // 3. It should also update Cache::put($statusCacheKey, 'processing', $ttl) periodically
            //    to keep the status alive if processing takes a very long time.
            //
            // Example: new TransaksiImport($this->jobId)
            // or new TransaksiImport($progressCacheKey, $statusCacheKey)
            Excel::import(new TransaksiImport($this->jobId), $this->filePath);

            // If import completes without throwing an exception, mark as completed.
            Cache::put($statusCacheKey, 'completed', now()->addMinutes(30)); // Keep for a while
            Cache::put($progressCacheKey, 100, now()->addMinutes(30)); // Ensure 100%

            Log::info("Queue Job: Successfully imported file for job {$this->jobId}: {$this->filePath}");

            // Optional: Delete the temporary file after successful import
            // Make sure the path is relative to the default storage disk (usually 'local' -> storage/app)
            // Storage::disk('local')->delete($this->filePath);

        } catch (\Throwable $e) {
            Log::error("Queue Job: Failed to import file {$this->filePath}. Error: " . $e->getMessage() . " Stack: " . $e->getTraceAsString());

            Cache::put($statusCacheKey, 'failed', $ttl);
            // Optionally, keep the last known progress or reset it
            // Cache::put($progressCacheKey, Cache::get($progressCacheKey, 0), $ttl);

            // The job will automatically be released back onto the queue for retries (configurable)
            // or moved to the failed_jobs table if it exceeds max attempts.
            // You might want to throw the exception again if you want Laravel's default retry/fail logic to fully engage.
            // throw $e; // Uncomment if you want the job to be marked as failed by the queue system immediately.
        }
    }
}
