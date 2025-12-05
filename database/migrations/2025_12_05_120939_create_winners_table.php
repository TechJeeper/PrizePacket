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
        Schema::create('winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns');
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items');
            $table->string('winner_display_name');
            $table->string('platform_origin', 50);

            $table->string('contact_email')->nullable();
            $table->text('shipping_address')->nullable();

            $table->boolean('status_winner_notified')->default(0);
            $table->boolean('status_info_collected')->default(0);
            $table->boolean('status_sent_to_sponsor')->default(0);
            $table->boolean('status_shipped')->default(0);

            $table->text('notes')->nullable();
            $table->timestamp('won_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('winners');
    }
};
