<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePricePointsTable extends Migration
{
    public function up()
    {
        Schema::create('price_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_item_id')->constrained('market_items')->cascadeOnDelete();
            $table->decimal('current_value', 18, 4)->nullable();
            $table->decimal('high_value', 18, 4)->nullable();
            $table->decimal('low_value', 18, 4)->nullable();
            $table->decimal('yesterday_avg_value', 18, 4)->nullable();
            $table->decimal('change_value', 18, 4)->nullable();
            $table->decimal('change_percent', 10, 4)->nullable();
            $table->enum('direction', ['asc', 'desc', 'none'])->default('none');
            $table->json('raw_payload');
            $table->timestamp('fetched_at')->index();
            $table->timestamps();
            $table->unique(['market_item_id', 'fetched_at']);
            $table->index(['market_item_id', 'fetched_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('price_points');
    }
}
