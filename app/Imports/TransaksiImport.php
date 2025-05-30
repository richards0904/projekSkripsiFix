<?php

namespace App\Imports;

use App\Models\Transaksi;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Events\AfterImport; // Optional, for cleanup or final checks

class TransaksiImport implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading, WithEvents
{
    private string $jobId;
    private string $statusCacheKey;
    private string $progressCacheKey;
    private \Carbon\Carbon $ttl;

    private int $totalRows = 0;
    private int $processedRows = 0;
    private int $updateThreshold; // How often to update cache

    public function __construct(string $jobId)
    {
        $this->jobId = $jobId;
        $this->statusCacheKey = 'excel_import_status_job_' . $this->jobId;
        $this->progressCacheKey = 'excel_import_progress_job_' . $this->jobId;
        $this->ttl = now()->addHours(2);
        $this->updateThreshold = 50; // Update cache every 50 rows, adjust as needed
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        $this->processedRows++;
        // Assumes your Excel columns are named 'order_id', 'date', 'item'
        // if WithHeadingRow is used.

        $parsedDate = null;
        if (isset($row['date'])) {
            if (is_numeric($row['date'])) {
                // Handle Excel numeric date format (common when Excel stores dates as numbers)
                $parsedDate = Carbon::instance(ExcelDate::excelToDateTimeObject($row['date']))->format('Y-m-d');
            } else {
                // Handle string date format dd/mm/yyyy
                try {
                    $parsedDate = Carbon::createFromFormat('d/m/Y', $row['date'])->format('Y-m-d');
                } catch (\Exception $e) {
                    // Invalid date format, validation rule below should catch this.
                    $parsedDate = null;
                }
            }
        }

        // Update progress periodically
        if ($this->processedRows % $this->updateThreshold === 0 || $this->processedRows === $this->totalRows) {
            if ($this->totalRows > 0) {
                $progress = (int)(($this->processedRows / $this->totalRows) * 100);
                Cache::put($this->progressCacheKey, $progress, $this->ttl);
                Cache::put($this->statusCacheKey, 'processing', $this->ttl); // Keep status alive
            }
        }

        return new Transaksi([
            'order_id' => isset($row['order_id']) ? (string)$row['order_id'] : null,
            'date'     => $parsedDate, // Stored as YYYY-MM-DD in DB
            'item'     => $row['item'] ?? null,
        ]);
    }
    /**
     * Prepare the data for validation.
     *
     * @param  array  $data
     * @param  int  $index
     * @return array
     */

    public function prepareForValidation($data, $index)
    {
        $data['order_id'] = isset($data['order_id']) ? (string) $data['order_id'] : null;
        return $data;
    }
    /**
     * @return array
     */

    public function rules(): array
    {
        return [
            '*.order_id' => 'required|string|max:255',
            '*.date'     => 'required',
            '*.item'     => 'required|string|max:255',
        ];
    }

    public function customValidationMessages()
    {
        return [
            '*.order_id.required' => 'Order ID pada baris :attribute wajib diisi.',
            '*.date.required'     => 'Tanggal pada baris :attribute wajib diisi. Pastikan formatnya dd/mm/yyyy atau format tanggal Excel.',
            '*.item.required'     => 'Item pada baris :attribute wajib diisi.',
        ];
    }

    /**
     * Defines how many models should be inserted into the database at once.
     *
     * @return int
     */
    public function batchSize(): int
    {
        return 500; // Adjust this number based on your server resources and testing
    }

    /**
     * Defines how many rows should be read from the spreadsheet at a time.
     *
     * @return int
     */
    public function chunkSize(): int
    {
        return 500; // Adjust this number based on your server resources and testing
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function(BeforeSheet $event) {
                // Get total rows. Subtract 1 if WithHeadingRow is used.
                $this->totalRows = $event->getSheet()->getDelegate()->getHighestRow() - ($this instanceof WithHeadingRow ? 1 : 0);

                // Initialize progress if there are rows to process
                if ($this->totalRows > 0) {
                    Cache::put($this->progressCacheKey, 0, $this->ttl);
                    Cache::put($this->statusCacheKey, 'processing', $this->ttl); // Ensure status is processing
                } else {
                    // No rows to process, consider it 100% done or a specific status
                    Cache::put($this->progressCacheKey, 100, $this->ttl);
                }
            },
            // You can also listen to AfterImport if needed for any final actions within this class
            // AfterImport::class => function(AfterImport $event) { ... }
        ];
    }
}
