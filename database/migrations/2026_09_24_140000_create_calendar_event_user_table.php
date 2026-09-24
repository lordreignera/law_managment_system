<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_event_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['calendar_event_id', 'user_id']);
            $table->index('user_id');
        });

        DB::table('calendar_events')
            ->whereNotNull('assigned_to')
            ->orderBy('id')
            ->select(['id', 'assigned_to'])
            ->chunkById(200, function ($events) {
                DB::table('calendar_event_user')->insert(
                    $events->map(fn ($event) => [
                        'calendar_event_id' => $event->id,
                        'user_id' => $event->assigned_to,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_user');
    }
};
