<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akilli oneriler (PLAN.md 10).
     *
     * Oneri yalnizca veri olgunluk kapisi asildiktan sonra uretilir
     * (varsayilan: otomat basina >= 200 basarili satis ve >= 30 gun veri).
     * `evidence` alani "neden bunu oneriyorum" aciklamasinin ham verisidir.
     */
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('slot_no')->nullable();
            // product_fit|idle_slot|restock_timing|price_elasticity|stock_forecast|anomaly
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->json('evidence')->nullable();
            $table->decimal('confidence', 4, 3)->nullable(); // 0.000 - 1.000
            $table->unsignedInteger('data_points')->default(0);
            $table->timestamp('generated_at');
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('dismissed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['machine_id', 'type', 'dismissed_at']);
            $table->index(['machine_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
