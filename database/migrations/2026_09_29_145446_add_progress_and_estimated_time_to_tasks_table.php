<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percentage')
                ->default(0);

            $table->unsignedInteger('estimated_minutes')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'progress_percentage',
                'estimated_minutes',
            ]);
        });
    }
};