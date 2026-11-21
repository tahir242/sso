<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occasions', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('message');
            $table->text('message_ur')->nullable();
            // gregorian: month/day are Gregorian; hijri: month/day are Islamic (Umm al-Qura).
            $table->string('calendar', 10)->default('gregorian');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('day');
            $table->unsignedSmallInteger('duration_days')->default(1);
            // Moon-sighting correction for the current year (hijri occasions).
            $table->date('override_starts_on')->nullable();
            $table->date('override_ends_on')->nullable();
            $table->string('theme', 20)->default('general');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->text('text');
            $table->string('author', 150)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('occasions');
    }
};
