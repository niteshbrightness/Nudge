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
        Schema::create('sms_consent_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('tenant_id')->nullable();
            $table->text('sms_content')->nullable();
            $table->string('action');
            $table->string('method');
            $table->timestamps();

            $table->index('phone_number');
            $table->index(['tenant_id', 'client_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_consent_logs');
    }
};
