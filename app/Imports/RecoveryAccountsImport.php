<?php

namespace App\Imports;

use App\Models\RecoveryAccount;
use App\Models\RecoveryImportBatch;
use App\Models\User;
use App\Support\Recoveries\RecoveryPortfolioMapper;
use App\Support\Recoveries\RecoveryPortfolioProfiles;
use App\Support\Recoveries\RecoveryReferenceData;
use App\Support\Recoveries\RecoveryAccountIdentity;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RecoveryAccountsImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    public int $imported = 0;

    public int $skipped = 0;

    public float $principal = 0;

    public float $outstanding = 0;

    public int $assigned = 0;

    public int $duplicates = 0;

    public int $updated = 0;

    public array $previewRows = [];

    public array $errors = [];

    private array $collectorOfficerMap;

    private array $seenIdentities = [];

    public function __construct(
        private RecoveryImportBatch $batch,
        private User $user,
        private ?int $assignedTo = null,
        private bool $matchCollector = false,
        private string|int $sheet = 0,
        private array $columnMap = [],
        private bool $previewOnly = false,
        private string $duplicateAction = 'skip',
    ) {
        $this->collectorOfficerMap = User::whereHas('roles', fn ($query) => $query->where('name', 'Recovery Officer'))
            ->get(['id', 'name', 'branch_id'])
            ->mapWithKeys(fn (User $officer) => [strtolower(trim($officer->name)) => $officer])
            ->all();
    }

    public function sheets(): array
    {
        return [$this->sheet => $this];
    }

    public function collection(Collection $rows): void
    {
        $headings = $rows->first()?->keys()->all() ?? [];
        $normalizedHeadings = array_map(RecoveryPortfolioProfiles::key(...), $headings);
        $isStandard = in_array('debtor_name', $normalizedHeadings, true)
            && in_array('outstanding_amount', $normalizedHeadings, true);
        $mappedHeadings = array_map(RecoveryPortfolioProfiles::key(...), array_values($this->columnMap));
        $columnMap = $isStandard && array_diff($mappedHeadings, $normalizedHeadings)
            ? []
            : $this->columnMap;

        if (! RecoveryPortfolioProfiles::matchesHeaders((string) $this->batch->portfolio_type, $headings, $columnMap)) {
            throw ValidationException::withMessages([
                'file' => 'The selected worksheet headings do not match this client template or the standard recovery CSV.',
            ]);
        }

        $mapper = new RecoveryPortfolioMapper;

        foreach ($rows as $index => $row) {
            $data = $mapper->map($row->toArray(), (string) $this->batch->portfolio_type, $index + 2, $columnMap);

            if (! $data) {
                $this->skipped++;

                continue;
            }

            if ((float) $data['outstanding_amount'] <= 0) {
                $this->skipped++;
                if (count($this->errors) < 20) {
                    $this->errors[] = 'Row '.($index + 2).': Outstanding amount must be greater than zero.';
                }

                continue;
            }

            $data['recovery_client_id'] = $this->batch->recovery_client_id;
            $data['recovery_import_batch_id'] = $this->batch->id;
            $data['branch_id'] = $this->user->branch_id;
            $data['sms_opt_in'] = false;
            try {
                $data = RecoveryReferenceData::validate($data, $this->batch->client, sourceImport: true);
            } catch (ValidationException $exception) {
                $this->skipped++;
                if (count($this->errors) < 20) {
                    $this->errors[] = 'Row '.($index + 2).': '.collect($exception->errors())->flatten()->join(' ');
                }

                continue;
            }

            $identity = RecoveryAccountIdentity::fromData($data);
            $data['identity_key'] = $identity['key'];
            $data['identity_source'] = $identity['source'];
            $data['identity_portfolio'] = (string) $this->batch->portfolio_type;
            $alreadyInFile = isset($this->seenIdentities[$identity['key']]);
            $this->seenIdentities[$identity['key']] = true;
            $existing = $alreadyInFile ? null : RecoveryAccountIdentity::existing(
                $this->batch->recovery_client_id, (string) $this->batch->portfolio_type, $data
            );

            if ($alreadyInFile || $existing) {
                $this->duplicates++;
                $action = $alreadyInFile || $existing?->trashed() || in_array($identity['source'], ['name', 'name_phone'], true)
                    ? 'skip'
                    : $this->duplicateAction;
                $decision = $this->previewOnly && $existing && ! $existing->trashed()
                    && ! in_array($identity['source'], ['name', 'name_phone'], true)
                    ? 'Existing account - skip or update details'
                    : ($action === 'update' ? 'Update details' : 'Duplicate - skip');
                $this->addPreviewRow($data, $decision, $existing?->id);

                if (! $this->previewOnly && $action === 'update' && $existing) {
                    $existing->update(collect($data)->only([
                        'identity_key', 'identity_source', 'identity_portfolio', 'debtor_name', 'phone', 'alternate_phone',
                        'whatsapp_number', 'email', 'employer', 'branch_name', 'region',
                        'collector_name', 'operative_account', 'days_past_due', 'bucket',
                        'collateral_held', 'cause_of_default', 'raw_payload',
                    ])->all());
                    $this->updated++;
                } else {
                    $this->skipped++;
                }

                continue;
            }

            $this->addPreviewRow($data, 'New account');
            if ($this->previewOnly) {
                $this->imported++;
                $this->principal += (float) $data['principal_amount'];
                $this->outstanding += (float) $data['outstanding_amount'];

                continue;
            }

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

            $data['opening_recovered_amount'] = $data['amount_recovered'] ?? 0;

            RecoveryAccount::create($data);

            $this->imported++;
            $this->principal += (float) $data['principal_amount'];
            $this->outstanding += (float) $data['outstanding_amount'];
        }

        $this->batch->update($this->previewOnly ? [
            'total_rows' => $rows->count(),
            'preview_rows' => $this->previewRows,
            'preview_counts' => [
                'new' => $this->imported,
                'duplicates' => $this->duplicates,
                'invalid' => $this->skipped - $this->duplicates,
            ],
        ] : [
            'total_rows' => $rows->count(),
            'imported_rows' => $this->imported,
            'skipped_rows' => $this->skipped,
            'total_principal' => $this->principal,
            'total_outstanding' => $this->outstanding,
            'assigned_count' => $this->assigned,
            'status' => $this->assigned > 0 ? 'assigned' : 'imported',
        ]);
    }

    private function addPreviewRow(array $data, string $decision, ?int $existingId = null): void
    {
        if (count($this->previewRows) >= 100) {
            return;
        }

        $this->previewRows[] = [
            'row' => $data['import_row_number'],
            'debtor' => $data['debtor_name'],
            'account' => $data['account_number'] ?: $data['customer_number'],
            'outstanding' => $data['outstanding_amount'],
            'decision' => $decision,
            'existing_id' => $existingId,
        ];
    }
}
