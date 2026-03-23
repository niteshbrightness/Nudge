<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->unsignedBigInteger('activecollab_id')->nullable()->comment('Project ID in ActiveCollab');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->string('url')->nullable()->comment('ActiveCollab project URL');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'activecollab_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
