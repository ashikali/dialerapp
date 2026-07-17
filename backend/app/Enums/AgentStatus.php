<?php

namespace App\Enums;

enum AgentStatus: string { case OFFLINE='OFFLINE'; case READY='READY'; case RESERVED='RESERVED'; case RINGING='RINGING'; case ON_CALL='ON_CALL'; case ON_HOLD='ON_HOLD'; case AFTER_CALL_WORK='AFTER_CALL_WORK'; case NOT_READY='NOT_READY'; case BREAK='BREAK'; case LUNCH='LUNCH'; case MEETING='MEETING'; case TRAINING='TRAINING'; }
