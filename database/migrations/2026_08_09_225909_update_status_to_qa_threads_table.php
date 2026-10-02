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
            $table->dropColumn('is_resolved');
            $table->string('status')->default('unresolved');
        });
    }

    public function down(): void
    {
        Schema::table('qa_threads', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->boolean('is_resolved')->default(false);
        });
    }
};
