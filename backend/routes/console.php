<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('model:prune')->dailyAt('02:30');
