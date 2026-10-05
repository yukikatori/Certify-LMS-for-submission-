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
        Schema::create('google_calendar_connections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('coach_id')->constrained('users')->cascadeOnDelete();
            $table->string('google_account_id')->nullable();
            $table->string('google_email')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique('coach_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_calendar_connections');
    }
};
