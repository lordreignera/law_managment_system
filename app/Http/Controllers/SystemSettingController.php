<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\CentenaryRegion;
use App\Models\Court;
use App\Models\CurrencyType;
use App\Models\Letterhead;
use App\Support\MasterDataValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function overview(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $searchTerm = mb_strtolower($search);
        $settings = collect(config('system_settings'))->map(function ($setting, $slug) {
            $setting['slug'] = $slug;
            $setting['count'] = $setting['model']::count();

            return $setting;
        })->filter(function (array $setting) use ($searchTerm) {
            if ($searchTerm === '') {
                return true;
            }

            return str_contains(mb_strtolower($setting['title'].' '.$setting['description']), $searchTerm);
        })->values();

        return view('modules.system-settings.overview', [
            'settings' => $settings,
            'search' => $search,
        ]);
    }

    public function index(Request $request, string $setting)
    {
        $definition = $this->definition($setting);
        $extraFields = $definition['extra_fields'] ?? [];
        $records = $definition['model']::query()
            ->when($request->filled('search'), function ($query) use ($request, $extraFields) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search, $extraFields) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");

                    if (in_array('office_location', $extraFields, true)) {
                        $query->orWhere('office_location', 'like', "%{$search}%");
                    }

                    if (in_array('districts_covered', $extraFields, true)) {
                        $query->orWhere('districts_covered', 'like', "%{$search}%");
                    }

                    if (in_array('bank_id', $extraFields, true)) {
                        $query->orWhereHas('bank', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                    }

                    if (in_array('contact_person', $extraFields, true)) {
                        $query->orWhere('contact_person', 'like', "%{$search}%");
                    }
                    if (in_array('iso_code', $extraFields, true)) {
                        $query->orWhere('iso_code', 'like', "%{$search}%");
                    }
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('is_active', $request->string('status')->toString() === 'active');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('modules.system-settings.index', [
            'setting' => $setting,
            'definition' => $definition,
            'records' => $records,
            'newRecord' => $this->newRecord($definition),
            'banks' => Bank::orderBy('name')->get(),
            'currencyTypes' => CurrencyType::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
            'courts' => Court::orderBy('name')->get(),
            'centenaryRegions' => CentenaryRegion::orderBy('name')->get(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(string $setting)
    {
        $definition = $this->definition($setting);

        return view('modules.system-settings.create', [
            'setting' => $setting,
            'definition' => $definition,
            'record' => $this->newRecord($definition),
            'banks' => Bank::orderBy('name')->get(),
            'currencyTypes' => CurrencyType::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
            'courts' => Court::orderBy('name')->get(),
            'centenaryRegions' => CentenaryRegion::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $setting)
    {
        $definition = $this->definition($setting);
        $data = $this->validatedData($request, $definition);

        $record = $definition['model']::create($data);
        $this->syncDefaultLetterhead($definition, $record, $data);

        return redirect()
            ->route('settings.system.index', $setting)
            ->with('status', $definition['singular'].' created.');
    }

    public function edit(string $setting, int $record)
    {
        $definition = $this->definition($setting);
        $record = $definition['model']::findOrFail($record);

        return view('modules.system-settings.edit', [
            'setting' => $setting,
            'definition' => $definition,
            'record' => $record,
            'banks' => Bank::orderBy('name')->get(),
            'currencyTypes' => CurrencyType::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
            'courts' => Court::orderBy('name')->get(),
            'centenaryRegions' => CentenaryRegion::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, string $setting, int $record)
    {
        $definition = $this->definition($setting);
        $record = $definition['model']::findOrFail($record);
        $data = $this->validatedData($request, $definition, $record);

        $record->update($data);
        $this->syncDefaultLetterhead($definition, $record, $data);

        return redirect()
            ->route('settings.system.index', $setting)
            ->with('status', $definition['singular'].' updated.');
    }

    public function destroy(string $setting, int $record)
    {
        $definition = $this->definition($setting);
        $definition['model']::findOrFail($record)->delete();

        return back()->with('status', $definition['singular'].' deleted.');
    }

    private function definition(string $setting): array
    {
        abort_unless(array_key_exists($setting, config('system_settings')), 404);

        return config("system_settings.$setting") + ['extra_fields' => []];
    }

    private function validatedData(Request $request, array $definition, ?Model $record = null): array
    {
        return app(MasterDataValidator::class)->validate($request->all(), $definition, $record);
    }

    private function newRecord(array $definition): Model
    {
        $model = new $definition['model'];

        if (method_exists($definition['model'], 'nextCompanyCode')) {
            $model->code = $definition['model']::nextCompanyCode();
        }

        return $model;
    }

    private function syncDefaultLetterhead(array $definition, Model $record, array $data): void
    {
        if (($definition['model'] !== Letterhead::class) || empty($data['is_default'])) {
            return;
        }

        $definition['model']::whereKeyNot($record->id)->update(['is_default' => false]);
    }
}
