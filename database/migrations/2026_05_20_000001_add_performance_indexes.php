<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerformanceIndexes extends Migration
{
    public function up()
    {
        Schema::table('market_items', function (Blueprint $table) {
            $table->index(['is_active', 'category']);
        });

        Schema::table('fetch_logs', function (Blueprint $table) {
            $table->index('finished_at');
        });
    }

    public function down()
    {
        Schema::table('fetch_logs', function (Blueprint $table) {
            $table->dropIndex(['finished_at']);
        });

        Schema::table('market_items', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'category']);
        });
    }
}
