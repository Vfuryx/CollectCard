<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('members', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 15);
            $table->string('open_id', 45);
            $table->integer("card1")->default(0);
            $table->integer("card2")->default(0);
            $table->integer("card3")->default(0);
            $table->integer("card4")->default(0);
            $table->integer("card5")->default(0);
            $table->tinyInteger('level')->default(0);
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
        Schema::dropIfExists('members');
    }
}
