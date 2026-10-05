<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Butuh cron hosting: * * * * * php artisan schedule:run (lihat deploy-hostinger.sh).
Schedule::command('masuk-kelas:kirim-kelas-kosong')->everyMinute()->withoutOverlapping();
Schedule::command('masuk-kelas:hapus-foto-lama')->dailyAt('01:00');
