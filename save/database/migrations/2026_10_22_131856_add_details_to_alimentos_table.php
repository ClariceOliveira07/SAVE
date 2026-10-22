<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('alimentos', function (Blueprint $table) {
            $table->foreignId('user_id')->after('id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('categoria')->default('Outros');
            $table->decimal('quantidade', 8, 2)->default(1);
            $table->string('unidade')->default('unidades');
            $table->string('embalagem')->nullable();
            $table->date('validade')->nullable();
            $table->decimal('preco', 10, 2)->nullable();
            $table->string('codigo_barras', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alimentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'nome',
                'categoria',
                'quantidade',
                'unidade',
                'embalagem',
                'validade',
                'preco',
                'codigo_barras',
            ]);
        });
    }
};
