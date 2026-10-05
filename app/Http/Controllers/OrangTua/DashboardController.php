<?php

namespace App\Http\Controllers\OrangTua;

use App\Actions\OrangTua\DataKehadiranAnak;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DataKehadiranAnak $dataKehadiran): Response
    {
        return Inertia::render('orang-tua/Index', $dataKehadiran($request->user(), $request->integer('siswa')));
    }
}
