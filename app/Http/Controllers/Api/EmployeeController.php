<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = User::whereHas('roles', function ($query) {
            $query->where('name', 'Employee');
        })
        ->select([
            'id',
            'name',
            'email',
            'created_at',
        ])
        ->orderBy('name')
        ->get();

        return response()->json([
            'message' => 'Employees retrieved successfully',
            'data' => $employees,
        ]);
    }
}