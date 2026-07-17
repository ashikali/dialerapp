<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index(): JsonResponse { return response()->json(Tenant::query()->latest()->paginate(25)); }
    public function store(Request $request): JsonResponse
    {
        $data=$request->validate(['name'=>['required','string','max:150'],'code'=>['required','alpha_dash','max:50','unique:tenants,code'],'sip_domain'=>['required','string','max:190','unique:tenants,sip_domain'],'timezone'=>['required','timezone'],'extension_start'=>['required','integer','min:1'],'extension_end'=>['required','integer','gt:extension_start'],'max_extensions'=>['required','integer','min:1'],'max_agents'=>['required','integer','min:1'],'max_queues'=>['required','integer','min:1'],'max_concurrent_calls'=>['required','integer','min:1'],'recording_retention_days'=>['required','integer','between:1,3650'],'features'=>['sometimes','array']]);
        return response()->json(['data'=>Tenant::create($data+['status'=>'ACTIVE','default_language'=>'en','max_campaigns'=>0])], 201);
    }
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data=$request->validate(['name'=>['sometimes','string','max:150'],'sip_domain'=>['sometimes','string','max:190',Rule::unique('tenants')->ignore($tenant->id)],'status'=>['sometimes',Rule::in(['ACTIVE','SUSPENDED'])],'timezone'=>['sometimes','timezone'],'features'=>['sometimes','array']]);
        $tenant->update($data); return response()->json(['data'=>$tenant->fresh()]);
    }
}
