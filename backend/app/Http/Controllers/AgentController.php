<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Extension;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AgentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Agent::query()
            ->with(['user:id,tenant_id,name,email,status', 'user.extensions:id,user_id,extension_number,status'])
            ->orderBy('display_name')
            ->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant=$request->user()->tenant;
        $tenantId=$tenant->id;
        $data=$request->validate([
            'name'=>['required','string','max:255'],
            'display_name'=>['required','string','max:255'],
            'employee_code'=>['required','alpha_dash','max:50',Rule::unique('agents')->where('tenant_id',$tenantId)],
            'email'=>['required','email','max:255','unique:users,email'],
            'password'=>['required','confirmed',Password::min(12)->letters()->mixedCase()->numbers()],
            'extension_id'=>['nullable','uuid',Rule::exists('extensions','id')->where('tenant_id',$tenantId)],
        ]);

        if (Agent::query()->count() >= $tenant->max_agents) {
            throw ValidationException::withMessages(['employee_code'=>'This tenant has reached its agent capacity.']);
        }

        $agent=DB::transaction(function () use ($data,$tenantId): Agent {
            $extension=null;
            if (!empty($data['extension_id'])) {
                $extension=Extension::query()->whereKey($data['extension_id'])->lockForUpdate()->firstOrFail();
                if ($extension->user_id) {
                    throw ValidationException::withMessages(['extension_id'=>'This extension is already assigned.']);
                }
            }
            $user=User::create([
                'tenant_id'=>$tenantId,
                'name'=>$data['name'],
                'email'=>$data['email'],
                'password'=>$data['password'],
                'role'=>UserRole::AGENT,
                'status'=>'ACTIVE',
            ]);
            $agent=Agent::create([
                'tenant_id'=>$tenantId,
                'user_id'=>$user->id,
                'employee_code'=>$data['employee_code'],
                'display_name'=>$data['display_name'],
                'status'=>'ACTIVE',
            ]);
            $extension?->update(['user_id'=>$user->id]);
            return $agent;
        });

        return response()->json(['data'=>$agent->load(['user.extensions'])],201);
    }
}
