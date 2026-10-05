<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kunci publik HP yang mendaftar lewat aplikasi Android.
     *
     * Pasangan privatnya tersimpan di Android Keystore dan hanya bisa dipakai
     * setelah sidik jari atau kunci layar. Perangkat dari browser tidak punya
     * kunci ini; mereka memakai passkey seperti sebelumnya.
     */
    public function up(): void
    {
        Schema::table('perangkats', function (Blueprint $table): void {
            $table->text('kunci_publik')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('perangkats', function (Blueprint $table): void {
            $table->dropColumn('kunci_publik');
        });
    }
};
