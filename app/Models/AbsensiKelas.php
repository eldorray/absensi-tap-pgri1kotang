<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTahunAjaran;
use Database\Factories\AbsensiKelasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bukti satu guru masuk ke satu kelas pada jam pertama.
 *
 * Kewajibannya per kelas, bukan per guru: satu kelas boleh punya beberapa
 * baris di tanggal yang sama, dan guru yang tidak absen kelas tidak dihukum.
 *
 * @property int $id
 * @property int $user_id
 * @property int $kelas_id
 * @property Carbon $tanggal
 * @property int $menit_terlambat 0 = tepat waktu
 * @property string|null $foto_path null setelah file dihapus retensi
 * @property string $perangkat_uuid
 * @property Carbon|null $created_at jam server saat guru masuk kelas
 * @property-read User $user
 * @property-read Kelas $kelas
 */
#[Fillable(['user_id', 'kelas_id', 'tanggal', 'menit_terlambat', 'foto_path', 'perangkat_uuid'])]
class AbsensiKelas extends Model
{
    use BelongsToTahunAjaran;

    /** @use HasFactory<AbsensiKelasFactory> */
    use HasFactory;

    /**
     * Umur foto sebelum dihapus, dalam hari. Konstanta, bukan pengaturan admin:
     * salah ketik di form akan menghapus seluruh foto tanpa bisa dikembalikan.
     */
    public const RETENSI_FOTO_HARI = 60;

    protected $table = 'absensi_kelas';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Kelas, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * Bentuk satu absen untuk layar guru, dashboard, dan rekap.
     *
     * @return array{nama: string, jam: string, menitTerlambat: int, absensiKelasId: int, adaFoto: bool}
     */
    public function ringkasan(): array
    {
        return [
            'nama' => $this->user->name,
            'jam' => $this->created_at?->format('H:i') ?? '',
            'menitTerlambat' => $this->menit_terlambat,
            'absensiKelasId' => $this->id,
            'adaFoto' => $this->foto_path !== null,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'menit_terlambat' => 'integer',
        ];
    }
}
