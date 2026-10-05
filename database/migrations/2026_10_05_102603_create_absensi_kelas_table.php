<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('absensi_kelas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajarans')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->date('tanggal');
            $table->unsignedSmallInteger('menit_terlambat')->default(0);
            $table->string('foto_path')->nullable();
            $table->string('perangkat_uuid', 36);
            $table->timestamps();

            // Satu guru satu absen kelas per hari; sekaligus penjaga kirim serentak.
            $table->unique(['user_id', 'tanggal']);
            $table->index(['kelas_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi_kelas');
    }
};
