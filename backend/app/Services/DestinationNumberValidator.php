<?php

namespace App\Services;

use App\Models\Extension;
use App\Models\PbxQueue;
use App\Models\RingGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class DestinationNumberValidator
{
    public function ensureAvailable(string $tenantId,string $number,?Model $ignore=null,string $field='number'): void
    {
        $checks=[
            Extension::withoutGlobalScope('tenant')->where('tenant_id',$tenantId)->where('extension_number',$number),
            PbxQueue::withoutGlobalScope('tenant')->where('tenant_id',$tenantId)->where('number',$number),
            RingGroup::withoutGlobalScope('tenant')->where('tenant_id',$tenantId)->where('number',$number),
        ];

        foreach($checks as $query){
            if($ignore && get_class($query->getModel())===get_class($ignore))$query->whereKeyNot($ignore->getKey());
            if($query->exists()) throw ValidationException::withMessages([$field=>'This destination number is already used by an extension, queue, or ring group.']);
        }
    }
}
