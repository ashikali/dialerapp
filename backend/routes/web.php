<?php

use App\Http\Controllers\FreeSwitchXmlController;
use Illuminate\Support\Facades\Route;

Route::post('/freeswitch/xml/directory',[FreeSwitchXmlController::class,'directory'])->middleware('throttle:120,1');
Route::post('/freeswitch/xml/{section}',[FreeSwitchXmlController::class,'emptySection'])->whereIn('section',['dialplan','configuration'])->middleware('throttle:120,1');
