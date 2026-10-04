<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global filterable attributes (e.g. Color, Size, Brand, Price)
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name');                     // e.g. "Color", "Size"
            $table->string('type')->default('select');  // select | color | range | text
            $table->json('options')->nullable();        // ["Red","Blue","Green"] or null for range
            $table->timestamps();
        });

        // Which attributes are filterable for each category (pivot)
        Schema::create('attribute_category', function (Blueprint $table) {
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['attribute_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_category');
        Schema::dropIfExists('attributes');
    }
};
