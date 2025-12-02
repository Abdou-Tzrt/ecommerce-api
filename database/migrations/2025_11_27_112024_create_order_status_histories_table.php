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
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');  // Reference to the order
            $table->string('from_status')->nullable();                          // Previous status (null for first status)
            $table->string('to_status');                                        // New status
            $table->foreignId('user_id')->nullable()->constrained('users');     // User who made the change
            $table->text('notes')->nullable();                                  // Optional notes about the status change
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
