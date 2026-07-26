<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use App\Services\DestinationNumberValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function store(Request $request,DestinationNumberValidator $numbers): JsonResponse
    {
        $tenantId=$request->user()->tenant_id;
        $tenant=$request->user()->tenant;
        $data=$this->validated($request,null);
        $numbers->ensureAvailable($tenantId,(string)$data['extension_number'],null,'extension_number');

        if (Extension::query()->count() >= $tenant->max_extensions) {
            throw ValidationException::withMessages(['extension_number'=>'This tenant has reached its extension capacity.']);
        }

        $data['extension_number']=(string)$data['extension_number'];
        $extension=Extension::create($data+['tenant_id'=>$tenantId,'status'=>'ACTIVE']);
        return response()->json(['data'=>$extension->makeHidden('sip_password')],201);
    }

    public function update(Request $request,Extension $extension,DestinationNumberValidator $numbers): JsonResponse
    {
        $data=$this->validated($request,$extension);
        $numbers->ensureAvailable($request->user()->tenant_id,(string)$data['extension_number'],$extension,'extension_number');
        if (empty($data['sip_password'])) unset($data['sip_password']);
        $data['extension_number']=(string)$data['extension_number'];
        $extension->update($data);
        return response()->json(['data'=>$extension->fresh()->load('user:id,name,email')]);
    }

    public function revealPassword(Request $request,Extension $extension): JsonResponse
    {
        abort_unless($extension->tenant_id === $request->user()->tenant_id,404);

        DB::table('audit_logs')->insert([
            'id'=>(string) Str::uuid(),
            'tenant_id'=>$extension->tenant_id,
            'user_id'=>$request->user()->id,
            'action'=>'extension.sip_password_revealed',
            'auditable_type'=>Extension::class,
            'auditable_id'=>$extension->id,
            'before'=>null,
            'after'=>json_encode(['extension_number'=>$extension->extension_number],JSON_THROW_ON_ERROR),
            'ip_address'=>$request->ip(),
            'user_agent'=>Str::limit((string) $request->userAgent(),500,''),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        return response()->json(['data'=>[
            'sip_password'=>$extension->sip_password,
        ]])->withHeaders([
            'Cache-Control'=>'no-store, private',
            'Pragma'=>'no-cache',
        ]);
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
