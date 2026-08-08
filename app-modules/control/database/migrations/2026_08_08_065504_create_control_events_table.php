<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_events', function (Blueprint $table): void {
            // O `id` É o cursor do feed: monotônico e barato, `?after=<id>` vira
            // um `where('id', '>', ...)`. Um uuid aqui não ordenaria nada.
            $table->bigIncrements('id');
            $table->string('channel');
            $table->string('level');
            $table->text('message');
            $table->jsonb('context');
            // Nullable porque comando artisan e sweep não têm request.
            $table->uuid('request_id')->nullable();
            // O instante REAL do evento, tirado do record do Monolog — nunca o
            // do flush. O feed existe para mostrar o timing de verdade.
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index('occurred_at');
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_events');
    }
};
