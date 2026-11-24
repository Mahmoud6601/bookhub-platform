<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (! Schema::hasColumn('books', 'deleted_at')) {
                $table->softDeletes();
            }
            $table->index('title');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
        });

        Schema::table('borrows', function (Blueprint $table) {
            $table->index(['user_id', 'returned_at']);
            $table->index('due_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE books MODIFY description TEXT NULL, MODIFY cover_url VARCHAR(2048) NULL, MODIFY stock INT UNSIGNED NOT NULL DEFAULT 0'
            );
        }
    }

    public function down(): void
    {
        Schema::table('borrows', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'returned_at']);
            $table->dropIndex(['due_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['title']);
            if (Schema::hasColumn('books', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
