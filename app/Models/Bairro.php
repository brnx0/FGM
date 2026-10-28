<?php

namespace App\Models;

use Database\Factories\BairroFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bairro oficial de Salvador; pertence a uma só Prefeitura-Bairro. */
class Bairro extends Model
{
    /** @use HasFactory<BairroFactory> */
    use HasFactory;

    protected $fillable = ['nome'];

    /** @return BelongsTo<PrefeituraBairro, $this> */
    public function prefeituraBairro(): BelongsTo
    {
        return $this->belongsTo(PrefeituraBairro::class);
    }
}
