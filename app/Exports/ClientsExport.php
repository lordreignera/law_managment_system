<?php

namespace App\Exports;

use App\Models\Client;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ClientsExport implements FromQuery, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private User $user)
    {
    }

    public function query()
    {
        return Client::query()
            ->forBranchOf($this->user)
            ->with('branch')
            ->latest();
    }

    public function headings(): array
    {
        return [
            'Client No',
            'Type',
            'Name',
            'Organization',
            'First Name',
            'Last Name',
            'Gender',
            'NIN/Passport',
            'Email',
            'Phone',
            'Address',
            'Occupation',
            'TIN',
            'Status',
            'Branch',
            'Created On',
        ];
    }

    /**
     * @param  Client  $client
     */
    public function map($client): array
    {
        return [
            $client->client_no,
            $client->client_type,
            $client->display_name ?: $client->name,
            $client->organization_name,
            $client->first_name,
            $client->last_name,
            $client->gender,
            $client->nin_passport_no,
            $client->email,
            $client->phone,
            $client->address,
            $client->occupation,
            $client->tin,
            $client->status,
            $client->branch?->name,
            optional($client->created_at)->format('Y-m-d'),
        ];
    }

    public function title(): string
    {
        return 'Clients';
    }
}
