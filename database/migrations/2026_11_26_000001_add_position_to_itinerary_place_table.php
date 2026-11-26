<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itinerary_place', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0);
        });

        // Existing stops keep the order they were shown in: alphabetical by place name, then id.
        $links = DB::table('itinerary_place')
            ->join('places', 'places.id', '=', 'itinerary_place.place_id')
            ->orderBy('itinerary_place.itinerary_id')
            ->orderBy('places.name')
            ->orderBy('places.id')
            ->get(['itinerary_place.itinerary_id', 'itinerary_place.place_id']);

        $positions = [];

        foreach ($links as $link) {
            $position = $positions[$link->itinerary_id] = ($positions[$link->itinerary_id] ?? 0) + 1;

            DB::table('itinerary_place')
                ->where('itinerary_id', $link->itinerary_id)
                ->where('place_id', $link->place_id)
                ->update(['position' => $position]);
        }
    }

    public function down(): void
    {
        Schema::table('itinerary_place', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
