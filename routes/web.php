<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DataMiningController;
use App\Http\Controllers\TransaksiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KesimpulanController;
use App\Http\Controllers\PanduanController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Authentication Routes
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// Group routes that require authentication
Route::middleware(['auth'])->group(function () {
    Route::get('/', [TransaksiController::class, 'index'])->name('transaksi.index');
    Route::post('/transaksi/import-excel', [TransaksiController::class, 'importExcel'])->name('transaksi.importExcel');
    Route::get('/transaksi/import-status', [TransaksiController::class, 'importStatus'])->name('transaksi.importStatus');
    Route::get('/data-mining', [DataMiningController::class, 'index'])->name('transaksi.dataMining');
    Route::get('/data-mining/get-transaksi', [DataMiningController::class, 'getTransaksiData'])->name('datamining.getTransaksi');
    Route::post('/data-mining/process', [DataMiningController::class, 'processMining'])->name('datamining.process');
    Route::get('/kesimpulan', [KesimpulanController::class, 'index'])->name('kesimpulan.index');
    Route::get('/panduan', [PanduanController::class, 'index'])->name('panduan.index');
});
