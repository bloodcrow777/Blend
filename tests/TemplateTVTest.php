<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use LCI\Blend\Blender;
use LCI\Blend\TemplateVariable;
use League\CLImate\CLImate;

final class TemplateTVTest extends BaseBlend
{
    /** @var bool  */
    protected $install_blend = true;

    /** @var array  */
    protected $seeded_tvs = [
        'AutoTagTV',
        'CheckboxTV',
        'DateTV',
        'ListboxSingleSelectTV',
        'ListboxMultiSelectTV',
        'EmailTV',
        'FileTV',
        'HiddenTV',
        'ImageTV',
        'NumberTV',
        'RadioOptionsTV',
        'ResourceListTV',
        'RichTextTV',
        'TagTV',
        'TextTV',
        'TextareaTV',
        'URLTV'
    ];

    public function testLoadTemplateFromSeed()
    {
        $migration = 'TVsLoadFromSeedsExample';
        $template_name = 'TVAllTestTypes';
        $template_description = 'Test all default TV types';
        $template_code = '<!DOCTYPE html><html lang="en">
<head>
  <title>[[*pagetitle]]</title>
</head>
<body>
  [[*content]]
  [[*AutoTagTV]]
  [[*CheckboxTV]]
  [[*DateTV]]
  [[*ListboxSingleSelectTV]]
  [[*ListboxMultiSelectTV]]
  [[*EmailTV]]
  [[*FileTV]]
  [[*HiddenTV]]
  [[*ImageTV]]
  [[*NumberTV]]
  [[*RadioOptionsTV]]
  [[*ResourceListTV]]
  [[*RichTextTV]]
  [[*TagTV]]
  [[*TextTV]]
  [[*TextareaTV]]
  [[*URLTV]]
</body>
</html>';

        $this->blender->runMigration('up', 'master', 0, 0, $migration);

        $testTVTemplate = $this->modx->getObject('modTemplate', ['templatename' => $template_name]);
        $this->assertInstanceOf(
            '\modTemplate',
            $testTVTemplate,
            'Validate testTemplateMigration that template was created '.$template_name
        );

        if ($testTVTemplate instanceof \modTemplate) {
            $this->assertEquals(
                $template_name,
                $testTVTemplate->get('templatename'),
                'Compare template name'
            );

            $this->assertEquals(
                $template_description,
                $testTVTemplate->get('description'),
                'Compare template description'
            );

            $this->assertEquals(
                $template_code,
                $testTVTemplate->getContent(),
                'Compare template code'
            );

        }
    }

    /**
     * @depends testLoadTemplateFromSeed
     */
    public function testCreatedTVs()
    {
        $template_name = 'TVAllTestTypes';
        $testTVTemplate = $this->modx->getObject('modTemplate', ['templatename' => $template_name]);

        $cacheOptions = [
            \xPDO::OPT_CACHE_KEY => 'elements',
            \xPDO::OPT_CACHE_PATH  => $this->blender->getSeedsDirectory() . BLEND_TEST_SEEDS_DIR . '/'
        ];

        // get all related TVs:
        $created_tvs = [];
        $tvTemplates = $testTVTemplate->getMany('TemplateVarTemplates');
        foreach ($tvTemplates as $tvTemplate) {
            $tv = $tvTemplate->getOne('TemplateVar');
            $tv_name = $tv->get('name');

            $created_tvs[] = $tv_name;

            $created_data = $tv->toArray();
            unset($created_data['id'], $created_data['related_data'], $created_data['source'], $created_data['category']);

            $seed_data = $this->modx->cacheManager->get('modTemplateVar_'.$tv_name, $cacheOptions);
            unset($seed_data['id'], $seed_data['related_data'], $seed_data['source'], $seed_data['category']);

            $this->assertEquals(
                $seed_data,
                $created_data,
                'Compare seeded to created TV data: '.$tv_name
            );

        }

        $this->assertEquals(
            $this->seeded_tvs,
            $created_tvs,
            'Compare all seed created TVs'
        );
    }

    public function testRevertLoadTemplateFromSeed()
    {
        $migration = 'TVsLoadFromSeedsExample';
        $template_name = 'TVAllTestTypes';

        $testTVTemplate = $this->modx->getObject('modTemplate', ['templatename' => $template_name]);

        $this->assertInstanceOf(
            '\modTemplate',
            $testTVTemplate,
            'Validate testTemplateMigration that template was created '.$template_name
        );

        $this->blender->runMigration('down', 'master', 0, 0, $migration);

        $testTVTemplateRevert = $this->modx->getObject('modTemplate', ['templatename' => $template_name]);

        $this->assertEquals(
            false,
            $testTVTemplateRevert,
            'Compare testRevertLoadTemplateFromSeed, should be empty/false'
        );

        // now verify that that TVs were also removed:
        $remainingTVs = $this->modx->getCollection('modTemplateVar', ['name:IN' => $this->seeded_tvs]);

        $this->assertEquals(
            0,
            count($remainingTVs),
            'Compare that all TVs were removed'
        );
    }
}
