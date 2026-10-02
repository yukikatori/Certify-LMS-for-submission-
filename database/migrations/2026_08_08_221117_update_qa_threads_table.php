<?php

declare(strict_types=1);

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
        Schema::table('qa_threads', function (Blueprint $table) {
            $table->dropColumn(['published_status', 'replies_count', 'last_replied_at']);
            $table->boolean('is_resolved')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('qa_threads', function (Blueprint $table) {
            $table->string('published_status')->nullable();
            $table->unsignedInteger('replies_count')->default(0);
            $table->timestamp('last_replied_at')->nullable();
            $table->dropColumn('is_resolved');
        });
    }
};
