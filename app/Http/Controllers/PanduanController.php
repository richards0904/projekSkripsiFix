<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PanduanController extends Controller
{
    /**
     * Menampilkan halaman panduan penggunaan.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('panduan');
    }
}
