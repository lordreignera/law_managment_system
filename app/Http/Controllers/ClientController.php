<?php

namespace App\Http\Controllers;

use App\Models\Client;

class ClientController extends Controller
{
    public function index()
    {
        return view('modules.clients.index', [
            'clients' => Client::latest()->paginate(15),
        ]);
    }
}
