<?php namespace Waka\SalesForce\Updates;

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Schema;

class CreateLogsfsTable extends Migration
{
    public function up()
    {
        Schema::create('waka_salesforce_logsfs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name');
            $table->text('infos')->nullable();
            $table->text('query')->nullable();
            $table->integer('user_id')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_ended')->nullable();
            $table->integer('nb_updated_rows')->nullable();
            $table->integer('sf_total_size')->nullable();
            //softDelete
            $table->softDeletes();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('waka_salesforce_logsfs');
    }
}
