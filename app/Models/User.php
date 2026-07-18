<?php

namespace App\Models;

use App\Notifications\BrandedResetPassword;
use App\Support\StorageUrl;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
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
        'account_type',
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

    public function clientPortalAccount()
    {
        return $this->hasOne(ClientPortalAccount::class);
    }

    public function isClientAccount(): bool
    {
        return $this->account_type === 'client';
    }

    public function assignedRecoveries()
    {
        return $this->hasMany(RecoveryAccount::class, 'assigned_to');
    }

    public function getSignatureUrlAttribute(): ?string
    {
        if ($this->signature_path && Route::has('profile-media.signature')) {
            return route('profile-media.signature', [
                'user' => $this,
                'v' => $this->profileMediaVersion($this->signature_path),
            ], false);
        }

        return StorageUrl::for($this->signature_path, StorageUrl::profileDisk());
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->profile_photo_path && Route::has('profile-media.photo')) {
            return route('profile-media.photo', [
                'user' => $this,
                'v' => $this->profileMediaVersion($this->profile_photo_path),
            ], false);
        }

        return StorageUrl::for($this->profile_photo_path, StorageUrl::profileDisk())
            ?: $this->defaultProfilePhotoUrl();
    }

    private function profileMediaVersion(?string $path): string
    {
        return substr(md5(($path ?: '').'|'.optional($this->updated_at)->timestamp), 0, 12);
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
