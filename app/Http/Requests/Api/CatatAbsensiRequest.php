<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\CatatAbsensiRequest as CatatAbsensiWebRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class CatatAbsensiRequest extends CatatAbsensiWebRequest
{
    /**
     * Tantangan dan tanda tangan kunci perangkat menggantikan penanda
     * verifikasi passkey yang di web disimpan di sesi.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tantangan' => ['nullable', 'string', 'max:100'],
            'tanda_tangan' => ['nullable', 'string', 'max:500'],
        ];
    }
}
