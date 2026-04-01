<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the client_project pivot table
        Schema::create('client_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'project_id']);
        });

        // 2. Migrate existing client_id data from projects into the pivot table
        DB::statement('
            INSERT INTO client_project (client_id, project_id, created_at, updated_at)
            SELECT client_id, id, NOW(), NOW()
            FROM projects
            WHERE client_id IS NOT NULL
              AND deleted_at IS NULL
        ');

        // 3. Drop client_id from projects
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });

        // 4. Add project_id to notification_logs
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('client_id')->constrained('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('tenant_id')->constrained('clients')->nullOnDelete();
        });

        DB::statement('
            UPDATE projects p
            INNER JOIN client_project cp ON cp.project_id = p.id
            SET p.client_id = cp.client_id
        ');

        Schema::dropIfExists('client_project');
    }
};
