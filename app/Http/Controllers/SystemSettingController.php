<?php

namespace App\Http\Controllers;

use App\Models\CurrencyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemSettingController extends Controller
{
    public function overview()
    {
        $settings = collect(config('system_settings'))->map(function ($setting, $slug) {
            $setting['slug'] = $slug;
            $setting['count'] = $setting['model']::count();

            return $setting;
        });

        return view('modules.system-settings.overview', [
            'settings' => $settings,
        ]);
    }

    public function index(string $setting)
    {
        $definition = $this->definition($setting);
        $records = $definition['model']::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15);

        return view('modules.system-settings.index', [
            'setting' => $setting,
            'definition' => $definition,
            'records' => $records,
        ]);
    }

    public function create(string $setting)
    {
        $definition = $this->definition($setting);

        return view('modules.system-settings.create', [
            'setting' => $setting,
            'definition' => $definition,
            'record' => new $definition['model'],
            'currencyTypes' => CurrencyType::orderBy('name')->get(),
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
            'currencyTypes' => CurrencyType::orderBy('name')->get(),
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
        $table = (new $definition['model'])->getTable();
        $ignore = $record?->id;
        $extraFields = $definition['extra_fields'] ?? [];

        $rules = [
            'name' => ['required', 'string', 'max:191', Rule::unique($table, 'name')->ignore($ignore)],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if (in_array('court_level', $extraFields, true)) {
            $rules['court_level'] = ['nullable', 'string', 'max:120'];
        }

        if (in_array('station', $extraFields, true)) {
            $rules['station'] = ['nullable', 'string', 'max:120'];
        }

        if (in_array('symbol', $extraFields, true)) {
            $rules['symbol'] = ['nullable', 'string', 'max:10'];
        }

        if (in_array('header_text', $extraFields, true)) {
            $rules['header_text'] = ['nullable', 'string', 'max:2000'];
        }

        if (in_array('footer_text', $extraFields, true)) {
            $rules['footer_text'] = ['nullable', 'string', 'max:2000'];
        }

        if (in_array('is_default', $extraFields, true)) {
            $rules['is_default'] = ['nullable', 'boolean'];
        }

        if (in_array('hourly_rate', $extraFields, true)) {
            $rules['hourly_rate'] = ['required', 'numeric', 'min:0'];
        }

        if (in_array('currency_type_id', $extraFields, true)) {
            $rules['currency_type_id'] = ['nullable', 'exists:currency_types,id'];
        }

        $data = $request->validate($rules);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        if (in_array('is_default', $extraFields, true)) {
            $data['is_default'] = $request->boolean('is_default');
        }

        return $data;
    }

    private function syncDefaultLetterhead(array $definition, Model $record, array $data): void
    {
        if (($definition['model'] !== \App\Models\Letterhead::class) || empty($data['is_default'])) {
            return;
        }

        $definition['model']::whereKeyNot($record->id)->update(['is_default' => false]);
    }
}
