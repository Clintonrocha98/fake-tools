<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_withdrawals', static function (Blueprint $table): void {
            // Congelamento pausa o avanço lazy (AdvanceWithdrawStatus) sem tocar em `status`.
            $table->boolean('frozen')->default(value: false);
            // Vocabulário de wire fora de WithdrawStatus (0-6) — ecoado verbatim em
            // WithdrawHistoryRow::status, nunca traduzido para um caso conhecido do enum.
            $table->integer('raw_status_override')->nullable();
        });
    }
};
