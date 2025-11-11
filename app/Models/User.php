<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PlatformAdminRole;
use App\Models\ApiKey;
use App\Models\CopilotPrompt;
use App\Models\PlatformAdmin;
use App\Models\PurchaseRequisition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'role',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

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
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(PurchaseRequisition::class, 'requested_by');
    }

    public function copilotPrompts(): HasMany
    {
        return $this->hasMany(CopilotPrompt::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class, 'owner_user_id');
    }

    public function platformAdmin(): HasOne
    {
        return $this->hasOne(PlatformAdmin::class);
    }

    public function isPlatformAdmin(?PlatformAdminRole $role = null): bool
    {
        $admin = $this->getRelationValue('platformAdmin');

        if ($admin === null) {
            $admin = $this->platformAdmin()->first();
        }

        if ($admin === null || ! $admin->enabled) {
            return false;
        }

        if ($role === null) {
            return true;
        }

        if ($admin->role === PlatformAdminRole::Super) {
            return true;
        }

        return $admin->role === $role;
    }


    public function getRequiresCompanyOnboardingAttribute(): bool
    {
        $company = $this->getRelationValue('company');

        if ($company === null) {
            $company = $this->company()->first();
        }

        if ($company === null) {
            return true;
        }

        return ! $company->hasCompletedBuyerOnboarding();
    }
}
