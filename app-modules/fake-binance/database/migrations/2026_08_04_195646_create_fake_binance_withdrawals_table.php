<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fake_binance_withdrawals', static function (Blueprint $table): void {
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
            // Congelamento pausa o avanço lazy (AdvanceWithdrawStatus) sem tocar em `status`.
            $table->boolean('frozen')->default(value: false);
            // Vocabulário de wire fora de WithdrawStatus (0-6) — ecoado verbatim em
            // WithdrawHistoryRow::status, nunca traduzido para um caso conhecido do enum.
            $table->integer('raw_status_override')->nullable();
            $table->timestampsTz();

            $table->index('coin');
        });
    }
};
