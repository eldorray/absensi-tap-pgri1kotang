<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class PilihTanggalAbsensiSiswaRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tanggal' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * Tanggal yang sedang dilihat. Hanya hari ini yang bisa disunting: hari
     * lampau dibuka untuk membaca hasil finalisasi, hari depan tidak ada.
     */
    public function tanggal(): Carbon
    {
        $tanggal = $this->validated('tanggal');

        return is_string($tanggal) ? Carbon::createFromFormat('Y-m-d', $tanggal)->startOfDay() : Carbon::today();
    }
}
