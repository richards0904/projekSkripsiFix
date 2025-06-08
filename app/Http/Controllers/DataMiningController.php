<?php

namespace App\Http\Controllers;

use App\Models\Transaksi; // Pastikan Transaksi model di-import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class DataMiningController extends Controller
{
    public function index()
    {
        // Membersihkan session hasil apriori sebelumnya saat halaman data mining di-load ulang
        // agar tidak menampilkan hasil lama jika pengguna kembali tanpa memproses ulang.
        // Atau, Anda bisa biarkan jika ingin hasil terakhir tetap ada sampai diproses ulang.
        // session()->forget('hasil_apriori_untuk_kesimpulan');

        return view('datamining', [
            'hasil_mining_terorganisir' => session('hasil_apriori_untuk_tab', null), // Ambil dari session jika ada
            'input_sebelumnya' => old() ?: session('input_sebelumnya_dm', []) // Ambil input lama
        ]);
    }

    public function getTransaksiData(Request $request)
    {
        if ($request->ajax()) {
            $query = Transaksi::query();
            return DataTables::of($query)
                ->editColumn('date', function ($transaksi) {
                    return $transaksi->date ? \Carbon\Carbon::parse($transaksi->date)->format('d-m-Y') : '-';
                })
                ->rawColumns(['date'])
                ->make(true);
        }
        abort(403, 'Unauthorized action.');
    }

    public function processMining(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'min_support' => 'required|numeric|min:3|max:100',
            'min_confidence' => 'required|numeric|min:1|max:100',
            'tanggal_awal' => 'nullable|date_format:Y-m-d',
            'tanggal_akhir' => 'nullable|date_format:Y-m-d|after_or_equal:tanggal_awal',
            'min_item_occurrence' => 'nullable|integer|min:1',
        ], [
            'tanggal_akhir.after_or_equal' => 'Tanggal akhir harus setelah atau sama dengan tanggal awal.',
            'min_support.min' => 'Minimal support harus setidaknya 3%.',
            'min_confidence.min' => 'Minimal confidence harus setidaknya 1%.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $tanggalAwal = $request->input('tanggal_awal');
            $tanggalAkhir = $request->input('tanggal_akhir');
            if (($tanggalAwal && !$tanggalAkhir) || (!$tanggalAwal && $tanggalAkhir)) {
                $validator->errors()->add('tanggal_custom', 'Jika salah satu tanggal diisi (awal atau akhir), maka kedua tanggal harus diisi.');
            }
        });

        if ($validator->fails()) {
            return redirect()->route('transaksi.dataMining')
                        ->withErrors($validator)
                        ->withInput();
        }

        $validated = $validator->validated();

        $minSupportDecimal = $validated['min_support'] / 100;
        $minConfidenceDecimal = $validated['min_confidence'] / 100;
        $minItemOccurrenceDefault = 5; // Nilai default tetap di sini

        $pythonPath = 'C:\\Users\\User\\AppData\\Local\\Programs\\Python\\Python39\\python.exe';
        $scriptPath = base_path('apriori.py');
        $command = [
            $pythonPath, $scriptPath,
            '--min_support', $minSupportDecimal, // Kirim nilai desimal
            '--min_confidence', $minConfidenceDecimal, // Kirim nilai desimal
            '--min_item_occurrence', $minItemOccurrenceDefault
        ];
        if (!empty($validated['tanggal_awal']) && !empty($validated['tanggal_akhir'])) {
            $command[] = '--tanggal_awal'; $command[] = $validated['tanggal_awal'];
            $command[] = '--tanggal_akhir'; $command[] = $validated['tanggal_akhir'];
        }
        if (!empty($validated['min_item_occurrence'])) {
            $command[] = '--min_item_occurrence'; $command[] = $validated['min_item_occurrence'];
        }

        Log::info('Running Python command: ' . implode(' ', $command));
        $process = new Process($command);
        $process->setTimeout(3600);

        $hasilUntukTab = null;
        $errorOccurred = false;

        try {
            $process->mustRun();
            $rawOutputFromPython = $process->getOutput();
            Log::info('Python script Raw Output: ' . $rawOutputFromPython);
            $decodedJson = json_decode($rawOutputFromPython, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                if (isset($decodedJson['error'])) { // Error yang didefinisikan dalam JSON Python
                    Log::error('Error from Python script logic: ' . $decodedJson['error'] . (isset($decodedJson['details']) ? ' - Details: '.$decodedJson['details'] : ''));
                    $hasilUntukTab = ['error' => $decodedJson['error'], 'details' => $decodedJson['details'] ?? $rawOutputFromPython];
                    session()->forget('hasil_apriori_untuk_kesimpulan');
                    $errorOccurred = true;
                } elseif (is_array($decodedJson)) {
                    // SIMPAN HASIL MENTAH (tapi sudah disaring Python) KE SESSION untuk halaman Kesimpulan
                    session(['hasil_apriori_untuk_kesimpulan' => $decodedJson]);

                    // Organisir untuk tampilan tab di halaman data mining
                    $tabCategories = [
                        '1_item_antecedent' => [], '2_items_antecedent' => [],
                        '3_items_antecedent' => [], '4_items_antecedent' => [],
                        'lainnya_antecedent' => []
                    ];
                    foreach ($decodedJson as $rule) {
                        if (!isset($rule['antecedents']) || !is_array($rule['antecedents'])) continue;
                        $antecedentLen = count($rule['antecedents']);
                        $key = match (true) {
                            $antecedentLen == 1 => '1_item_antecedent',
                            $antecedentLen == 2 => '2_items_antecedent',
                            $antecedentLen == 3 => '3_items_antecedent',
                            $antecedentLen == 4 => '4_items_antecedent',
                            default => ($antecedentLen > 4 ? 'lainnya_antecedent' : null),
                        };
                        if ($key) {
                            $tabCategories[$key][] = $rule;
                        }
                    }
                    $hasilUntukTab = $tabCategories;
                } else { // Bukan array dan bukan error terstruktur dari Python
                     Log::error('Output Python bukan array atau JSON error terstruktur: ' . $rawOutputFromPython);
                    $hasilUntukTab = ['error' => 'Format output tidak dikenal.', 'details' => $rawOutputFromPython];
                    session()->forget('hasil_apriori_untuk_kesimpulan');
                    $errorOccurred = true;
                }
            } else { // Gagal parse JSON
                Log::error('Gagal memparse output JSON. Error: ' . json_last_error_msg() . ' | Output: ' . $rawOutputFromPython);
                $errorOutputFromPython = $process->getErrorOutput();
                Log::error('Python Error Stream (if any): ' . $errorOutputFromPython);
                $hasilUntukTab = ['error' => 'Format output tidak valid.', 'details' => $rawOutputFromPython . ($errorOutputFromPython ? "\nError Stream: " . $errorOutputFromPython : "")];
                session()->forget('hasil_apriori_untuk_kesimpulan');
                $errorOccurred = true;
            }
        } catch (ProcessFailedException $exception) {
            $errorOutputFromPython = $exception->getProcess()->getErrorOutput();
            Log::error('Proses Python gagal: ' . $exception->getMessage() . ' | Error Output: ' . $errorOutputFromPython);
            $hasilUntukTab = ['error' => 'Eksekusi skrip gagal.', 'details' => $errorOutputFromPython ?: $exception->getMessage()];
            session()->forget('hasil_apriori_untuk_kesimpulan');
            $errorOccurred = true;
        }

        // Simpan input ke session agar bisa di-repopulate jika ada validasi error di halaman lain, atau untuk referensi
        session(['input_sebelumnya_dm' => $request->all()]);

        // Simpan hasil untuk tab ke session juga, agar saat redirect kembali, tab bisa ditampilkan
        session(['hasil_apriori_untuk_tab' => $hasilUntukTab]);

        // Redirect kembali ke halaman data-mining untuk menampilkan hasil di tab dan link ke kesimpulan
        // Atau jika Anda ingin langsung ke kesimpulan jika tidak ada error:
        // if (!$errorOccurred && !isset($hasilUntukTab['error'])) {
        //     return redirect()->route('kesimpulan.index');
        // }

        return redirect()->route('transaksi.dataMining')->withInput(); // withInput() akan mengambil dari session('input_sebelumnya_dm') jika ada
    }
}
