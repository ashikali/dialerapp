<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantId}',fn($user,string $tenantId)=>$user->isSuperAdmin() || $user->tenant_id===$tenantId);
Broadcast::channel('tenant.{tenantId}.agent.{agentId}',function($user,string $tenantId,string $agentId){ if($user->isSuperAdmin())return true; return $user->tenant_id===$tenantId && ($user->isTenantAdmin() || optional($user->agent)->id===$agentId); });
Broadcast::channel('tenant.{tenantId}.queue.{queueId}',fn($user,string $tenantId)=>$user->isSuperAdmin() || $user->tenant_id===$tenantId);
