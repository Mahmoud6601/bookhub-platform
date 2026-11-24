<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('isbn')->nullable()->unique()->after('description');
            $table->decimal('price', 10, 2)->default(0)->after('isbn');
            $table->unsignedInteger('stock')->default(0)->after('price');
            $table->string('cover_url', 2048)->nullable()->after('stock');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete()->after('cover_url');
            $table->foreignId('author_id')->nullable()->constrained()->nullOnDelete()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['author_id']);
            $table->dropColumn(['isbn', 'price', 'stock', 'cover_url', 'category_id', 'author_id']);
        });
    }
};
