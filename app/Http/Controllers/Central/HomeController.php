<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $plans = Plan::where('is_active', true)->with('features')->get();

        return view('central.landing', compact('plans'));
    }
}
