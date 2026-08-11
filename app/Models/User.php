<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'parent_user_id',
        'created_by',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'profile_image',
        'theme_preference',
        'permissions',
        'business_id',
        'last_login_at',
        'last_seen_at',
        'last_activity_at',
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
            'permissions' => 'array',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function ownedBusiness()
    {
        return $this->hasOne(Business::class, 'owner_id');
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function children()
    {
        return $this->hasMany(User::class, 'parent_user_id');
    }

    public function businessAssignments()
    {
        return $this->hasMany(BusinessUserAssignment::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }
}
