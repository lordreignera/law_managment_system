<?php

namespace App\Imports;

use App\Models\RecoveryAccount;
use App\Models\RecoveryImportBatch;
use App\Models\User;
use App\Support\Recoveries\RecoveryPortfolioMapper;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RecoveryAccountsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;
    public int $skipped = 0;
    public float $principal = 0;
    public float $outstanding = 0;
    public int $assigned = 0;

    private array $collectorOfficerMap;

    public function __construct(
        private RecoveryImportBatch $batch,
        private User $user,
        private ?int $assignedTo = null,
        private bool $matchCollector = false,
    ) {
        $this->collectorOfficerMap = User::role('Recovery Officer')
            ->get(['id', 'name', 'branch_id'])
            ->mapWithKeys(fn (User $officer) => [strtolower(trim($officer->name)) => $officer])
            ->all();
    }

    public function collection(Collection $rows): void
    {
        $mapper = new RecoveryPortfolioMapper;

        foreach ($rows as $index => $row) {
            $data = $mapper->map($row->toArray(), (string) $this->batch->portfolio_type, $index + 2);

            if (! $data) {
                $this->skipped++;

                continue;
            }

            $data['recovery_client_id'] = $this->batch->recovery_client_id;
            $data['recovery_import_batch_id'] = $this->batch->id;
            $data['branch_id'] = $this->user->branch_id;

            $assignee = $this->assignedTo ? User::find($this->assignedTo) : null;
            if (! $assignee && $this->matchCollector && $data['collector_name']) {
                $assignee = $this->collectorOfficerMap[strtolower(trim($data['collector_name']))] ?? null;
            }

            if ($assignee) {
                $data['assigned_to'] = $assignee->id;
                $data['assigned_by'] = $this->user->id;
                $data['assigned_at'] = now();
                $data['branch_id'] = $assignee->branch_id ?: $data['branch_id'];
                $this->assigned++;
            }

            RecoveryAccount::create($data);

            $this->imported++;
            $this->principal += (float) $data['principal_amount'];
            $this->outstanding += (float) $data['outstanding_amount'];
        }

        $this->batch->update([
            'total_rows' => $rows->count(),
            'imported_rows' => $this->imported,
            'skipped_rows' => $this->skipped,
            'total_principal' => $this->principal,
            'total_outstanding' => $this->outstanding,
            'assigned_count' => $this->assigned,
            'status' => $this->assigned > 0 ? 'assigned' : 'imported',
        ]);
    }
}
