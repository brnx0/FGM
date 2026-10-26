<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['cpf', 'name', 'email', 'email_verified', 'phone'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified' => 'boolean',
            'profile_completed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return HasOne<PessoaFisica, $this> */
    public function pessoaFisica(): HasOne
    {
        return $this->hasOne(PessoaFisica::class);
    }
}
