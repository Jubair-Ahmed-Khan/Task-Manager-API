
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_time_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                ->constrained('tasks')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('started_at');

            $table->timestamp('ended_at')->nullable();

            $table->unsignedInteger('duration_seconds')
                ->default(0);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['task_id', 'user_id']);
            $table->index(['user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_time_entries');
    }
};