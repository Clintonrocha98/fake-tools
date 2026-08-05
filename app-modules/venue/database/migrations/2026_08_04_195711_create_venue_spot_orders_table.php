<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_spot_orders', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('order_id')->unique();
            $table->string('client_order_id')->unique();
            $table->string('symbol');
            $table->string('side');
            $table->string('type')->default('MARKET');
            $table->string('status');
            $table->decimal('quantity', 36, 18)->nullable();
            $table->decimal('quote_order_qty', 36, 18)->nullable();
            $table->decimal('executed_qty', 36, 18)->default(0);
            $table->decimal('cummulative_quote_qty', 36, 18)->default(0);
            $table->decimal('fill_price', 36, 18)->nullable();
            $table->decimal('commission', 36, 18)->default(0);
            $table->string('commission_asset')->nullable();
            // Vocabulário de wire fora de OrderStatus — ecoado verbatim em
            // SpotOrderView::toWireArray(), nunca traduzido para um caso conhecido do enum.
            $table->string('raw_status_override')->nullable();
            $table->timestampsTz();
        });
    }
};
