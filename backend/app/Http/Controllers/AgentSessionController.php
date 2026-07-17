<?php

namespace App\Http\Controllers;

use App\Enums\AgentStatus;
use App\Enums\WorkMode;
use App\Models\Agent;
use App\Models\AgentSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentSessionController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $data=$request->validate(['extension_id'=>['required','uuid'],'work_mode'=>['required',Rule::enum(WorkMode::class)],'device_type'=>['required',Rule::in(['WEBRTC','SIP_PHONE','SOFTPHONE'])]]);
        $agent=Agent::query()->where('user_id',$request->user()->id)->firstOrFail();
        abort_if($agent->sessions()->whereNull('logout_time')->exists(),409,'An active agent session already exists.');
        $session=AgentSession::create($data+['tenant_id'=>$request->user()->tenant_id,'agent_id'=>$agent->id,'status'=>AgentStatus::NOT_READY,'login_time'=>now(),'ip_address'=>$request->ip(),'user_agent'=>mb_substr((string)$request->userAgent(),0,500)]);
        return response()->json(['data'=>$session],201);
    }
    public function status(Request $request, AgentSession $session): JsonResponse
    {
        abort_if($session->logout_time,409,'The agent session has ended.');
        $data=$request->validate(['status'=>['required',Rule::enum(AgentStatus::class)]]);
        $session->update($data); return response()->json(['data'=>$session->fresh()]);
    }
    public function stop(Request $request, AgentSession $session): JsonResponse
    {
        abort_if($session->current_call_id,409,'An active call must end before logout.');
        $session->update(['status'=>AgentStatus::OFFLINE,'logout_time'=>now()]); return response()->json(['data'=>$session->fresh()]);
    }
}
