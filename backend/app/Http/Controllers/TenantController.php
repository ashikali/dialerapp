<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class TenantController extends Controller
{
    public function index(): JsonResponse
    {
        $tenants = Tenant::query()
            ->withCount('users')
            ->with(['users' => fn ($query) => $query
                ->where('role', UserRole::TENANT_ADMIN)
                ->select(['id','tenant_id','name','username','email','status'])])
            ->latest()
            ->paginate(25);

        return response()->json($tenants);
    }

    public function store(Request $request): JsonResponse
    {
        $adminInput=(array)$request->input('admin',[]);
        $request->merge(['admin'=>[
            ...$adminInput,
            'username'=>strtolower((string)($adminInput['username']??'')),
        ]]);
        $data=$request->validate([
            'name'=>['required','string','max:150'],
            'code'=>['required','alpha_dash','max:50','unique:tenants,code'],
            'sip_domain'=>['required','string','max:190','unique:tenants,sip_domain'],
            'timezone'=>['required','timezone'],
            'extension_start'=>['required','integer','min:1'],
            'extension_end'=>['required','integer','gt:extension_start'],
            'max_extensions'=>['required','integer','min:1'],
            'max_agents'=>['required','integer','min:1'],
            'max_queues'=>['required','integer','min:1'],
            'max_concurrent_calls'=>['required','integer','min:1'],
            'recording_retention_days'=>['required','integer','between:1,3650'],
            'features'=>['sometimes','array'],
            'admin.name'=>['required','string','max:255'],
            'admin.username'=>['required','string','min:2','max:64','regex:/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/'],
            'admin.email'=>['required','email:rfc','max:255','unique:users,email'],
            'admin.password'=>['required','string','max:128','confirmed',Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $tenant=DB::transaction(function () use ($data): Tenant {
            $admin=$data['admin']; unset($data['admin']);
            $tenant=Tenant::create($data+['status'=>'ACTIVE','default_language'=>'en','max_campaigns'=>0]);
            User::create([
                'tenant_id'=>$tenant->id,
                'name'=>$admin['name'],
                'username'=>$admin['username'],
                'email'=>$admin['email'],
                'password'=>$admin['password'],
                'role'=>UserRole::TENANT_ADMIN,
                'status'=>'ACTIVE',
            ]);

            return $tenant;
        });

        return response()->json(['data'=>$tenant->load('users')->loadCount('users')], 201);
    }
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data=$request->validate(['name'=>['sometimes','string','max:150'],'sip_domain'=>['sometimes','string','max:190',Rule::unique('tenants')->ignore($tenant->id)],'status'=>['sometimes',Rule::in(['ACTIVE','SUSPENDED'])],'timezone'=>['sometimes','timezone'],'features'=>['sometimes','array']]);
        $tenant->update($data); return response()->json(['data'=>$tenant->fresh()]);
    }
}
