<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ExtensionController extends Controller
{
    public function index(): JsonResponse { return response()->json(Extension::query()->select(['id','tenant_id','user_id','extension_number','sip_username','caller_id_name','caller_id_number','status','webrtc_enabled','voicemail_enabled','dnd_enabled','ring_timeout','created_at'])->paginate(50)); }
    public function store(Request $request): JsonResponse
    {
        $tenantId=$request->user()->tenant_id;
        $data=$request->validate(['extension_number'=>['required','string','max:16',Rule::unique('extensions')->where('tenant_id',$tenantId)],'sip_username'=>['required','string','max:100',Rule::unique('extensions')->where('tenant_id',$tenantId)],'sip_password'=>['required','string','min:16','max:128'],'user_id'=>['nullable','uuid'],'caller_id_name'=>['required','string','max:100'],'caller_id_number'=>['required','string','max:32'],'webrtc_enabled'=>['boolean'],'voicemail_enabled'=>['boolean'],'ring_timeout'=>['integer','between:5,120']]);
        $extension=Extension::create($data+['tenant_id'=>$tenantId,'status'=>'ACTIVE']);
        return response()->json(['data'=>$extension->makeHidden('sip_password')],201);
    }
}
