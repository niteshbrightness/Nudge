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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('event_type');
            $table->jsonb('raw_payload');
            $table->jsonb('parsed_data')->nullable();
            $table->string('activecollab_url')->nullable()->comment('Deep-link to the item in ActiveCollab');
            $table->string('short_url')->nullable()->comment('TinyURL shortened URL');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
