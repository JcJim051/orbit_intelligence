<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceTokenController extends Controller
{
    public function index(Request $request)
    {
        return view('tokens.index', ['tokens' => $request->user()->tokens()->latest()->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'purpose' => ['required', Rule::in(['meetings', 'qgis'])],
        ]);
        abort_if($data['purpose'] === 'qgis' && ! $request->user()->isAdmin(), 403);

        $abilities = match ($data['purpose']) {
            'qgis' => ['qgis:read'],
            default => ['meetings:upload'],
        };
        $token = $request->user()->createToken($data['name'], $abilities);

        return back()->with('new_token', $token->plainTextToken);
    }

    public function destroy(Request $request, int $token)
    {
        $request->user()->tokens()->whereKey($token)->delete();

        return back()->with('status', 'Token revocado.');
    }
}
