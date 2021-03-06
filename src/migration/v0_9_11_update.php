<?php

/**
 * Auto Generated from Blender
 * Date: 2017/11/10 at 15:29:37 EST -05:00
 */

use \LCI\Blend\Migrations;

class v0_9_11_update extends Migrations
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // only thing to change is the version number

        /** @var \LCI\Blend\SystemSetting $systemSetting */
        $systemSetting = new \LCI\Blend\SystemSetting($this->modx, $this->blender);
        $systemSetting
            ->setName('blend.version')
            ->setSeedsDir($this->getSeedsDir())
            ->setValue('0.9.11')
            ->setArea('Blend')
            ->blend();

        $this->modx->cacheManager->refresh();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        /** @var \LCI\Blend\SystemSetting $systemSetting */
        $systemSetting = new \LCI\Blend\SystemSetting($this->modx, $this->blender);
        $systemSetting
            ->setName('blend.version')
            ->setSeedsDir($this->getSeedsDir())
            ->revertBlend();

        $this->modx->cacheManager->refresh();
    }

    /**
     * Method is called on construct, please fill me in
     */
    protected function assignDescription()
    {
        $this->description = 'Update Blend to v0.9.11 from v0.9.10 and below';
    }

    /**
     * Method is called on construct, please fill me in
     */
    protected function assignVersion()
    {
        $this->version = '0.9.11';
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
        $this->seeds_dir = '2018_02_14_101010';
    }
}
