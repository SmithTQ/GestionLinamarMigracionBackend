<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 100)->nullable()->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->softDeletes();
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('status', 30)->default('draft')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campaign_branch', function (Blueprint $table): void {
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['campaign_id', 'branch_id']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 80)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('module', 60)->index();
            $table->string('action', 60);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('campaign_user', function (Blueprint $table): void {
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['campaign_id', 'user_id']);
        });

        Schema::create('branch_user', function (Blueprint $table): void {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['branch_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('campaign_user');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('campaign_branch');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('branches');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['username', 'is_active']);
        });
    }
};
