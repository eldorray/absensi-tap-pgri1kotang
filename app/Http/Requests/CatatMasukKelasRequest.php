<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CatatMasukKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kelas tidak divalidasi dengan exists di sini: Rule::exists melewati scope
     * tahun ajaran. Keanggotaan kelas diperiksa CatatMasukKelas.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kelas_id' => ['required', 'integer'],
            'foto' => ['required', 'file', 'mimes:jpg,jpeg', 'max:1024'],
            'device_uuid' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.required' => 'Ambil foto bersama siswa dulu.',
            'foto.mimes' => 'Foto harus diambil dari kamera aplikasi.',
            'foto.max' => 'Foto terlalu besar. Ambil ulang.',
        ];
    }
}
