<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Olay x kanal matrisi. machine_id NULL ise ayar tum otomatlar icin gecerlidir.
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained()->cascadeOnDelete();
            // sale|offline|temp_alarm|lid_failed|payment_suspicious|low_stock|daily_summary|ticket_reply
            $table->string('event');
            $table->string('channel');                       // mail|sms|whatsapp|push
            $table->string('target')->nullable();            // farkli adres/numara kullanilacaksa
            $table->boolean('is_enabled')->default(true);
            $table->json('quiet_hours')->nullable();         // {"from":"23:00","to":"08:00"}
            $table->timestamps();

            $table->index(['user_id', 'event', 'channel']);
            $table->index(['machine_id', 'event']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('event');
            $table->string('provider')->nullable();          // netgsm|resend|meta_whatsapp|webpush
            $table->string('provider_message_id')->nullable();
            $table->string('status')->default('queued');     // queued|sent|delivered|failed|bounced
            $table->string('target')->nullable();            // maskelenmis (05** *** 12 34)
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('cost_minor')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['channel', 'created_at']);
        });

        // KVKK / IYS: onay kaydi. Metin surumu + zaman + IP ile ispatlanabilir olmali.
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');                       // sms|email|whatsapp
            $table->string('target_hash');                   // aranabilirlik icin hash
            $table->string('target_masked');                 // gosterim icin maskeli
            $table->string('text_version');                  // aydinlatma metni surumu
            $table->string('source')->default('kiosk');      // kiosk|panel|web
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip')->nullable();
            $table->timestamps();

            $table->index(['channel', 'target_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_settings');
    }
};
