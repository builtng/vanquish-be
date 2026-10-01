<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'training_counsellor_id',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
        ];
    }

    /**
     * Get the training counsellor associated with this user (for counsellor role)
     */
    public function trainingCounsellor(): BelongsTo
    {
        return $this->belongsTo(TrainingCounsellor::class, 'training_counsellor_id');
    }

    /**
     * Get the permissions for this user
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    /**
     * Get messages sent by this user
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'from_user_id');
    }

    /**
     * Get messages received by this user
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'to_user_id');
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permissionName): bool
    {
        // Super Admins and Admins have all permissions
        if (in_array($this->role, ['super_admin', 'admin'])) {
            return true;
        }

        // Check if user has the specific permission
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    /**
     * Check if user can perform an action based on role and permissions
     */
    public function canPerform(string $action): bool
    {
        // Super Admins and Admins can do everything
        if (in_array($this->role, ['super_admin', 'admin'])) {
            return true;
        }

        // Consultation Staff
        if ($this->role === 'consultation_staff') {
            $allowed = [
                'view_consultations',
                'manage_bookings',
                'view_clients',
                'view_trainee_applications'
            ];
            $denied = [
                'financial_access',
                'system_settings',
                'user_management',
                'manage_finance',
                'manage_settings'
            ];

            if (in_array($action, $denied)) return false;
            return in_array($action, $allowed) || $this->hasPermission($action);
        }

        // Compliance Officer
        if ($this->role === 'compliance_officer') {
            $denied = ['financial_access', 'manage_finance'];
            if (in_array($action, $denied)) return false;
            return true; // Compliance can see most things except finance
        }

        // Staff (Legacy role) can do most things (unless restricted by permissions)
        if ($this->role === 'staff') {
            if ($this->hasPermission('deny_' . $action)) {
                return false;
            }
            return true;
        }

        // Counsellors have minimal permissions
        if ($this->role === 'counsellor') {
            $allowedActions = ['view_own_clients', 'view_own_sessions', 'send_message'];
            return in_array($action, $allowedActions) || $this->hasPermission($action);
        }

        return false;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    /**
     * Check if user is staff (any admin/staff role)
     */
    public function isStaff(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'staff', 'consultation_staff', 'compliance_officer']);
    }

    /**
     * Check if user is counsellor
     */
    public function isCounsellor(): bool
    {
        return $this->role === 'counsellor';
    }
}
