<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_event_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['calendar_event_id', 'branch_id']);
            $table->index('branch_id');
        });

        DB::table('calendar_events')
            ->whereNotNull('branch_id')
            ->orderBy('id')
            ->select(['id', 'branch_id'])
            ->chunkById(200, function ($events) {
                DB::table('calendar_event_branch')->insert(
                    $events->map(fn ($event) => [
                        'calendar_event_id' => $event->id,
                        'branch_id' => $event->branch_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_branch');
    }
};
