<?php

use App\Http\Controllers\TransaksiController;
use Illuminate\Support\Facades\Route;

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

Route::get('/', [TransaksiController::class, 'index'])->name('transaksi.index');
Route::post('/transaksi/upload', [TransaksiController::class, 'uploadExcel'])->name('transaksi.upload');
Route::get('/import-status', [TransaksiController::class, 'getImportStatus'])->name('transaksi.import.status');
