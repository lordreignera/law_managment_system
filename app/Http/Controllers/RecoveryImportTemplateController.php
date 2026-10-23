<?php

namespace App\Http\Controllers;

use App\Models\RecoveryClient;
use App\Models\RecoveryImportTemplate;
use App\Support\Recoveries\RecoveryPortfolioProfiles;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecoveryImportTemplateController extends Controller
{
    public function store(Request $request)
    {
        $client = RecoveryClient::where('is_active', true)->findOrFail($request->integer('recovery_client_id'));
        $fields = array_keys(RecoveryPortfolioProfiles::TEMPLATE_FIELDS);
        $rules = [
            'portfolio_type' => ['required', Rule::in(array_keys($client->portfolioOptions()))],
            'sheet_name' => ['nullable', 'string', 'max:31'],
            'column_map' => ['required', 'array:'.implode(',', $fields)],
        ];
        foreach ($fields as $field) {
            $rules['column_map.'.$field] = [in_array($field, ['debtor_name', 'outstanding_amount'], true) ? 'required' : 'nullable', 'string', 'max:191'];
        }
        $data = $request->validate($rules);
        $columnMap = collect($data['column_map'])
            ->map(fn ($heading) => trim((string) $heading))
            ->filter(fn ($heading) => $heading !== '')
            ->all();
        $normalized = array_map(RecoveryPortfolioProfiles::key(...), array_values($columnMap));
        if (in_array('', $normalized, true) || count($normalized) !== count(array_unique($normalized))) {
            throw ValidationException::withMessages([
                'column_map' => 'Use a distinct, readable source column heading for each mapped field.',
            ]);
        }

        $template = RecoveryImportTemplate::firstOrNew([
            'recovery_client_id' => $client->id,
            'portfolio_type' => $data['portfolio_type'],
        ]);
        if (! $template->exists) {
            $template->created_by = $request->user()->id;
        }
        $template->sheet_name = trim((string) ($data['sheet_name'] ?? '')) ?: null;
        $template->column_map = $columnMap;
        $template->save();

        return redirect()->route('recoveries.import', [
            'recovery_client_id' => $client->id,
            'portfolio_type' => $template->portfolio_type,
        ])->with('status', 'Client import template saved. Download it or import a matching file.');
    }

    public function download(RecoveryImportTemplate $template): StreamedResponse
    {
        abort_unless($template->client?->is_active && array_key_exists($template->portfolio_type, $template->client->portfolioOptions()), 404);

        $headings = collect(RecoveryPortfolioProfiles::TEMPLATE_FIELDS)
            ->keys()
            ->filter(fn ($field) => isset($template->column_map[$field]))
            ->map(fn ($field) => $template->column_map[$field])
            ->all();
        $filename = Str::slug($template->client->name.'-'.$template->portfolio_type).'-import-template.csv';

        return response()->streamDownload(function () use ($headings): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headings);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(RecoveryImportTemplate $template)
    {
        $template->delete();

        return redirect()->route('recoveries.import', ['recovery_client_id' => $template->recovery_client_id])
            ->with('status', 'Client import template removed. The standard CSV remains available.');
    }
}
