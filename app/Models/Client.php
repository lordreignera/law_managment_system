<?php

namespace App\Models;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use BranchScoped;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_prospect' => 'boolean',
        'date_of_birth' => 'date',
    ];

    public function getDisplayNameAttribute(): string
    {
        return $this->name
            ?: trim(collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' '))
            ?: ($this->organization_name ?: 'Unnamed Client');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }

    public function nextOfKin()
    {
        return $this->hasOne(ClientContact::class)->where('contact_type', 'next_of_kin');
    }

    public function matters()
    {
        return $this->hasMany(Matter::class);
    }

    public function engagements()
    {
        return $this->hasMany(Engagement::class);
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

    public function clientInCharge()
    {
        return $this->belongsTo(User::class, 'client_in_charge_id');
    }
}
