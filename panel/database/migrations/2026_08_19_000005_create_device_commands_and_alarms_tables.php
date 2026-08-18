<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Komut yasam dongusu (PLAN.md 5.4).
         *
         * Her uzaktan mudahale once buraya yazilir, sonra imzalanip MQTT'ye
         * gonderilir, cihazdan ACK gelince kapanir. "Kapak acildi mi" ve
         * "kim acti" sorularinin tek cevap kaynagi bu tablodur.
         */
        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                  // nonce; cihaz tarafinda tekrar korumasi
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');                          // open_slot|open_lid|set_slot_config|set_price|ping|reboot
            $table->json('args')->nullable();
            // queued -> published -> acked | nacked | expired | failed
            $table->string('status')->default('queued');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('error_code')->nullable();        // NAK_EBUSY|NAK_ELOCK|TIMEOUT|BADSIG|EXPIRED|DUP
            $table->json('result')->nullable();
            $table->string('reason')->nullable();            // test|dolum|ariza (opsiyonel gerekce)
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['machine_id', 'created_at']);
            $table->index(['machine_id', 'status']);
            $table->index('status');
        });

        Schema::create('alarms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            // offline|temp_high|temp_low|lid_failed|payment_suspicious|stm_fault|rate_limit_exceeded
            $table->string('code');
            $table->string('severity')->default('warning');  // info|warning|critical
            $table->json('detail')->nullable();
            $table->timestamp('opened_at');
            $table->foreignId('acknowledged_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['machine_id', 'code', 'resolved_at']);
            $table->index(['severity', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarms');
        Schema::dropIfExists('device_commands');
    }
};
