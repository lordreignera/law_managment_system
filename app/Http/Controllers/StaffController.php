<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $staff = User::with(['branch', 'department', 'roles'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('branch', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('department', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('roles', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('modules.staff.index', [
            'staff' => $staff,
            'filters' => $request->only(['search']),
        ]);
    }
}
