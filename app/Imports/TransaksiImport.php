<?php

namespace App\Imports;

use App\Models\Transaksi; // Pastikan model Transaksi Anda ada di sini
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterChunk;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon; // Import Carbon

class TransaksiImport implements ToModel, WithHeadingRow, WithChunkReading, WithEvents
{
    use RegistersEventListeners;

    private $batchId;
    private $totalRows;
    private static $processedRows = 0;

    public function __construct(string $batchId, int $totalRows)
    {
        $this->batchId = $batchId;
        $this->totalRows = $totalRows;
        self::$processedRows = 0;
    }

    public function model(array $row)
    {
        self::$processedRows++;

        // Sesuaikan nama header ini dengan yang ADA PERSIS di file Excel Anda (case-sensitive jika tidak dinormalisasi otomatis)
        $excelOrderIdHeader = 'order_id'; // Header di Excel untuk Order ID
        $excelDateHeader = 'date';       // Header di Excel untuk Tanggal (format dd-mm-yyyy)
        $excelItemHeader = 'item';       // Header di Excel untuk Item

        $tanggalUntukDatabase = null;
        if (!empty($row[$excelDateHeader])) {
            try {
                // Parsing format 'dd-mm-yyyy' secara eksplisit
                $tanggalUntukDatabase = Carbon::createFromFormat('d/m/Y', trim($row[$excelDateHeader]))->format('Y-m-d');
            } catch (\Exception $e) {
                // Fallback jika format angka Excel atau format lain yang bisa dikenali Carbon
                try {
                    if (is_numeric($row[$excelDateHeader])) {
                        $tanggalUntukDatabase = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[$excelDateHeader])->format('Y-m-d');
                    } else {
                        $tanggalUntukDatabase = Carbon::parse(trim($row[$excelDateHeader]))->format('Y-m-d');
                    }
                } catch (\Exception $ex) {
                    Log::warning("Gagal parsing tanggal '{$row[$excelDateHeader]}' pada baris " . self::$processedRows . " untuk batch {$this->batchId}. Error: " . $ex->getMessage());
                    $tanggalUntukDatabase = null;
                }
            }
        }

        // Pemetaan ke kolom database sesuai migrasi Anda
        return new Transaksi([
            'order_id' => $row[$excelOrderIdHeader] ?? null,
            'date'     => $tanggalUntukDatabase, // Match model's fillable & DB column 'date'
            'item'     => $row[$excelItemHeader] ?? null, // Match model's fillable & DB column 'item'
        ]);
    }

    public function chunkSize(): int
    {
        return 200;
    }

    public static function afterChunk(AfterChunk $event)
    {
        /** @var self $importerInstance */
        $importerInstance = $event->getConcernable();
        $batchId = $importerInstance->batchId;
        $totalRows = $importerInstance->totalRows;
        $cacheKey = 'import_progress_' . $batchId;

        if ($totalRows > 0) {
            $progress = round((self::$processedRows / $totalRows) * 100);
            Cache::put($cacheKey, min($progress, 99), now()->addHours(2));
        } else {
            Cache::put($cacheKey, 50, now()->addHours(2));
        }
    }
}
