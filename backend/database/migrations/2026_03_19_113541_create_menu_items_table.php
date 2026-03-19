<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('menu_categories')->onDelete('cascade');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('day_of_week', 20)->nullable();
            $table->json('ingredients')->nullable();
            $table->json('embedding')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->string('image_url', 500)->nullable();
            $table->integer('preparation_time')->nullable();
            $table->timestamps();

            $table->index('category_id');
            $table->index('is_available');
            $table->index('day_of_week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
