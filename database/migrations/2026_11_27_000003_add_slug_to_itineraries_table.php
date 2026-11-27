<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Public itinerary URLs use a readable slug: the title plus the id, e.g. a-weekend-in-madrid-17.
     * The column stays nullable because a new itinerary only gets its slug once its id is known.
     * The same rule as Itinerary::slugFor() is inlined so this migration does not depend on model code.
     */
    public function up(): void
    {
        Schema::table('itineraries', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
        });

        foreach (DB::table('itineraries')->orderBy('id')->get(['id', 'title']) as $itinerary) {
            DB::table('itineraries')
                ->where('id', $itinerary->id)
                ->update(['slug' => (Str::slug(Str::limit($itinerary->title, 80, '')) ?: 'itinerary').'-'.$itinerary->id]);
        }

        // Welcome cards cached before this migration have no slug to link to, so drop them
        // (both keys, as in Itinerary::forgetWelcomeCache()).
        Cache::forget('welcome.recent_itineraries');
        Cache::forget('illuminate:cache:flexible:created:welcome.recent_itineraries');
    }

    public function down(): void
    {
        Schema::table('itineraries', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
