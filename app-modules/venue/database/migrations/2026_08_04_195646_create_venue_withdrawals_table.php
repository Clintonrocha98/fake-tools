<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_withdrawals', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('coin');
            $table->string('network');
            $table->string('address');
            $table->string('address_tag')->nullable();
            $table->decimal('amount', 36, 18);
            $table->decimal('transaction_fee', 36, 18);
            $table->string('withdraw_order_id')->nullable()->unique();
            $table->unsignedTinyInteger('status');
            $table->string('tx_id')->nullable();
            $table->string('info')->nullable();
            $table->timestampTz('applied_at');
            $table->timestampsTz();

            $table->index('coin');
        });
    }
};
