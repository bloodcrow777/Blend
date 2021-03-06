<?php

/**
 * Auto Generated from Blender
 * Date: 2018/02/12 at 17:36:08 UTC +00:00
 */

use \LCI\Blend\Migrations;

class m2018_01_10_093000_Resource extends Migrations
{
    /** @var array  */
    protected $resources = array (
      'web' => 
      array (
        0 => 'test-blend-many-resource-3',
        1 => 'test-blend-many-resource-4',
      ),
    );

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->blender->blendManyResources($this->resources, $this->getSeedsDir());
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->blender->revertBlendManyResources($this->resources, $this->getSeedsDir());
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
        $this->seeds_dir = 'm2018_01_10_093000_Resource';
    }
}