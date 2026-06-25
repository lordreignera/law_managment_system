<?php

namespace App\Http\Controllers;

use App\Models\Matter;

class MatterController extends Controller
{
    public function index()
    {
        return view('modules.matters.index', [
            'matters' => Matter::with(['client', 'practiceArea'])->latest()->paginate(15),
        ]);
    }
}
