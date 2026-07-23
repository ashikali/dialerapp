<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExtensionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Extension::query()
            ->with('user:id,name,email')
            ->select(['id','tenant_id','user_id','extension_number','sip_username','caller_id_name','caller_id_number','status','webrtc_enabled','voicemail_enabled','dnd_enabled','ring_timeout','created_at'])
            ->orderBy('extension_number')
            ->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId=$request->user()->tenant_id;
        $tenant=$request->user()->tenant;
        $data=$this->validated($request,null);

        if (Extension::query()->count() >= $tenant->max_extensions) {
            throw ValidationException::withMessages(['extension_number'=>'This tenant has reached its extension capacity.']);
        }

        $data['extension_number']=(string)$data['extension_number'];
        $extension=Extension::create($data+['tenant_id'=>$tenantId,'status'=>'ACTIVE']);
        return response()->json(['data'=>$extension->makeHidden('sip_password')],201);
    }

    public function update(Request $request,Extension $extension): JsonResponse
    {
        $data=$this->validated($request,$extension);
        if (empty($data['sip_password'])) unset($data['sip_password']);
        $data['extension_number']=(string)$data['extension_number'];
        $extension->update($data);
        return response()->json(['data'=>$extension->fresh()->load('user:id,name,email')]);
    }

    private function validated(Request $request,?Extension $extension): array
    {
        $tenant=$request->user()->tenant;
        $tenantId=$tenant->id;
        $numberUnique=Rule::unique('extensions')->where('tenant_id',$tenantId);
        $usernameUnique=Rule::unique('extensions')->where('tenant_id',$tenantId);
        if ($extension) {
            $numberUnique->ignore($extension);
            $usernameUnique->ignore($extension);
        }
        return $request->validate([
            'extension_number'=>['required','integer','between:'.$tenant->extension_start.','.$tenant->extension_end,$numberUnique],
            'sip_username'=>['required','string','max:100',$usernameUnique],
            'sip_password'=>[$extension ? 'nullable' : 'required','string','min:16','max:128'],
            'caller_id_name'=>['required','string','max:100'],
            'caller_id_number'=>['required','string','max:32'],
            'status'=>[$extension ? 'sometimes' : 'nullable',Rule::in(['ACTIVE','INACTIVE'])],
            'webrtc_enabled'=>['required','boolean'],
            'voicemail_enabled'=>['required','boolean'],
            'dnd_enabled'=>['sometimes','boolean'],
            'ring_timeout'=>['required','integer','between:5,120'],
        ]);
    }
}
