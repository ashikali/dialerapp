<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'login'=>$request->input('login',$request->input('email')),
        ]);
        $credentials=$request->validate([
            'login'=>['required','string','max:255'],
            'password'=>['required','string'],
            'remember'=>['sometimes','boolean'],
        ]);
        $login=strtolower(trim($credentials['login']));
        $remember=(bool)($credentials['remember']??false);
        $tenant=Tenant::query()->whereRaw('LOWER(sip_domain) = ?', [strtolower($request->getHost())])->first();

        if($tenant){
            $username=$this->tenantUsername($login,$tenant->sip_domain);
            $user=User::query()
                ->where('tenant_id',$tenant->id)
                ->where('status','ACTIVE')
                ->where(function($query) use($login,$username): void {
                    $query->whereRaw('LOWER(username) = ?',[$username])
                        ->orWhereRaw('LOWER(email) = ?',[$login]);
                })
                ->first();
        }else{
            $user=User::query()
                ->whereNull('tenant_id')
                ->where('status','ACTIVE')
                ->whereRaw('LOWER(email) = ?',[$login])
                ->first();
        }

        if(!$user || !Hash::check($credentials['password'],$user->password)){
            throw ValidationException::withMessages(['login'=>'The provided credentials are invalid.']);
        }
        if($tenant && $tenant->status!=='ACTIVE'){
            throw ValidationException::withMessages(['login'=>'This tenant is suspended. Contact the platform administrator.']);
        }

        Auth::guard('web')->login($user,$remember);
        $request->session()->regenerate();
        return response()->json(['user'=>$request->user()]);
    }

    private function tenantUsername(string $login,string $sipDomain): string
    {
        if(!str_contains($login,'@'))return $login;
        [$local,$domain]=array_pad(explode('@',$login,2),2,'');
        return strcasecmp($domain,$sipDomain)===0?$local:$login;
    }

    public function logout(Request $request): JsonResponse { Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return response()->json(['message'=>'Logged out']); }
}
