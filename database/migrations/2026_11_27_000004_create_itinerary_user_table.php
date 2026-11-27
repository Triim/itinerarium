<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Itineraries users have saved to their collection. Authorship stays itineraries.user_id.
        Schema::create('itinerary_user', function (Blueprint $table) {
            $table->foreignId('itinerary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One save per user, so saving again cannot inflate an itinerary's popularity.
            $table->unique(['itinerary_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itinerary_user');
    }
};
