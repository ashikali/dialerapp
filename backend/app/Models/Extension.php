<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Extension extends Model
{
    use BelongsToTenant, HasUuids;
    protected $fillable = ['tenant_id','user_id','extension_number','sip_username','sip_password','caller_id_name','caller_id_number','status','webrtc_enabled','voicemail_enabled','dnd_enabled','ring_timeout'];
    protected $hidden = ['sip_password'];
    protected function casts(): array { return ['sip_password'=>'encrypted','webrtc_enabled'=>'boolean','voicemail_enabled'=>'boolean','dnd_enabled'=>'boolean']; }
}
