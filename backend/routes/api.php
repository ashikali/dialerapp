<?php

use App\Http\Controllers\AgentSessionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExtensionController;
use App\Http\Controllers\TenantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login',[AuthController::class,'login'])->middleware('throttle:5,1');
Route::middleware('auth:sanctum')->group(function(){
    Route::post('/auth/logout',[AuthController::class,'logout']);
    Route::get('/me',fn(Request $request)=>response()->json(['user'=>$request->user()]));
    Route::get('/dashboard',DashboardController::class);
    Route::middleware('role:SUPER_ADMIN')->group(function(){ Route::apiResource('tenants',TenantController::class)->only(['index','store','update']); });
    Route::middleware('role:TENANT_ADMIN')->group(function(){ Route::apiResource('extensions',ExtensionController::class)->only(['index','store']); });
    Route::prefix('agent')->middleware('role:AGENT')->group(function(){ Route::post('/sessions',[AgentSessionController::class,'start']); Route::patch('/sessions/{session}/status',[AgentSessionController::class,'status']); Route::delete('/sessions/{session}',[AgentSessionController::class,'stop']); });
});
