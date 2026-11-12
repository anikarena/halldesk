<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitorController extends Controller
{
    public function index(Request $request): View
    {
        return view('student.visitors', [
            'visitors' => $request->user()->visitors()->latest('check_in_at')->paginate(15),
        ]);
    }
}
