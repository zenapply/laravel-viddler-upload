<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateVideosTables extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('viddler.table'), function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('title');
            $table->string('status')->default('new');
            $table->boolean('uploaded')->default(false);
            $table->integer('encoding_progress')->nullable();
            $table->string('disk')->nullable();
            $table->string('extension')->nullable();
            $table->string('filename')->nullable();
            $table->string('mime')->nullable();
            $table->string('path')->default('/')->nullable();
            $table->string('viddler_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop(config('viddler.table'));
    }
}
