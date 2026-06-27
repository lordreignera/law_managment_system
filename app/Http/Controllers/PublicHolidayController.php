<?php

namespace App\Http\Controllers;

use App\Models\PublicHoliday;
use Illuminate\Http\Request;

class PublicHolidayController extends Controller
{
    public function index(Request $request)
    {
        $holidays = PublicHoliday::query()
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('date')
            ->paginate(20)
            ->withQueryString();

        return view('modules.holidays.index', [
            'holidays' => $holidays,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return view('modules.holidays.create');
    }

    public function store(Request $request)
    {
        PublicHoliday::create($this->validateHoliday($request));

        return redirect()
            ->route('holidays.index')
            ->with('status', 'Public holiday added.');
    }

    public function edit(PublicHoliday $holiday)
    {
        return view('modules.holidays.edit', ['holiday' => $holiday]);
    }

    public function update(Request $request, PublicHoliday $holiday)
    {
        $holiday->update($this->validateHoliday($request));

        return redirect()
            ->route('holidays.index')
            ->with('status', 'Public holiday updated.');
    }

    public function destroy(PublicHoliday $holiday)
    {
        $holiday->delete();

        return back()->with('status', 'Public holiday removed.');
    }

    private function validateHoliday(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'date' => ['required', 'date'],
            'is_recurring' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['is_recurring'] = $request->boolean('is_recurring');

        return $data;
    }
}
