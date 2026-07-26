<?php

use App\Http\Controllers\FreeSwitchXmlController;
use Illuminate\Support\Facades\Route;

Route::post('/freeswitch/xml/directory',[FreeSwitchXmlController::class,'directory'])->middleware('throttle:120,1');
Route::post('/freeswitch/xml/dialplan',[FreeSwitchXmlController::class,'dialplan'])->middleware('throttle:240,1');
Route::post('/freeswitch/xml/configuration',[FreeSwitchXmlController::class,'configuration'])->middleware('throttle:120,1');
