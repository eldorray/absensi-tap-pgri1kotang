<?php

namespace App\Console\Commands;

use App\Actions\Absensi\MasukKelasHariIni;
use App\Notifications\PushAdmin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

#[Signature('masuk-kelas:kirim-kelas-kosong')]
#[Description('Kirim push ke admin berisi kelas yang belum ada gurunya setelah batas masuk kelas')]
class KirimKelasKosong extends Command
{
    /**
     * Lewat sekian menit dari batas, push dianggap basi -- mis. cron sempat
     * mati dan baru jalan lagi siang hari.
     */
    private const JENDELA_MENIT = 30;

    /**
     * Banyaknya nama kelas yang ditulis di isi push; sisanya diringkas.
     */
    private const MAKS_NAMA = 10;

    public function handle(MasukKelasHariIni $masukKelasHariIni): int
    {
        $keadaan = $masukKelasHariIni();

        if ($keadaan === null || ! $keadaan['lewat']) {
            return self::SUCCESS;
        }

        if (now()->greaterThan(today()->setTimeFromTimeString($keadaan['batas'])->addMinutes(self::JENDELA_MENIT))) {
            return self::SUCCESS;
        }

        $kosong = array_column(
            array_filter($keadaan['kelas'], fn (array $kelas): bool => $kelas['status'] === 'kosong'),
            'nama',
        );

        // Kunci per tanggal: scheduler memanggil command ini setiap menit.
        if ($kosong === [] || ! Cache::add('masuk-kelas:push:'.today()->toDateString(), true, now()->endOfDay())) {
            return self::SUCCESS;
        }

        $isi = implode(', ', array_slice($kosong, 0, self::MAKS_NAMA));

        if (count($kosong) > self::MAKS_NAMA) {
            $isi .= ' +'.(count($kosong) - self::MAKS_NAMA).' lainnya';
        }

        // Langsung, bukan kirimKeAdmin(): tidak ada response yang perlu didahulukan.
        Notification::send(PushAdmin::penerima(), new PushAdmin(
            count($kosong).' kelas belum ada guru',
            $isi,
            route('admin.dashboard'),
        ));

        return self::SUCCESS;
    }
}
