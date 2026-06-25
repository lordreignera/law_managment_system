<?php

namespace App\Http\Controllers;

use App\Models\User;

class StaffController extends Controller
{
    public function index()
    {
        return view('modules.staff.index', [
            'staff' => User::with(['branch', 'department', 'roles'])->latest()->paginate(15),
        ]);
    }
}
