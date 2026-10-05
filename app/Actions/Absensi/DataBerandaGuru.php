<?php

namespace App\Actions\Absensi;

use App\Models\Absensi;
use App\Models\AbsensiKelas;
use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\Lokasi;
use App\Models\PengaturanAbsensi;
use App\Models\Pengumuman;
use App\Models\Perangkat;
use App\Models\User;

/**
 * Isi halaman tap guru: jadwal dan status hari ini. Dipakai halaman web dan
 * API aplikasi Android supaya keduanya selalu melihat angka yang sama.
 */
class DataBerandaGuru
{
    public function __construct(private MasukKelasHariIni $masukKelasHariIni) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(User $guru, ?string $uuidPerangkat): array
    {
        $jadwal = JadwalKerja::hariUntukGuru((int) $guru->id, now()->dayOfWeek);
        $pengaturan = PengaturanAbsensi::current();

        $hariIni = Absensi::query()
            ->with(['masukAttempt', 'pulangAttempt'])
            ->where('user_id', $guru->id)
            ->whereDate('tanggal', today())
            ->first();

        return [
            'jadwal' => $jadwal === null ? null : [
                'jam_masuk' => substr($jadwal->jam_masuk, 0, 5),
                'jam_pulang' => substr($jadwal->jam_pulang, 0, 5),
                'toleransi_menit' => $pengaturan->toleransi_menit,
                'buka_masuk' => today()->setTimeFromTimeString($jadwal->jam_masuk)
                    ->subMinutes($pengaturan->buka_masuk_menit)->format('H:i'),
                'tutup_masuk' => today()->setTimeFromTimeString($jadwal->jam_masuk)
                    ->addMinutes($pengaturan->tutup_masuk_menit)->format('H:i'),
                'buka_pulang' => today()->setTimeFromTimeString($jadwal->jam_pulang)
                    ->subMinutes($pengaturan->buka_pulang_menit)->format('H:i'),
                'is_hari_kerja' => $jadwal->is_hari_kerja,
            ],
            'hariIni' => $hariIni === null ? null : [
                'status' => $hariIni->status?->value,
                'jam_masuk' => $hariIni->masukAttempt?->created_at?->format('H:i'),
                'jam_pulang' => $hariIni->pulangAttempt?->created_at?->format('H:i'),
                'pulang_cepat' => $hariIni->pulang_cepat,
                'terverifikasi' => $hariIni->masukAttempt->terverifikasi ?? false,
            ],
            // Jam server, supaya tombol absen di HP mengikuti jendela yang sama
            // dengan yang dinilai server walau jam HP meleset.
            'waktuServer' => now()->toIso8601String(),
            'libur' => HariLibur::query()->whereDate('tanggal', today())->value('nama'),
            'masukKelas' => $this->masukKelas($guru, $hariIni),
            'pengumumans' => Pengumuman::query()
                ->where('is_active', true)
                ->latest()
                ->latest('id')
                ->limit(5)
                ->get(['id', 'judul', 'isi', 'created_at'])
                ->map(fn (Pengumuman $pengumuman): array => [
                    'id' => $pengumuman->id,
                    'judul' => $pengumuman->judul,
                    'isi' => $pengumuman->isi,
                    'dibuat' => $pengumuman->created_at?->toIso8601String(),
                ]),
            'lokasis' => Lokasi::query()
                ->aktifUntukKantor($guru->kantor_id)
                ->orderBy('nama')
                ->get(['id', 'nama', 'latitude', 'longitude', 'radius_meter']),
            'punyaPasskey' => $guru->hasPasskeysEnabled(),
            'statusPerangkat' => $uuidPerangkat === null ? null : Perangkat::query()
                ->where('user_id', $guru->id)
                ->where('uuid', $uuidPerangkat)
                ->value('status'),
        ];
    }

    /**
     * Kartu masuk kelas di beranda: batasnya, absen guru ini kalau sudah, dan
     * kelas di unitnya beserta guru yang sudah mengisinya.
     *
     * @return array{
     *     batas: string,
     *     sudahTapMasuk: bool,
     *     absen: array{kelas: string, jam: string, menitTerlambat: int}|null,
     *     kelas: list<array{id: int, nama: string, terisiOleh: list<string>}>
     * }|null
     */
    private function masukKelas(User $guru, ?Absensi $hariIni): ?array
    {
        $keadaan = ($this->masukKelasHariIni)($guru->kantor_id);

        if ($keadaan === null) {
            return null;
        }

        $absen = AbsensiKelas::query()
            ->with('kelas:id,nama')
            ->where('user_id', $guru->id)
            ->whereDate('tanggal', today())
            ->first();

        return [
            'batas' => $keadaan['batas'],
            'sudahTapMasuk' => $hariIni?->masuk_attempt_id !== null,
            'absen' => $absen === null ? null : [
                'kelas' => $absen->kelas->nama,
                'jam' => $absen->created_at?->format('H:i') ?? '',
                'menitTerlambat' => $absen->menit_terlambat,
            ],
            'kelas' => array_map(fn (array $kelas): array => [
                'id' => $kelas['id'],
                'nama' => $kelas['nama'],
                'terisiOleh' => array_column($kelas['guru'], 'nama'),
            ], $keadaan['kelas']),
        ];
    }
}
