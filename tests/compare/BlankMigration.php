<?php

/**
 * Auto Generated from Blender
 * Date: 2018/01/06 at 9:43:39 EST -05:00
 */

use \LCI\Blend\Migrations;

class BlankMigration extends Migrations
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //@TODO
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //@TODO
    }

    /**
     * Method is called on construct, please fill me in
     */
    protected function assignDescription()
    {
        $this->description = '';
    }

    /**
     * Method is called on construct, please fill me in
     */
    protected function assignVersion()
    {

    }

    /**
     * Method is called on construct, can change to only run this migration for those types
     */
    protected function assignType()
    {
        $this->type = 'master';
    }

    /**
     * Method is called on construct, Child class can override and implement this
     */
    protected function assignTimestamp()
    {
        $this->timestamp = '2018_01_10_093000';
    }
}