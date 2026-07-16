<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const AUDIENCE_TYPES = [
        'self' => 'Myself',
        'users' => 'Individual User(s)',
        'client_matter' => 'Client Matter',
        'department' => 'Department',
        'branch' => 'Branch',
        'firm' => 'All Branches',
    ];

    protected $guarded = [];

    protected $casts = [
        'allow_replies' => 'boolean',
        'is_broadcast' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('participants', fn (Builder $query) => $query->where('user_id', $user->id));
    }

    public function scopeUnreadForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('participants', function (Builder $query) use ($user) {
            $query
                ->where('user_id', $user->id)
                ->where(function (Builder $query) {
                    $query
                        ->whereNull('last_read_at')
                        ->orWhereColumn('last_read_at', '<', 'conversations.last_message_at');
                });
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function matter()
    {
        return $this->belongsTo(Matter::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['last_read_at', 'muted_at'])
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('sent_at');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany('sent_at');
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCE_TYPES[$this->audience_type] ?? str($this->audience_type)->headline()->toString();
    }
}
