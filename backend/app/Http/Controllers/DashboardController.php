<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Call;
use App\Models\Extension;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $user = auth()->user();
        return response()->json(['data'=>[
            'tenants'=>$user->isSuperAdmin() ? Tenant::query()->count() : 1,
            'extensions'=>Extension::query()->count(), 'agents'=>Agent::query()->count(),
            'active_calls'=>Call::query()->whereIn('status',['RINGING','ANSWERED','BRIDGED','ON_HOLD'])->count(),
            'calls_today'=>Call::query()->whereDate('created_at', today())->count(),
        ]]);
    }
}
