<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\DaftarkanPerangkatRequest as DaftarkanPerangkatWebRequest;
use App\Support\KunciPerangkat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DaftarkanPerangkatRequest extends DaftarkanPerangkatWebRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'kunci_publik' => [
                'nullable',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (KunciPerangkat::pem((string) $value) === null) {
                        $fail('Kunci perangkat tidak dikenali. Perbarui aplikasi.');
                    }
                },
            ],
        ];
    }

    public function kunciPem(): ?string
    {
        $kunci = $this->validated('kunci_publik');

        return is_string($kunci) ? KunciPerangkat::pem($kunci) : null;
    }
}
