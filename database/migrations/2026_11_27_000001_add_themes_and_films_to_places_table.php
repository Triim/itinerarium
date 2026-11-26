<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing places keep null, which means no themes and no films.
        Schema::table('places', function (Blueprint $table) {
            $table->json('themes')->nullable();
            $table->json('films')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn(['themes', 'films']);
        });
    }
};
