<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kullanici <-> Otomat yetki matrisi.
     *
     * Bir kullanici birden fazla otomata, bir otomat birden fazla kullaniciya
     * baglanabilir. `permissions` JSON'u sayfa/eylem bazli izinleri tutar; bu
     * satiri yalnizca super admin olusturabilir/degistirebilir (MachineUserPolicy).
     */
    public function up(): void
    {
        Schema::create('machine_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('operator');    // owner|operator|viewer
            $table->json('permissions')->nullable();        // {"slots.open": true, ...}
            $table->foreignId('granted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['machine_id', 'user_id']);
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_user');
    }
};
