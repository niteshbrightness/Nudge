<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'activecollab_id']);
            $table->renameColumn('activecollab_id', 'external_id');
            $table->string('source')->nullable()->after('client_id');
        });

        DB::statement("UPDATE projects SET source = 'activecollab' WHERE external_id IS NOT NULL");

        Schema::table('projects', function (Blueprint $table) {
            $table->index(['tenant_id', 'source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'source', 'external_id']);
            $table->dropColumn('source');
            $table->renameColumn('external_id', 'activecollab_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->index(['tenant_id', 'activecollab_id']);
        });
    }
};
