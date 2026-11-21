<?php

use App\Domain\Identity\Enums\LocationType;
use App\Domain\Identity\Models\Location;
use App\Domain\Identity\Support\LocationCodes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applies the automatic location coding standard to existing places and
 * makes codes unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Location::query()->update(['code' => null]);

        foreach (Location::query()->where('type', LocationType::City)->orderBy('sort_order')->orderBy('id')->get() as $city) {
            LocationCodes::recode($city);
        }

        Schema::table('locations', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });
    }
};
