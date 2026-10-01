<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_template_id')->constrained('process_templates')->cascadeOnDelete();
            $table->text('question');
            $table->text('description')->nullable();
            $table->string('response_type')->default('yes_no');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_template_items');
    }
};
