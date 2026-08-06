<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fake_binance_fiat_withdrawals', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('order_id')->unique();
            $table->string('currency');
            $table->string('payment_method');
            $table->decimal('amount', 36, 18);
            $table->string('account_number');
            $table->string('agency')->nullable();
            $table->string('bank_code_for_pix')->nullable();
            $table->string('account_type')->nullable();
            // Idempotência do POST /sapi/v2/fiat/withdraw: repetir o mesmo
            // clientOrderId devolve o mesmo orderId sem debitar de novo.
            $table->string('client_order_id')->unique();
            $table->string('status');
            $table->timestampTz('requested_at');
            $table->timestampsTz();
        });
    }
};
