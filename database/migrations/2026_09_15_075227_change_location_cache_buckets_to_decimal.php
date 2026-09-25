<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location_cache', function (Blueprint $table) {
            $table->decimal('latitude_bucket', 10, 4)
                ->change();

            $table->decimal('longitude_bucket', 10, 4)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('location_cache', function (Blueprint $table) {
            $table->string('latitude_bucket', 20)
                ->change();

            $table->string('longitude_bucket', 20)
                ->change();
        });
    }
};
