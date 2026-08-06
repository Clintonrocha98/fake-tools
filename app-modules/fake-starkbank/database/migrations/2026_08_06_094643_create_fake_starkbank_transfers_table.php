<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fake_starkbank_transfers', static function (Blueprint $table): void {
            // A pk é o id do StarkBank (string numérica de 16 dígitos): é ele o
            // providerRef que o consumidor sela no Payout e relê por GET.
            $table->string('id')->primary();
            $table->unsignedBigInteger('amount');
            $table->string('name');
            $table->string('tax_id');
            $table->string('bank_code');
            // Blobs do DICT, aceitos como chegaram: são opacos por contrato e o
            // fake não é quem os emitiu quando a entry não vem do seed.
            $table->string('branch_code');
            $table->text('account_number');
            $table->string('account_type');
            // A chave de idempotência do provedor. Nullable porque o campo é
            // opcional no contrato — e em Postgres vários NULL convivem sob um
            // índice único, que é exatamente o comportamento desejado: sem
            // externalId não há o que deduplicar.
            $table->string('external_id')->nullable()->unique();
            $table->string('status');
            $table->jsonb('tags');
            $table->timestampsTz();

            // A varredura do extrato lê por status na ordem de criação, que é
            // também a ordem do cursor de paginação.
            $table->index(['status', 'created_at']);
        });
    }
};
