<?php

namespace EvoSC\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use EvoSC\Classes\DB;
use EvoSC\Classes\Server;

class CreateServerStatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @param Builder $schemaBuilder
     * @return void
     */
    public function up(Builder $schemaBuilder)
    {
        $schemaBuilder->create('server-stats', function (Blueprint $table) {
            $table->string('Title')->nullable();
            $table->integer('MaxPlayers')->default(0);
            $table->integer('CurrentPlayers')->default(0);
            $table->string('CurrentMapName')->nullable();
        });

        DB::table('server-stats')insert([
            'Title' => "PhenexTech",
            'MaxPlayers' => "0",
            'CurrentPlayers' => "0",
            'CurrentMapName' => "None",
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @param Builder $schemaBuilder
     * @return void
     */
    public function down(Builder $schemaBuilder)
    {
        $schemaBuilder->drop('server-stats');
    }
}