<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->after('remember_token')
                ->constrained('roles')
                ->restrictOnDelete();
            $table->foreignId('zone_id')
                ->nullable()
                ->after('role_id')
                ->constrained('zones')
                ->nullOnDelete();
            $table->string('phone', 20)->nullable()->after('zone_id');
            $table->boolean('is_active')->default(true)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('zone_id');
            $table->dropColumn(['phone', 'is_active']);
        });
    }
};
