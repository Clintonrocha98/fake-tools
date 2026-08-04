<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_spot_orders', static function (Blueprint $table): void {
            // Vocabulário de wire fora de OrderStatus — ecoado verbatim em
            // SpotOrderView::toWireArray(), nunca traduzido para um caso conhecido do enum.
            $table->string('raw_status_override')->nullable();
        });
    }
};
