<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fake_binance_crypto_deposits', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('coin');
            $table->string('network');
            $table->string('address');
            $table->string('address_tag')->nullable();
            $table->decimal('amount', 36, 18);
            $table->string('tx_id');
            $table->unsignedTinyInteger('status');
            // O relógio do avanço lazy (AdvanceCryptoDepositStatus) conta a partir do anúncio.
            $table->timestampTz('announced_at');
            // Guard de idempotência do crédito no ledger: creditado no máximo UMA vez.
            $table->timestampTz('credited_at')->nullable();
            $table->timestampsTz();

            $table->index('coin');
        });
    }
};
