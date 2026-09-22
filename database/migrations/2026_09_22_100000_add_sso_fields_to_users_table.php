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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('kpfc_sub')->nullable()->unique()->after('id');
            $table->string('phone')->nullable()->after('email');
            $table->string('role')->default('user')->after('phone');
            $table->boolean('fleet_access')->default(true)->after('role');
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['kpfc_sub']);
            $table->dropColumn(['kpfc_sub', 'phone', 'role', 'fleet_access']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
