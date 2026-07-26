<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use App\Models\RingGroup;
use App\Services\DestinationNumberValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RingGroupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(RingGroup::query()
            ->with($this->relations())
            ->orderBy('number')
            ->paginate(50));
    }

    public function store(Request $request,DestinationNumberValidator $numbers): JsonResponse
    {
        $data=$this->validated($request);
        $tenantId=$request->user()->tenant_id;
        $numbers->ensureAvailable($tenantId,(string)$data['number']);
        $ringGroup=DB::transaction(function() use($data,$tenantId): RingGroup {
            $ringGroup=RingGroup::create([...$this->attributes($data),'tenant_id'=>$tenantId]);
            $this->syncMembers($ringGroup,$data['member_extension_ids']);
            return $ringGroup;
        });
        return response()->json(['data'=>$this->fresh($ringGroup)],201);
    }

    public function update(Request $request,RingGroup $ringGroup,DestinationNumberValidator $numbers): JsonResponse
    {
        $data=$this->validated($request);
        $numbers->ensureAvailable($request->user()->tenant_id,(string)$data['number'],$ringGroup);
        DB::transaction(function() use($ringGroup,$data): void {
            $ringGroup->update($this->attributes($data));
            $this->syncMembers($ringGroup,$data['member_extension_ids']);
        });
        return response()->json(['data'=>$this->fresh($ringGroup)]);
    }

    private function validated(Request $request): array
    {
        $tenantId=$request->user()->tenant_id;
        return $request->validate([
            'name'=>['required','string','max:150'],
            'number'=>['required','regex:/^\d{2,16}$/'],
            'strategy'=>['required',Rule::in(['SIMULTANEOUS','SEQUENTIAL'])],
            'ring_timeout'=>['required','integer','between:5,120'],
            'status'=>['required',Rule::in(['ACTIVE','INACTIVE'])],
            'member_extension_ids'=>['required','array','min:1','max:100'],
            'member_extension_ids.*'=>['required','uuid','distinct',Rule::exists('extensions','id')->where('tenant_id',$tenantId)->where('status','ACTIVE')],
        ]);
    }

    private function attributes(array $data): array
    {
        return collect($data)->only(['name','number','strategy','ring_timeout','status'])->all();
    }

    private function syncMembers(RingGroup $ringGroup,array $extensionIds): void
    {
        $ringGroup->members()->delete();
        foreach(array_values($extensionIds) as $position=>$extensionId){
            $ringGroup->members()->create([
                'tenant_id'=>$ringGroup->tenant_id,
                'extension_id'=>$extensionId,
                'position'=>$position+1,
            ]);
        }
    }

    private function fresh(RingGroup $ringGroup): RingGroup
    {
        return $ringGroup->fresh()->load($this->relations());
    }

    private function relations(): array
    {
        return ['members.extension:id,tenant_id,user_id,extension_number,caller_id_name,status'];
    }
}
