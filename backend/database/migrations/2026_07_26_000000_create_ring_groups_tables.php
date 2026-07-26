<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ring_groups',function(Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name',150);
            $table->string('number',20);
            $table->string('strategy',20)->default('SIMULTANEOUS');
            $table->unsignedSmallInteger('ring_timeout')->default(30);
            $table->string('status',20)->default('ACTIVE')->index();
            $table->timestamps();
            $table->unique(['tenant_id','number']);
        });

        Schema::create('ring_group_members',function(Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ring_group_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('extension_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
            $table->unique(['ring_group_id','extension_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ring_group_members');
        Schema::dropIfExists('ring_groups');
    }
};
