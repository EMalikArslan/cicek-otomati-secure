<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            // Super admin bayragi Gate::before ile tum yetkileri acar (PLAN.md 7).
            $table->boolean('is_super_admin')->default(false)->after('password');
            $table->string('status')->default('pending')->after('is_super_admin'); // pending|active|suspended
            $table->string('locale', 5)->default('tr')->after('status');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->softDeletes();

            $table->index(['status', 'is_super_admin']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_super_admin']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'phone', 'is_super_admin', 'status', 'locale',
                'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
