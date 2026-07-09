<?php

namespace App\Models;

use App\Notifications\BrandedResetPassword;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'branch_id',
        'department_id',
        'signature_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'signature_url',
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
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Whether this user may view records across every branch.
     */
    public function canSeeAllBranches(): bool
    {
        return $this->hasRole('Super Admin')
            || $this->hasAnyRole(['Administrator', 'Managing Partner']);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function getSignatureUrlAttribute(): ?string
    {
        return $this->signature_path
            ? Storage::disk('public')->url($this->signature_path)
            : null;
    }

    public function createdConversations()
    {
        return $this->hasMany(Conversation::class, 'created_by');
    }

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['last_read_at', 'muted_at'])
            ->withTimestamps();
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new BrandedResetPassword($token));
    }

    /**
     * Route notifications for the SMS channel (used by App\Notifications\Channels\SmsChannel).
     */
    public function routeNotificationForSms($notification = null): ?string
    {
        return $this->staffProfile?->phone;
    }
}
