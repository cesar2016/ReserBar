<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('intent_id')->nullable()->constrained('intents')->onDelete('set null');
            $table->text('user_message');
            $table->text('assistant_message')->nullable();
            $table->json('extracted_entities')->nullable();
            $table->json('embedding')->nullable();
            $table->float('confidence')->default(0);
            $table->string('session_id', 100)->nullable();
            $table->boolean('was_successful')->default(true);
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('intent_id');
            $table->index('session_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_embeddings');
    }
};
