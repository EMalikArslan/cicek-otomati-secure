<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('slot_no');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name_snapshot')->nullable();
            $table->string('product_key')->nullable();        // normalize edilmis ad; urun kirilimi agregasyonu
            $table->unsignedBigInteger('price_minor');
            // success  : odeme alindi, kapak acildi (ACK)
            // suspicious: POS timeout - banka onaylamis olabilir (PROTOKOL.md 5.1 dugum J)
            // lid_failed: odeme alindi, kapak acilmadi (dugum P)
            // refunded : iade edildi
            $table->string('status')->default('success');
            $table->timestamp('sold_at');
            $table->unsignedBigInteger('local_id')->nullable(); // Pi SQLite satislar.id
            $table->string('payment_ref')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            // Ayni satisin tekrar gonderiminde cift kayit olusmasini engeller.
            $table->unique(['machine_id', 'local_id']);
            $table->index(['machine_id', 'sold_at']);
            $table->index(['machine_id', 'slot_no', 'sold_at']);
            $table->index(['machine_id', 'status']);
            $table->index(['product_key', 'sold_at']);
        });

        // ---- Onceden hesaplanmis ozetler --------------------------------------
        // Panel grafikleri YALNIZCA bu tablolari okur; ham `sales` taranmaz.

        Schema::create('sales_hourly_agg', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->timestamp('bucket_hour');
            $table->unsignedSmallInteger('slot_no');
            $table->unsignedInteger('qty')->default(0);
            $table->unsignedBigInteger('revenue_minor')->default(0);
            $table->timestamps();

            $table->unique(['machine_id', 'bucket_hour', 'slot_no'], 'sales_hourly_agg_unique');
            $table->index(['machine_id', 'bucket_hour']);
        });

        Schema::create('sales_daily_agg', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->date('bucket_date');
            $table->unsignedSmallInteger('slot_no');
            $table->unsignedInteger('qty')->default(0);
            $table->unsignedBigInteger('revenue_minor')->default(0);
            $table->timestamps();

            $table->unique(['machine_id', 'bucket_date', 'slot_no'], 'sales_daily_agg_unique');
            $table->index(['machine_id', 'bucket_date']);
        });

        // Urun cinsi kirilimi (pasta grafik). product_key serbest metin adlari da tasir.
        Schema::create('product_daily_agg', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->date('bucket_date');
            $table->string('product_key');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('qty')->default(0);
            $table->unsignedBigInteger('revenue_minor')->default(0);
            $table->timestamps();

            $table->unique(['machine_id', 'bucket_date', 'product_key'], 'product_daily_agg_unique');
            $table->index(['machine_id', 'bucket_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_daily_agg');
        Schema::dropIfExists('sales_daily_agg');
        Schema::dropIfExists('sales_hourly_agg');
        Schema::dropIfExists('sales');
    }
};
