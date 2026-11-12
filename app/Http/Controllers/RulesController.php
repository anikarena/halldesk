<?php

namespace App\Http\Controllers;

use App\Support\HallRules;
use Illuminate\View\View;

class RulesController extends Controller
{
    public function __invoke(HallRules $rules): View
    {
        return view('rules', ['rules' => $rules->parse()]);
    }
}
