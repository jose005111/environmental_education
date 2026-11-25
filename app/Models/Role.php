<?php

namespace App\Models;

use App\Enums\RoleName;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property RoleName $name
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $fillable = ['name', 'label'];

    protected function casts(): array
    {
        return ['name' => RoleName::class];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
