<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'nome', 'categoria', 'quantidade', 'unidade', 'embalagem', 'validade', 'preco', 'codigo_barras'])]
class Alimento extends Model
{
    protected function casts(): array
    {
        return [
            'validade' => 'date',
            'quantidade' => 'decimal:2',
            'preco' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
