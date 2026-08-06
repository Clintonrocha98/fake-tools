<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fake_binance_withdrawals', static function (Blueprint $table): void {
            $table->timestampTz('completed_at')->nullable()->after('applied_at');
        });
    }
};
