<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('alimentos') && Schema::hasIndex('alimentos', 'alimentos_user_id_foreign')) {
            Schema::table('alimentos', function (Blueprint $table) {
                $table->dropForeign('alimentos_user_id_foreign');
            });
        }

        if (Schema::hasTable('alimentos') && ! Schema::hasIndex('alimentos', 'alimentos_user_id_index')) {
            Schema::table('alimentos', function (Blueprint $table) {
                $table->index('user_id');
            });
        }

        $indexedColumns = [
            'users' => ['email' => 191],
            'password_reset_tokens' => ['email' => 191],
            'sessions' => ['id' => 191],
            'cache' => ['key' => 191],
            'cache_locks' => ['key' => 191],
            'jobs' => ['queue' => 191],
            'job_batches' => ['id' => 191],
            'failed_jobs' => ['uuid' => 191, 'connection' => 64, 'queue' => 100],
        ];

        foreach ($indexedColumns as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column => $length) {
                    $table->string($column, $length)->change();
                }
            });
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (['users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'alimentos'] as $tableName) {
                if (Schema::hasTable($tableName)) {
                    DB::statement("ALTER TABLE `{$tableName}` ENGINE=MyISAM");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (['users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'alimentos'] as $tableName) {
                if (Schema::hasTable($tableName)) {
                    DB::statement("ALTER TABLE `{$tableName}` ENGINE=InnoDB");
                }
            }
        }

        if (Schema::hasTable('alimentos') && Schema::hasTable('users') && ! Schema::hasIndex('alimentos', 'alimentos_user_id_foreign')) {
            Schema::table('alimentos', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
