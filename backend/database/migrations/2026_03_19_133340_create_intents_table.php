<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->text('description')->nullable();
            $table->json('required_entities')->nullable();
            $table->text('response_template')->nullable();
            $table->json('example_phrases');
            $table->json('embedding')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('name');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intents');
    }
};
