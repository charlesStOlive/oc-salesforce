<?php namespace Waka\SalesForce\Updates;

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Schema;

class CreateLogsfErrorsTable extends Migration
{
    public function up()
    {
        Schema::create('waka_salesforce_logsf_errors', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('logsf_id');
            $table->text('error')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('waka_salesforce_logsf_errors');
    }
}
