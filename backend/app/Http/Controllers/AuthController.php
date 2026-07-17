<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email'=>['required','email'],'password'=>['required','string'],'remember'=>['sometimes','boolean']]);
        $remember = (bool) ($credentials['remember'] ?? false); unset($credentials['remember']);
        if (!Auth::attempt($credentials + ['status'=>'ACTIVE'], $remember)) throw ValidationException::withMessages(['email'=>'The provided credentials are invalid.']);
        if ($request->user()->tenant_id && $request->user()->tenant?->status !== 'ACTIVE') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email'=>'This tenant is suspended. Contact the platform administrator.']);
        }
        $request->session()->regenerate();
        return response()->json(['user'=>$request->user()]);
    }
    public function logout(Request $request): JsonResponse { Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return response()->json(['message'=>'Logged out']); }
}
