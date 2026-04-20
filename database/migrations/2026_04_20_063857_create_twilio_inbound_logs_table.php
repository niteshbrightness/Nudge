<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twilio_inbound_logs', function (Blueprint $table) {
            $table->id();
            $table->string('from_number');
            $table->text('body');
            $table->string('action')->nullable();   // 'stop' | 'start' | null (unrecognised keyword)
            $table->integer('clients_affected')->default(0);
            $table->jsonb('raw_payload');
            $table->timestamps();

            $table->index('from_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twilio_inbound_logs');
    }
};
