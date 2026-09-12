<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->timestamp('rating_updated_at')->nullable();
            $table->unsignedInteger('rating_count')->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->string('parsing_status')->default('idle');
            $table->unsignedTinyInteger('parsing_progress')->default(0);
            $table->text('parsing_error')->nullable();
            $table->timestamp('data_updated_at')->nullable();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('author');
            $table->timestampTz('reviewed_at');
            $table->text('text')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
            $table->index(['organization_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'rating',
                'rating_updated_at',
                'rating_count',
                'review_count',
                'parsing_status',
                'parsing_progress',
                'parsing_error',
                'data_updated_at',
            ]);
        });
    }
};
