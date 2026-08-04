<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_fiat_orders', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('order_no')->unique();
            $table->string('currency');
            $table->string('payment_method');
            $table->decimal('amount', 36, 18);
            $table->string('status');
            // Override/congelamento para o painel (#7): quando setado, vence o
            // avanço lazy calculado pela idade da ordem.
            $table->string('forced_status')->nullable();
            $table->string('brcode')->nullable();
            $table->timestampTz('credited_at')->nullable();
            $table->timestampsTz();
        });
    }
};
