<?php

namespace App\Console\Commands;

use App\Actions\Absensi\CatatMasukKelas;
use App\Models\AbsensiKelas;
use App\Models\Scopes\TahunAjaranScope;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

#[Signature('masuk-kelas:hapus-foto-lama')]
#[Description('Hapus foto masuk kelas yang melewati masa simpan; barisnya tetap untuk rekap')]
class HapusFotoMasukKelasLama extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk(CatatMasukKelas::DISK);
        $dihapus = 0;

        AbsensiKelas::query()
            // Semua tahun ajaran: foto tahun lalu juga harus kedaluwarsa.
            ->withoutGlobalScope(TahunAjaranScope::class)
            ->whereNotNull('foto_path')
            ->where('tanggal', '<', today()->subDays(AbsensiKelas::RETENSI_FOTO_HARI))
            ->chunkById(200, function (Collection $baris) use ($disk, &$dihapus): void {
                foreach ($baris as $absen) {
                    /** @var AbsensiKelas $absen */
                    $disk->delete((string) $absen->foto_path);
                    $absen->update(['foto_path' => null]);
                    $dihapus++;
                }
            });

        $this->info("{$dihapus} foto masuk kelas dihapus.");

        return self::SUCCESS;
    }
}
