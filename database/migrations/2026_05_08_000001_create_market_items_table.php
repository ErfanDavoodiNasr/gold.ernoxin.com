<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMarketItemsTable extends Migration
{
    public function up()
    {
        Schema::create('market_items', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40)->default('estjt');
            $table->string('category', 30)->index();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('currency', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['source', 'normalized_name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('market_items');
    }
}
