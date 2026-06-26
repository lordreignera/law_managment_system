<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientContact extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_primary' => 'boolean',
        'date_of_birth' => 'date',
    ];

    public function getDisplayNameAttribute(): string
    {
        return $this->name
            ?: trim(collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' '))
            ?: 'Unnamed Contact';
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function relationshipType()
    {
        return $this->belongsTo(RelationshipType::class);
    }

    public function salutation()
    {
        return $this->belongsTo(Salutation::class);
    }

    public function position()
    {
        return $this->belongsTo(ContactPosition::class, 'position_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
