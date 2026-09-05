<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserController extends Controller
{

    public function employees(Request $request)
    {
        abort_unless($request->user()->hasRole('Admin'), 403);

        $employees = User::role('Employee')
            ->select([
                'id',
                'name',
                'email',
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }
}
