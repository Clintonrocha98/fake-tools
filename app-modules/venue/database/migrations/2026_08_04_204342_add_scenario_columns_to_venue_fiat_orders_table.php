<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_fiat_orders', static function (Blueprint $table): void {
            // Congelamento pausa o avanço lazy (GetFiatOrderDetail::lazyStatus()) sem
            // tocar em `status`/`forced_status` — descongelar retoma de onde estava.
            $table->boolean('frozen')->default(value: false);
            // N leituras de get-order-detail sem `pixcode` antes de o brcode aparecer —
            // null preserva o comportamento atual (brcode imediato desde a abertura).
            $table->unsignedInteger('brcode_delay_reads')->nullable();
            $table->unsignedInteger('brcode_reads_count')->default(0);
        });
    }
};
