<?php

namespace App\Http\Controllers;

use App\Models\LandTitle;
use Illuminate\Http\Request;

class LandTitleController extends Controller
{
    public function index(Request $request)
    {
        $titles = LandTitle::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('borrower_name', 'like', "%{$search}%")
                        ->orWhere('instruction_type', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.land-titles.index', [
            'titles' => $titles,
            'filters' => $request->only(['search', 'status']),
        ]);
    }
}
