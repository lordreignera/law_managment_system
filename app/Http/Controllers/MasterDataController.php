<?php

namespace App\Http\Controllers;

use App\Imports\MasterDataImport;
use App\Support\MasterDataImports;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MasterDataController extends Controller
{
    public function index(Request $request, MasterDataImports $imports)
    {
        return view('modules.system-settings.import', ['definitions' => array_filter($imports->definitions(), fn ($key) => $request->user()->can(match ($key) {
            'branches' => 'branches.store', 'users' => 'access.users.store', 'roles' => 'access.roles.store', default => 'settings.system.store',
        }), ARRAY_FILTER_USE_KEY)]);
    }

    public function template(Request $request, string $setting, MasterDataImports $imports)
    {
        $imports->authorize($setting, $request->user());

        return response()->streamDownload(function () use ($imports, $setting) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $imports->headings($setting));
            fclose($handle);
        }, 'master-data-'.$setting.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request, MasterDataImports $imports)
    {
        $data = $request->validate(['setting' => ['required', 'string'], 'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:5120']]);
        $imports->authorize($data['setting'], $request->user());
        $import = new MasterDataImport($data['setting'], $request->user());
        Excel::import($import, $request->file('file'));

        return back()->with('status', 'Imported '.$import->imported.' master-data records.');
    }
}
