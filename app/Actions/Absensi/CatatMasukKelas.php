<?php

namespace App\Actions\Absensi;

use App\Enums\StatusPerangkat;
use App\Models\Absensi;
use App\Models\AbsensiKelas;
use App\Models\JadwalKerja;
use App\Models\Kelas;
use App\Models\Perangkat;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Catat guru masuk kelas jam pertama, dengan selfie bersama siswa sebagai bukti.
 *
 * Geofence sengaja tidak diulang: syaratnya sudah tap masuk hari ini, dan tap
 * itu sudah lolos geofence, HP terdaftar, dan passkey. GPS juga tidak bisa
 * membedakan ruang kelas dari ruang guru -- buktinya adalah fotonya.
 */
class CatatMasukKelas
{
    /**
     * Disk privat: foto anak tidak boleh punya URL publik.
     */
    public const DISK = 'local';

    /**
     * @throws ValidationException
     */
    public function __invoke(User $guru, int $kelasId, UploadedFile $foto, string $perangkatUuid): AbsensiKelas
    {
        $batas = JadwalKerja::batasMasukKelas(today());

        if ($batas === null) {
            $this->tolak('Hari ini tidak ada absen masuk kelas.');
        }

        $sudahTapMasuk = Absensi::query()
            ->where('user_id', $guru->id)
            ->whereDate('tanggal', today())
            ->whereNotNull('masuk_attempt_id')
            ->exists();

        if (! $sudahTapMasuk) {
            $this->tolak('Tap masuk dulu sebelum absen masuk kelas.');
        }

        $hpTerdaftar = Perangkat::query()
            ->where('user_id', $guru->id)
            ->where('uuid', $perangkatUuid)
            ->where('status', StatusPerangkat::Active)
            ->exists();

        if (! $hpTerdaftar) {
            $this->tolak('HP ini belum terdaftar untuk akunmu. Hubungi TU.');
        }

        $kelas = Kelas::query()
            ->whereKey($kelasId)
            ->where('is_active', true)
            ->when($guru->kantor_id !== null, fn ($query) => $query->where('kantor_id', $guru->kantor_id))
            ->first();

        if ($kelas === null) {
            $this->tolak('Kelas tidak ditemukan.');
        }

        $sudahAbsenKelas = AbsensiKelas::query()
            ->where('user_id', $guru->id)
            ->whereDate('tanggal', today())
            ->exists();

        if ($sudahAbsenKelas) {
            $this->tolak('Sudah absen masuk kelas hari ini.');
        }

        // Satu kelas satu guru: yang pertama absen yang menang.
        // ponytail: cek aplikasi, bukan unique(kelas_id, tanggal). Data lama
        // sudah punya kelas berguru ganda (migrasi unique akan gagal). Dua
        // kiriman nyaris bersamaan ke kelas yang sama masih bisa lolos keduanya;
        // kalau itu terjadi, lockForUpdate baris kelas di dalam transaksi.
        $pengisi = AbsensiKelas::query()
            ->with('user:id,name')
            ->where('kelas_id', $kelas->id)
            ->whereDate('tanggal', today())
            ->first();

        if ($pengisi !== null) {
            $this->tolak("{$kelas->nama} sudah diisi {$pengisi->user->name}. Pilih kelas lain.");
        }

        $path = $foto->store('absensi-kelas/'.now()->format('Y/m'), self::DISK);

        if ($path === false) {
            $this->tolak('Foto gagal disimpan. Coba kirim lagi.');
        }

        try {
            return AbsensiKelas::create([
                'user_id' => $guru->id,
                'kelas_id' => $kelas->id,
                // Carbon::today(), bukan today(): cast 'date' bertipe Carbon mutable.
                'tanggal' => Carbon::today(),
                'menit_terlambat' => max(0, (int) floor($batas->diffInSeconds(now()) / 60)),
                'foto_path' => $path,
                'perangkat_uuid' => $perangkatUuid,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Kirim serentak: dua request lolos cek duplikat sebelum salah satunya
            // tersimpan. Foto yang telanjur tersimpan dibuang supaya tidak yatim.
            Storage::disk(self::DISK)->delete($path);
            $this->tolak('Sudah absen masuk kelas hari ini.');
        }
    }

    public static function pesanTercatat(AbsensiKelas $absen): string
    {
        return sprintf(
            'Masuk %s tercatat pukul %s.%s',
            $absen->kelas->nama,
            now()->format('H.i'),
            $absen->menit_terlambat > 0 ? " Telat {$absen->menit_terlambat} menit." : ' Tepat waktu.',
        );
    }

    /**
     * @throws ValidationException
     */
    private function tolak(string $pesan): never
    {
        throw ValidationException::withMessages(['masuk_kelas' => $pesan]);
    }
}
