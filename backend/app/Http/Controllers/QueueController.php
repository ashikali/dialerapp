<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\PbxQueue;
use App\Services\DestinationNumberValidator;
use App\Services\TelephonyCommandDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QueueController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(PbxQueue::query()
            ->with($this->relations())
            ->orderBy('number')
            ->paginate(50));
    }

    public function store(Request $request,DestinationNumberValidator $numbers,TelephonyCommandDispatcher $telephony): JsonResponse
    {
        $tenant=$request->user()->tenant;
        if(PbxQueue::query()->count()>=$tenant->max_queues) throw ValidationException::withMessages(['number'=>'This tenant has reached its queue capacity.']);
        $data=$this->validated($request);
        $numbers->ensureAvailable($tenant->id,(string)$data['number']);

        $queue=DB::transaction(function() use($data,$tenant): PbxQueue {
            $queue=PbxQueue::create([
                ...$this->attributes($data),
                'tenant_id'=>$tenant->id,
            ]);
            $this->syncMembers($queue,$data['member_agent_ids']);
            return $queue;
        });
        $this->dispatchSync($queue,$telephony,[]);

        return response()->json(['data'=>$this->fresh($queue)],201);
    }

    public function update(Request $request,PbxQueue $queue,DestinationNumberValidator $numbers,TelephonyCommandDispatcher $telephony): JsonResponse
    {
        $data=$this->validated($request,$queue);
        $numbers->ensureAvailable($request->user()->tenant_id,(string)$data['number'],$queue);
        $oldAgentIds=$queue->members()->pluck('agent_id')->all();

        DB::transaction(function() use($queue,$data): void {
            $queue->update($this->attributes($data));
            $this->syncMembers($queue,$data['member_agent_ids']);
        });
        $removed=array_values(array_diff($oldAgentIds,$data['member_agent_ids']));
        $this->dispatchSync($queue,$telephony,$removed);

        return response()->json(['data'=>$this->fresh($queue)]);
    }

    private function validated(Request $request,?PbxQueue $queue=null): array
    {
        $tenantId=$request->user()->tenant_id;
        $data=$request->validate([
            'name'=>['required','string','max:150'],
            'number'=>['required','regex:/^\d{2,16}$/'],
            'strategy'=>['required',Rule::in(['longest-idle-agent','round-robin','top-down','agent-with-least-talk-time','agent-with-fewest-calls','sequentially-by-agent-order','ring-all','ring-progressively'])],
            'wrap_up_seconds'=>['required','integer','between:0,600'],
            'max_wait_seconds'=>['required','integer','between:5,3600'],
            'max_size'=>['required','integer','between:1,1000'],
            'music_on_hold'=>['required','string','max:255','regex:/^[A-Za-z0-9_:\/.\-]+$/'],
            'status'=>['required',Rule::in(['ACTIVE','INACTIVE'])],
            'member_agent_ids'=>['required','array','min:1','max:200'],
            'member_agent_ids.*'=>['required','uuid','distinct',Rule::exists('agents','id')->where('tenant_id',$tenantId)->where('status','ACTIVE')],
        ]);

        $agents=Agent::query()
            ->whereKey($data['member_agent_ids'])
            ->with(['user.extensions'=>fn($query)=>$query->where('status','ACTIVE')])
            ->get();
        if($agents->count()!==count($data['member_agent_ids']) || $agents->contains(fn(Agent $agent)=>$agent->user->extensions->isEmpty())){
            throw ValidationException::withMessages(['member_agent_ids'=>'Every queue agent must have an assigned active extension.']);
        }
        return $data;
    }

    private function attributes(array $data): array
    {
        return collect($data)->only(['name','number','strategy','wrap_up_seconds','max_wait_seconds','max_size','music_on_hold','status'])->all();
    }

    private function syncMembers(PbxQueue $queue,array $agentIds): void
    {
        $queue->members()->delete();
        foreach(array_values($agentIds) as $position=>$agentId){
            $queue->members()->create([
                'tenant_id'=>$queue->tenant_id,
                'agent_id'=>$agentId,
                'priority'=>1,
                'skill'=>$position+1,
            ]);
        }
    }

    private function dispatchSync(PbxQueue $queue,TelephonyCommandDispatcher $telephony,array $removedAgentIds): void
    {
        $queue=$this->fresh($queue);
        $payload=[
            'queue_name'=>$queue->number.'@'.$queue->tenant->sip_domain,
            'active'=>$queue->status==='ACTIVE',
            'removed_agents'=>array_map(fn(string $id)=>$id.'@'.$queue->tenant->sip_domain,$removedAgentIds),
            'members'=>$queue->members->map(function($member) use($queue): array {
                $extension=$member->agent->user->extensions->first();
                return [
                    'agent_name'=>$member->agent_id.'@'.$queue->tenant->sip_domain,
                    'contact'=>'[leg_timeout='.$extension->ring_timeout.']user/'.$extension->extension_number.'@'.$queue->tenant->sip_domain,
                    'level'=>$member->priority,
                    'position'=>$member->skill,
                ];
            })->values()->all(),
        ];
        try{$telephony->dispatch($queue->tenant_id,'CALLCENTER_SYNC_QUEUE',$payload);}
        catch(\Throwable $exception){Log::warning('queue.sync.dispatch_failed',['queue_id'=>$queue->id,'message'=>$exception->getMessage()]);}
    }

    private function fresh(PbxQueue $queue): PbxQueue
    {
        return $queue->fresh()->load(['tenant:id,sip_domain',...$this->relations()]);
    }

    private function relations(): array
    {
        return ['members.agent.user:id,name,username,email','members.agent.user.extensions'=>fn($query)=>$query->select(['id','user_id','extension_number','status','ring_timeout'])->where('status','ACTIVE')];
    }
}
