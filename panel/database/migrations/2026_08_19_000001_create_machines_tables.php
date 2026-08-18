<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();               // ETM_001
            $table->string('name');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location_label')->nullable();
            $table->text('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('timezone')->default('Europe/Istanbul');
            $table->unsignedSmallInteger('slot_count')->default(24);
            $table->string('firmware_version')->nullable();
            $table->string('hw_revision')->nullable();
            $table->string('status')->default('active');    // active|maintenance|retired
            $table->smallInteger('temp_min')->nullable();
            $table->smallInteger('temp_max')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('owner_user_id');
        });

        // Anlik durum: panelin hizli okudugu tek satirlik ozet (zaman serisinden ayri tutulur).
        Schema::create('machine_states', function (Blueprint $table) {
            $table->foreignId('machine_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->boolean('stm_online')->nullable();       // STM32 seri baglantisi canli mi
            $table->string('ip')->nullable();
            $table->unsignedBigInteger('uptime_s')->nullable();
            $table->decimal('reported_lat', 10, 7)->nullable();
            $table->decimal('reported_lng', 10, 7)->nullable();
            $table->string('agent_version')->nullable();
            $table->timestamps();

            $table->index('is_online');
        });

        // Cihaz kimligi: MQTT parolasi dogrulama icin hash, komut imzalama icin sifreli secret.
        Schema::create('device_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->string('mqtt_username')->unique();
            $table->string('secret_hash');                   // MQTT parolasi (hash)
            $table->text('command_secret');                  // HMAC imza anahtari (encrypted cast)
            $table->string('cert_fingerprint')->nullable();
            $table->timestamp('last_auth_at')->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['machine_id', 'revoked_at']);
        });

        // Yuksek hacimli zaman serisi. 90 gun ham tutulur, sonrasi ozetlenip silinir.
        Schema::create('machine_telemetry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('temperature', 5, 2)->nullable();
            $table->boolean('stm_online')->nullable();
            $table->unsignedBigInteger('uptime_s')->nullable();
            $table->json('extra')->nullable();

            $table->index(['machine_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_telemetry');
        Schema::dropIfExists('device_credentials');
        Schema::dropIfExists('machine_states');
        Schema::dropIfExists('machines');
    }
};
