<?php

namespace Database\Factories;

use App\Models\AbsensiKelas;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AbsensiKelas>
 */
class AbsensiKelasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kelas_id' => Kelas::factory(),
            'tanggal' => today()->toDateString(),
            'menit_terlambat' => 0,
            'foto_path' => 'absensi-kelas/'.today()->format('Y/m').'/'.Str::random(40).'.jpg',
            'perangkat_uuid' => (string) Str::uuid(),
        ];
    }

    public function telat(int $menit): static
    {
        return $this->state(fn (array $attributes): array => [
            'menit_terlambat' => $menit,
        ]);
    }
}
