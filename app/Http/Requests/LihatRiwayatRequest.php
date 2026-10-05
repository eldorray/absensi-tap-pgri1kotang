<?php

namespace App\Http\Requests;

use App\Actions\Absensi\DataRiwayat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class LihatRiwayatRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bulan' => [
                'nullable',
                'date_format:Y-m',
                function (string $attribute, mixed $value, Closure $fail): void {
                    // Perbandingan string aman karena formatnya sudah Y-m.
                    if ((string) $value > Carbon::today()->format('Y-m')) {
                        $fail('Riwayat bulan yang belum berjalan belum tersedia.');
                    }

                    if ((string) $value < DataRiwayat::BULAN_PALING_AWAL) {
                        $fail('Riwayat paling awal Januari 2020.');
                    }
                },
            ],
        ];
    }

    public function bulan(): ?string
    {
        $bulan = $this->validated('bulan');

        return is_string($bulan) ? $bulan : null;
    }
}
