<?php

namespace App\Http\Controllers;

use App\Exports\RecoveryReportExport;
use App\Models\AccountClass;
use App\Models\ChartAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ChartAccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = $this->filteredAccountQuery($request)
            ->orderBy('account_number')
            ->paginate(20)
            ->withQueryString();

        return view('modules.finance.chart-accounts.index', [
            'accounts' => $accounts,
            'classes' => AccountClass::orderBy('sort_order')->get(),
            'accountTypes' => ChartAccount::ACCOUNT_TYPES,
            'filters' => $request->only(['search', 'account_class_id', 'account_type', 'postable', 'status']),
            'summary' => [
                'Total Accounts' => ChartAccount::count(),
                'Postable Accounts' => ChartAccount::where('is_postable', true)->count(),
                'Bank/Cash Accounts' => ChartAccount::where(fn ($query) => $query->where('is_bank_account', true)->orWhere('is_cash_account', true))->count(),
                'Inactive Accounts' => ChartAccount::where('is_active', false)->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $classId = $request->integer('account_class_id') ?: AccountClass::orderBy('sort_order')->value('id');
        $parentId = $request->integer('parent_id') ?: null;

        return view('modules.finance.chart-accounts.create', $this->formData([
            'account' => new ChartAccount([
                'account_class_id' => $classId,
                'parent_id' => $parentId,
                'account_number' => ChartAccount::nextNumber($classId, $parentId),
                'normal_balance' => AccountClass::find($classId)?->normal_balance ?: 'debit',
                'account_type' => $this->typeFromClass($classId),
                'is_postable' => true,
                'is_active' => true,
            ]),
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $account = ChartAccount::create($data);

        return redirect()
            ->route('finance.chart-accounts.show', $account)
            ->with('status', 'Chart account created.');
    }

    public function show(ChartAccount $chartAccount)
    {
        return view('modules.finance.chart-accounts.show', [
            'account' => $chartAccount->load(['accountClass', 'parent', 'children.accountClass', 'mappings']),
        ]);
    }

    public function edit(ChartAccount $chartAccount)
    {
        return view('modules.finance.chart-accounts.edit', $this->formData([
            'account' => $chartAccount,
        ]));
    }

    public function update(Request $request, ChartAccount $chartAccount)
    {
        $data = $this->validatedData($request, $chartAccount);

        $chartAccount->update($data);

        return redirect()
            ->route('finance.chart-accounts.show', $chartAccount)
            ->with('status', 'Chart account updated.');
    }

    public function destroy(ChartAccount $chartAccount)
    {
        if ($chartAccount->children()->exists()) {
            return back()->withErrors(['account' => 'This account has child accounts. Deactivate it or move/delete the children first.']);
        }

        if ($chartAccount->mappings()->exists()) {
            return back()->withErrors(['account' => 'This account is used in finance mappings and cannot be deleted.']);
        }

        $chartAccount->delete();

        return redirect()
            ->route('finance.chart-accounts.index')
            ->with('status', 'Chart account deleted.');
    }

    public function export(Request $request)
    {
        $rows = $this->filteredAccountQuery($request)
            ->orderBy('account_number')
            ->get()
            ->map(fn (ChartAccount $account) => [
                $account->account_number,
                $account->name,
                $account->accountClass?->name,
                $account->parent?->fullName(),
                $account->typeLabel(),
                $account->normalBalanceLabel(),
                $account->is_postable ? 'Yes' : 'No',
                $account->is_bank_account ? 'Yes' : 'No',
                $account->is_cash_account ? 'Yes' : 'No',
                $account->currency_code,
                $account->is_active ? 'Active' : 'Inactive',
            ])
            ->all();

        return Excel::download(
            new RecoveryReportExport([
                'Account Number',
                'Name',
                'Class',
                'Parent',
                'Type',
                'Normal Balance',
                'Postable',
                'Bank',
                'Cash',
                'Currency',
                'Status',
            ], $rows, 'Chart of Accounts'),
            'chart-of-accounts-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function formData(array $overrides = []): array
    {
        return array_merge([
            'classes' => AccountClass::where('is_active', true)->orderBy('sort_order')->get(),
            'parents' => ChartAccount::where('is_active', true)->orderBy('account_number')->get(['id', 'account_class_id', 'account_number', 'name']),
            'accountTypes' => ChartAccount::ACCOUNT_TYPES,
            'normalBalances' => ChartAccount::NORMAL_BALANCES,
        ], $overrides);
    }

    private function filteredAccountQuery(Request $request): Builder
    {
        return ChartAccount::query()
            ->with(['accountClass', 'parent'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('account_number', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('accountClass', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('parent', fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('account_number', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('account_class_id'), fn ($query) => $query->where('account_class_id', $request->integer('account_class_id')))
            ->when($request->filled('account_type'), fn ($query) => $query->where('account_type', $request->string('account_type')->toString()))
            ->when($request->filled('postable'), fn ($query) => $query->where('is_postable', $request->string('postable')->toString() === 'yes'))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'));
    }

    private function validatedData(Request $request, ?ChartAccount $account = null): array
    {
        $data = $request->validate([
            'account_class_id' => ['required', 'exists:account_classes,id'],
            'parent_id' => ['nullable', 'exists:chart_accounts,id'],
            'account_number' => ['nullable', 'string', 'max:40', Rule::unique('chart_accounts', 'account_number')->ignore($account?->id)],
            'name' => ['required', 'string', 'max:191'],
            'account_type' => ['required', Rule::in(array_keys(ChartAccount::ACCOUNT_TYPES))],
            'normal_balance' => ['required', Rule::in(array_keys(ChartAccount::NORMAL_BALANCES))],
            'description' => ['nullable', 'string', 'max:2000'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_postable' => ['nullable', 'boolean'],
            'is_bank_account' => ['nullable', 'boolean'],
            'is_cash_account' => ['nullable', 'boolean'],
            'is_client_funds_account' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['parent_id'])) {
            $parent = ChartAccount::findOrFail($data['parent_id']);
            if ((int) $parent->account_class_id !== (int) $data['account_class_id']) {
                throw ValidationException::withMessages(['parent_id' => 'Parent account must be in the same class.']);
            }

            if ($account && (int) $parent->id === (int) $account->id) {
                throw ValidationException::withMessages(['parent_id' => 'An account cannot be its own parent.']);
            }

            $data['level'] = $parent->level + 1;
        } else {
            $data['level'] = 1;
        }

        $data['account_number'] = $data['account_number'] ?: ChartAccount::nextNumber((int) $data['account_class_id'], $data['parent_id'] ?? null);
        $data['sort_order'] = $data['sort_order'] ?? (int) preg_replace('/\D+/', '', $data['account_number']);
        $data['is_postable'] = $request->boolean('is_postable');
        $data['is_bank_account'] = $request->boolean('is_bank_account');
        $data['is_cash_account'] = $request->boolean('is_cash_account');
        $data['is_client_funds_account'] = $request->boolean('is_client_funds_account');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function typeFromClass(?int $classId): string
    {
        $class = AccountClass::find($classId);

        return match ($class?->name) {
            'Liabilities' => 'liability',
            'Equity' => 'equity',
            'Income' => 'income',
            'Expenses' => 'expense',
            default => 'asset',
        };
    }
}
