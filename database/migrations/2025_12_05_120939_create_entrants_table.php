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
        Schema::create('entrants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->enum('platform', ['twitch', 'youtube', 'google_sheet', 'manual']);
            $table->string('platform_user_id')->nullable();
            $table->string('platform_username');
            $table->string('entry_source_detail')->nullable();
            $table->boolean('is_subscriber')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['campaign_id', 'platform', 'platform_user_id'], 'unique_entry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entrants');
    }
};
