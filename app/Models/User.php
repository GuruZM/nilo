<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_company_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function companies() 
{
    return $this->belongsToMany(Company::class)
        ->withPivot(['is_owner', 'status'])
        ->withTimestamps();
}

public function currentCompany() 
{
    return $this->belongsTo(Company::class, 'current_company_id');
}

public function isMemberOfCompany(int $companyId): bool
{
    return $this->companies()->where('companies.id', $companyId)->exists();
}

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
        ];
    }
    /**
     * The companies this user belongs to.
     */
 
    /**
     * Companies owned by this user.
     */
    public function ownedCompanies()
    {
        return $this->hasMany(Company::class, 'owner_id');
    }

    /**
     * CompanyUser pivot records for this user.
     */
    public function companyUsers()
    {
        return $this->hasMany(CompanyUser::class);
    }
}
