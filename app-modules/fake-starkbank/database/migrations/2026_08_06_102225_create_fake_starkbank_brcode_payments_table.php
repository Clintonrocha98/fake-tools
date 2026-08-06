<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fake_starkbank_brcode_payments', static function (Blueprint $table): void {
            // A pk é o id do StarkBank (string numérica de 16 dígitos): é ele o
            // providerRef que o consumidor sela na Conversion e relê por GET.
            $table->string('id')->primary();
            // O copia-e-cola verbatim, como chegou no POST. É ele que o eco, a
            // releitura e a listagem devolvem, e é dele que sai a decodificação
            // que validou taxId e amount na criação.
            $table->text('brcode');
            $table->string('tax_id');
            $table->unsignedBigInteger('amount');
            $table->string('status');
            // Obrigatória só quando o BR Code é dinâmico (sem campo 54); o
            // consumidor sempre a envia.
            $table->string('description')->nullable();
            $table->jsonb('tags');
            $table->timestampsTz();

            // A varredura do extrato lê por status na ordem de criação, que é
            // também a ordem do cursor de paginação.
            $table->index(['status', 'created_at']);
        });
    }
};
