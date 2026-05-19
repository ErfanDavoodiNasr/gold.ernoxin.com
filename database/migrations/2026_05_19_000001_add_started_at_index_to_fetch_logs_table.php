<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStartedAtIndexToFetchLogsTable extends Migration
{
    public function up()
    {
        Schema::table('fetch_logs', function (Blueprint $table) {
            $table->index(['status', 'started_at']);
        });
    }

    public function down()
    {
        Schema::table('fetch_logs', function (Blueprint $table) {
            $table->dropIndex(['status', 'started_at']);
        });
    }
}
