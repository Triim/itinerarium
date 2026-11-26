<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New itineraries start as drafts. Itineraries that already exist were public
     * before the approval workflow, so they become published and stay visible.
     */
    public function up(): void
    {
        Schema::table('itineraries', function (Blueprint $table) {
            $table->string('status')->default('draft');
        });

        DB::table('itineraries')->update(['status' => 'published']);
    }

    public function down(): void
    {
        Schema::table('itineraries', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
