<?php

/**
 * Auto Generated from Blender
 * Date: 2018/01/17 at 14:23:18 EST -05:00
 */

use \LCI\Blend\Migrations;

class m2018_01_10_093000_Snippet extends Migrations
{
    /** @var array  */
    protected $snippets = array (
      0 => 'modSnippet_testSnippet2',
    );

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->blender->blendManySnippets($this->snippets, $this->getSeedsDir());
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->blender->revertBlendManySnippets($this->snippets, $this->getSeedsDir());
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
    protected function assignSeedsDir()
    {
        $this->seeds_dir = 'm2018_01_10_093000_Snippet';
    }
}
