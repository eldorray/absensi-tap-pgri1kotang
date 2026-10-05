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
        Schema::table('jadwal_kerjas', function (Blueprint $table): void {
            $table->time('jam_masuk_kelas')->nullable()->after('jam_pulang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_kerjas', function (Blueprint $table): void {
            $table->dropColumn('jam_masuk_kelas');
        });
    }
};
