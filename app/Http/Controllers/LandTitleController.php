<?php

namespace App\Http\Controllers;

use App\Models\LandTitle;

class LandTitleController extends Controller
{
    public function index()
    {
        return view('modules.land-titles.index', [
            'titles' => LandTitle::latest()->paginate(15),
        ]);
    }
}
