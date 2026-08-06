<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fake_starkbank_dict_entries', static function (Blueprint $table): void {
            // A pk é interna (uuid): na wire o `id` de uma entry do DICT é a
            // própria chave PIX, e promovê-la a pk amarraria a linha a um valor
            // que o operador pode querer corrigir.
            $table->uuid('id')->primary();
            $table->string('pix_key')->unique();
            $table->string('type');
            $table->string('name');
            $table->string('tax_id');
            $table->string('owner_type');
            $table->string('bank_name');
            $table->string('ispb');
            // Blobs opacos por contrato: o consumidor nunca os parseia, só os
            // ecoa verbatim em `branchCode`/`accountNumber` do POST /v2/transfer.
            $table->string('branch_code_blob');
            $table->text('account_number_blob');
            $table->string('account_type');
            // Vocabulário aberto (`registered` é o único observado): string crua
            // para que a config possa armar um estado que o fake ainda não
            // modela sem inventar um case de enum.
            $table->string('status');
            $table->timestampsTz();
        });
    }
};
