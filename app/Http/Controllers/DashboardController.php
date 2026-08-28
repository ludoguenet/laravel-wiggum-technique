<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $teams = $request->user()->teams()->with('owner')->withCount('users')->get();

        return view('dashboard', [
            'teams' => $teams,
        ]);
    }
}
