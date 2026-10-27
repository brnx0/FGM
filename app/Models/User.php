<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['cpf', 'name', 'email', 'email_verified', 'phone', 'password', 'ativo'])]
#[Hidden(['password'])]
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
            'password' => 'hashed',
            'ativo' => 'boolean',
            'profile_completed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return Attribute<?string, ?string> */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: function (?string $valor): ?string {
                $normalizado = mb_strtolower(trim((string) $valor));

                return $normalizado === '' ? null : $normalizado;
            },
        );
    }

    /** @return HasOne<PessoaFisica, $this> */
    public function pessoaFisica(): HasOne
    {
        return $this->hasOne(PessoaFisica::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function papeis(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function temPapel(string $slug): bool
    {
        return $this->papeis()->where('slug', $slug)->exists();
    }

    public function ehGestor(): bool
    {
        return $this->ativo && $this->papeis()->exists();
    }
}
