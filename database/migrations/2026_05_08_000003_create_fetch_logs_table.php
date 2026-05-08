<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFetchLogsTable extends Migration
{
    public function up()
    {
        Schema::create('fetch_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40)->default('estjt');
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->text('message')->nullable();
            $table->string('reference_id', 80)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('fetch_logs');
    }
}
