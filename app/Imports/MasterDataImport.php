<?php

namespace App\Imports;

use App\Models\User;
use App\Support\MasterDataImports;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MasterDataImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public function __construct(private string $setting, private User $actor) {}

    public function collection(Collection $rows): void
    {
        $this->imported += app(MasterDataImports::class)->import($this->setting, $rows, $this->actor);
    }
}
