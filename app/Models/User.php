<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'kpfc_sub', 'phone', 'role', 'fleet_access'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'fleet_access' => 'boolean',
        ];
    }

    /**
     * Get the SSO token record associated with the user.
     *
     * @return HasOne<UserSsoToken, $this>
     */
    public function ssoToken(): HasOne
    {
        return $this->hasOne(UserSsoToken::class);
    }

    /**
     * Check if the user has fleet access enabled.
     */
    public function hasFleetAccess(): bool
    {
        return (bool) $this->fleet_access;
    }

    /**
     * Check if the user is authorized to perform database write/mutation operations.
     */
    public function canWrite(): bool
    {
        if (! $this->hasFleetAccess()) {
            return false;
        }

        $writeRoles = (array) config('fleet.write_roles', ['admin', 'manager', 'fleet_manager']);

        return in_array(strtolower((string) $this->role), array_map('strtolower', $writeRoles), true);
    }

    /**
     * Check if the user has an administrative role.
     */
    public function isAdmin(): bool
    {
        return strtolower((string) $this->role) === 'admin';
    }
}
