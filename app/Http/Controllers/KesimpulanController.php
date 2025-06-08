<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// Anda tidak perlu model AturanAsosiasiResult lagi jika tidak simpan ke DB

class KesimpulanController extends Controller
{
    // Kategori menu di-hardcode di sini
    private $menuCategories = [
        'Menu Utama' => [
            'Seafood Tumpah', 'Fuyunghai', 'Sate Maranggi',
            'Mie Goreng Ultah', 'Nasi Goreng Telor', 'Nasi Goreng Seafood',
            'Capcay Ayam', 'Capcay Seafood', // Dipindahkan
            'Cumi Goreng Tepung', 'Udang Goreng Tepung', 'Cumi Saos Asam Manis',
            'Udang Saos Asam Manis', 'Cumi Saos Mala', 'Udang Saos Mala',
            'Cumi Saos Mentega', 'Udang Saos Mentega', 'Cumi Saos Lada Hitam',
            'Udang Saos Lada Hitam', 'Cumi Saos Padang', 'Udang Saos Padang',
            'Cumi Bakar', 'Udang Bakar',
            'Kerang Ijo Saos Padang', 'Kerang Ijo Saos Tiram', 'Kerang Ijo Rebus',
            'Kerang Dara Saos Padang', 'Kerang Dara Saos Tiram', 'Kerang Dara Rebus',
            'Kepiting Saos Asam Manis', 'Kepiting Saos Tiram', 'Kepiting Saos Lada Hitam',
            'Kepiting Saos Padang', 'Kepiting Asap',
            'Ayam Goreng', 'Ayam Panggang', 'Ayam Lada Hitam', 'Ayam Saos Padang',
            'Ayam Saos Mentega', 'Bistik Ayam',
            'Gurame Tim Biasa', 'Gurame Panggang', 'Gurame Tim Nonya',
            'Gurame Saos Asam Manis', 'Gurame Kuah Mala', 'Gurame Saos Mentega',
            'Gurame Saos Tiram', 'Gurame Saos Lada Hitam', 'Gurame Saos Padang',
            'Gurame Bakar', 'Gurame Goreng', 'Kerapu Tim Biasa', 'Kerapu Panggang',
            'Kerapu Tim Nonya', 'Kerapu Saos Asam Manis', 'Kerapu Kuah Mala',
            'Kerapu Saos Mentega', 'Kerapu Saos Tiram', 'Kerapu Saos Lada Hitam',
            'Kerapu Saos Padang', 'Kerapu Bakar', 'Kerapu Goreng', 'Kuwe Tim Biasa',
            'Kuwe Panggang', 'Kuwe Tim Nonya', 'Kuwe Saos Asam Manis', 'Kuwe Kuah Mala',
            'Kuwe Saos Mentega', 'Kuwe Saos Tiram', 'Kuwe Saos Lada Hitam',
            'Kuwe Saos Padang', 'Kuwe Bakar', 'Kuwe Goreng', 'Bawal Tim Biasa',
            'Bawal Panggang', 'Bawal Tim Nonya', 'Bawal Saos Asam Manis',
            'Bawal Kuah Mala', 'Bawal Saos Mentega', 'Bawal Saos Tiram',
            'Bawal Saos Lada Hitam', 'Bawal Saos Padang', 'Bawal Bakar', 'Bawal Goreng',
            'Kakap Tim Biasa', 'Kakap Panggang', 'Kakap Tim Nonya',
            'Kakap Saos Asam Manis', 'Kakap Kuah Mala', 'Kakap Saos Mentega',
            'Kakap Saos Tiram', 'Kakap Saos Lada Hitam', 'Kakap Saos Padang',
            'Kakap Bakar', 'Kakap Goreng', 'Sukang Tim Biasa', 'Sukang Panggang',
            'Sukang Tim Nonya', 'Sukang Saos Asam Manis', 'Sukang Kuah Mala',
            'Sukang Saos Mentega', 'Sukang Saos Tiram', 'Sukang Saos Lada Hitam',
            'Sukang Saos Padang', 'Sukang Bakar', 'Sukang Goreng', 'Patin Kuah Mala'
        ],
        'Nasi Putih' => ['Nasi Putih'],
        'Minuman' => [
            'Es Kosong', 'Es Teh Manis', 'Es Teh Tawar', 'Es Jeruk', 'Jeruk Hangat',
            'Teh Manis Hangat', 'Teh Tawar Hangat'
        ],
        'Side Dish' => [
            'Tahu Sumedang', 'Tempe Mendoan',
            'Terong Raos Sedap', 'Kangkung Ongseng', 'Tauge Cah Bawang Putih',
            'Pare Cah Bawang Putih', 'Pare Cah Telor',
            'Buncis Bawang Putih', 'Buncis Cah Telor', 'Sambal Dadak', 'Sambal Rawit Keсар'
        ],
        'Lainnya' => []
    ];

    private function getItemCategory($itemName)
    {
        foreach ($this->menuCategories as $category => $items) {
            if (in_array($itemName, $items)) {
                return $category;
            }
        }
        return 'Lainnya';
    }

public function index()
    {
        $aturanMentahDariSession = session('hasil_apriori_untuk_kesimpulan', []);
        $rekomendasiPaketBundling = [];
        $inputParams = session('input_sebelumnya_dm', []);

        if (empty($aturanMentahDariSession)) {
            $rekomendasiPaketBundling = ['info' => 'Belum ada hasil data mining yang diproses atau sesi telah berakhir. Silakan proses terlebih dahulu di halaman Data Mining.'];
        } elseif (isset($aturanMentahDariSession['error'])) {
            $rekomendasiPaketBundling = ['error' => 'Data mining sebelumnya menghasilkan error.', 'details' => $aturanMentahDariSession['details'] ?? 'Tidak ada detail.'];
        } else {
            foreach ($aturanMentahDariSession as $rule) {
                if (!isset($rule['antecedents']) || !isset($rule['consequents']) || !is_array($rule['antecedents']) || !is_array($rule['consequents']) || empty($rule['consequents'])) {
                    continue;
                }

                $antecedentItems = $rule['antecedents'];
                $consequentItem = $rule['consequents'][0];
                $allItemsInRuleNames = array_merge($antecedentItems, [$consequentItem]);

                // ... (Logika untuk identifikasi kategori tetap sama) ...
                $categoriesInRuleCount = ['Menu Utama' => 0, 'Nasi Putih' => 0, 'Minuman' => 0, 'Side Dish' => 0, 'Lainnya' => 0];
                $itemsPerCategory = ['Menu Utama' => [], 'Nasi Putih' => [], 'Minuman' => [], 'Side Dish' => [], 'Lainnya' => []];
                foreach ($allItemsInRuleNames as $itemNama) {
                    $category = $this->getItemCategory($itemNama);
                    $categoriesInRuleCount[$category]++;
                    if (!in_array($itemNama, $itemsPerCategory[$category])) {
                        $itemsPerCategory[$category][] = $itemNama;
                    }
                }

                $antecedentCount = count($antecedentItems);
                $paketTerbentuk = false;
                $namaPaket = "";

                // ... (Logika untuk mencocokkan template paket tetap sama) ...
                if ($antecedentCount == 3) { /* ... */
                    if ($categoriesInRuleCount['Menu Utama'] >= 1 && $categoriesInRuleCount['Nasi Putih'] >= 1 &&
                        $categoriesInRuleCount['Minuman'] >= 1 && $categoriesInRuleCount['Side Dish'] >= 1) {
                        $namaPaket = "Paket Komplit: " . implode(', ', $itemsPerCategory['Menu Utama']) . " + Nasi Putih + " . implode(', ', $itemsPerCategory['Minuman']) . " + " . implode(', ', $itemsPerCategory['Side Dish']);
                        $paketTerbentuk = true;
                    }
                } elseif ($antecedentCount == 2) { /* ... */
                    if ($categoriesInRuleCount['Menu Utama'] >= 1 && $categoriesInRuleCount['Minuman'] >= 1 &&
                        $categoriesInRuleCount['Nasi Putih'] >= 1) {
                        $namaPaket = "Paket Trio: " . implode(', ', $itemsPerCategory['Menu Utama']) . " + Nasi Putih + " . implode(', ', $itemsPerCategory['Minuman']);
                        $paketTerbentuk = true;
                    }
                } elseif ($antecedentCount == 1) { /* ... */
                    if ($categoriesInRuleCount['Menu Utama'] >= 1 && $categoriesInRuleCount['Nasi Putih'] >= 1) {
                        $namaPaket = "Paket Duo: " . implode(', ', $itemsPerCategory['Menu Utama']) . " + Nasi Putih";
                        $paketTerbentuk = true;
                    }
                }

                if ($paketTerbentuk) {
                    // --- AWAL PERUBAHAN: Buat kalimat kesimpulan ---
                    $antecedentsString = implode(', ', $antecedentItems);
                    $kesimpulanText = "Pelanggan yang membeli '" . $antecedentsString . "' memiliki kemungkinan tinggi untuk juga membeli '" . $consequentItem . "'.";
                    // --- AKHIR PERUBAHAN ---

                    $rekomendasiPaketBundling[] = [
                        'nama_paket' => rtrim(trim($namaPaket), '+'),
                        'kesimpulan' => $kesimpulanText, // Tambahkan kunci baru 'kesimpulan'
                        'support' => $rule['support'],
                        'confidence' => $rule['confidence'],
                        'lift' => $rule['lift']
                    ];
                }
            }

            // ... (Logika sorting dan slice untuk top 10 tetap sama) ...
            if (count($rekomendasiPaketBundling) > 0) {
                usort($rekomendasiPaketBundling, function($a, $b) {
                    if ($b['lift'] != $a['lift']) return $b['lift'] <=> $a['lift'];
                    if ($b['confidence'] != $a['confidence']) return $b['confidence'] <=> $a['confidence'];
                    return $b['support'] <=> $a['support'];
                });
                $rekomendasiPaketBundling = array_slice($rekomendasiPaketBundling, 0, 10);
            } elseif (empty($rekomendasiPaketBundling) && !isset($aturanMentahDariSession['error']) && count($aturanMentahDariSession) > 0) {
                $rekomendasiPaketBundling = ['info' => 'Tidak ada rekomendasi paket yang dapat dibentuk dari hasil mining saat ini berdasarkan kriteria yang ditetapkan.'];
            }
        }

        return view('kesimpulan', [
            'rekomendasi_paket_bundling' => $rekomendasiPaketBundling,
            'parameter_input_mining' => $inputParams
        ]);
    }
}
