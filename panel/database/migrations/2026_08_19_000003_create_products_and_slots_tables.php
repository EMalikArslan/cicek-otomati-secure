<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cicek cinsi taksonomisi - "hangi cins nerede daha iyi satiyor" analizinin dayanagi.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable();          // gul|papatya|aranjman|buket|...
            $table->unsignedBigInteger('default_price_minor')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_active']);
        });

        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('slot_no');         // 1..24 (PROTOKOL.md OPEN <goz>)
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name')->nullable();      // serbest metin (eski veriyle uyum)
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->string('image_path')->nullable();
            $table->string('state')->default('empty');       // full|empty|reserved|fault
            $table->timestamp('last_restock_at')->nullable();
            $table->foreignId('last_restock_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['machine_id', 'slot_no']);
            $table->index(['machine_id', 'state']);
        });

        // Dolum gecmisi. "Onceki dolumun adi ve gorseli" isterinin kaynagi;
        // ayrica dolum->satis suresi ve bosta kalma analizleri buradan uretilir.
        Schema::create('slot_restocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('slot_no');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->string('image_path')->nullable();
            $table->string('prev_product_name')->nullable();
            $table->unsignedBigInteger('prev_price_minor')->nullable();
            $table->string('prev_image_path')->nullable();
            $table->timestamp('filled_at');
            $table->timestamp('emptied_at')->nullable();     // satildigi an; dolum->satis suresi
            $table->string('source')->default('panel');      // panel|kiosk|migration
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['machine_id', 'slot_no', 'filled_at']);
        });

        // Fiyat esnekligi analizi: indirim satis hizini nasil degistirdi?
        Schema::create('price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('slot_no');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('old_price_minor');
            $table->unsignedBigInteger('new_price_minor');
            $table->string('reason')->default('manual');     // manual|discount_5|discount_10|discount_20|campaign|restock
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['machine_id', 'slot_no', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_changes');
        Schema::dropIfExists('slot_restocks');
        Schema::dropIfExists('slots');
        Schema::dropIfExists('products');
    }
};
