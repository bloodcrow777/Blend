<?php
/**
 * Created by PhpStorm.
 * User: jgulledge
 * Date: 9/30/2017
 * Time: 11:47 AM
 */

namespace LCI\Blend;


use function PHPSTORM_META\type;

abstract class Element
{
    /** @var  \modx */
    protected $modx;

    /** @var  Blender */
    protected $blender;

    /** @var array  */
    protected $cacheOptions = [];

    /** @var string */
    protected $seeds_dir = '';

    /** @var int $cache_life in seconds, 0 is forever */
    protected $cache_life = 0;

    /** @var string ~ xPDOObject class name, example: modChunk */
    protected $element_class;

    /** @var string  */
    protected $name_column_name = 'name';

    /** @var  string */
    protected $name;

    /** @var  string */
    protected $description;

    /** @var  int */
    protected $media_source_id;

    /** @var  bool */
    protected $static = false;

    /** @var string  */
    protected $code = null;

    /** @var bool  */
    protected $overwrite_static = false;

    /** @var  string */
    protected $static_file;

    /** @var null|Properties  */
    protected $properties = null;

    /** @var bool  */
    protected $error = false;

    /** @var array  */
    protected $error_messages = [];

    /** @var array  */
    protected $category_names = [];

    /** @var null|\xPDOObject  */
    protected $element = null;

    /** @var array  */
    protected $element_data = [];

    /** @var array  */
    protected $related_data = [];

    /** @var bool  */
    protected $exists = false;

    /** @var bool  */
    protected $debug = false;

    /**
     * Element constructor.
     *
     * @param \modx $modx
     * @param Blender $blender
     */
    public function __construct(\modx $modx, Blender $blender)
    {
        $this->modx = $modx;
        $this->blender = $blender;
        $this->properties = new Properties();
        $this->cacheOptions = [
            \xPDO::OPT_CACHE_KEY => 'elements',
            \xPDO::OPT_CACHE_PATH => $this->blender->getSeedsDirectory()
        ];
    }

    /**
     * @return bool
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     *
     * @return Element
     */
    public function setDebug(bool $debug)
    {
        $this->debug = $debug;
        return $this;
    }



    /**
     * @return string
     */
    public function getSeedsDir()
    {
        return $this->seeds_dir;
    }

    /**
     * @deprecated v0.9.7, use getSeedsDir
     * @return string
     */
    public function getTimestamp()
    {
        return $this->seeds_dir;
    }

    /**
     * @param string $dir ~ will be the directory name
     *
     * @return $this
     */
    public function setSeedsDir($dir)
    {
        $this->seeds_dir = (string) $dir;
        if (!empty($this->seeds_dir)) {
            $this->cacheOptions[\xPDO::OPT_CACHE_PATH] = $this->blender->getSeedsDirectory() . $dir . '/';
        }
        return $this;
    }

    /**
     * @deprecated v0.9.7,  use setSeedsDir
     * @param string $timestamp ~ will be the directory name
     *
     * @return $this
     */
    public function setSeedTimeDir($timestamp)
    {
        return $this->setSeedsDir($timestamp);
    }

    /**
     * @return bool
     */
    public function isError()
    {
        return $this->error;
    }
    /**
     * @return bool
     */
    public function isExists()
    {
        return $this->exists;
    }

    /**
     * @return array
     */
    public function getErrorMessages()
    {
        return $this->error_messages;
    }

    /**
     * @return mixed
     */
    public function getElementClass()
    {
        return $this->element_class;
    }

    /**
     * @param string $element_class ~ modChunk, modSnippet, modPlugin, modTemplate, modTemplateVariable
     *
     * @return $this
     */
    protected function setElementClass($element_class)
    {
        switch ($element_class) {
            case 'modChunk':
                break;
            case 'modSnippet':
                break;
            case 'modPlugin':
                break;
            case 'modTemplate':
                $this->name_column_name = 'templatename';
                break;
            case 'modTemplateVar':
                // no break
            case 'modTemplateVariable':
                $element_class = 'modTemplateVar';
                break;
            default:
                $this->error = true;
                $this->error_messages['type'] = 'Invalid element type set';
        }
        $this->element_class = $element_class;
        return $this;
    }
    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return $this
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $code ~ if not doing static file then set the Elements code here
     * @param bool $overwrite_static ~ if the setAsStatic is ran, false will keep the static content code, true will overwrite the static file
     * @return $this
     */
    public function setCode(string $code, $overwrite_static=false)
    {
        $this->code = $code;
        $this->overwrite_static = $overwrite_static;
        return $this;
    }

    /**
     * duplicate method for setCode, matches MODX naming
     * @param string $code ~ if not doing static file then set the Elements code here
     * @param bool $overwrite_static ~ if the setAsStatic is ran, false will keep the static content code, true will overwrite the static file
     * @return $this
     */
    public function setContent(string $code, $overwrite_static=false)
    {
        return $this->setCode($code, $overwrite_static);
    }

    /**
     * @param string $file - the file path
     *
     * @return $this
     */
    public function setAsStatic($file)
    {
        $this->media_source_id = 1;
        $this->static = true;
        $this->static_file = $file;
        return $this;
    }

    /**
     * @param string $category ~ nest like so: Category=>Child=>Child
     *
     * @return $this
     */
    public function setCategoryFromNames($category)
    {
        $categories = explode('=>', $category);

        $this->category_names = [];
        $lineage = '';

        $count = 0;
        foreach ($categories as $category) {
            if (!empty($lineage)) {
                $lineage .= '=>';
            }
            $lineage .= trim($category);

            $this->category_names[$count++] = ['name' => $category, 'lineage' => $lineage];
        }

        return $this;
    }

    /**
     * @return Properties
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setProperties(array $data)
    {
        $this->properties->mergePropertiesFromArray($data);
        return $this;
    }

    protected function makeStudyCase($name)
    {
        $StudyName = '';
        $parts = explode('_', $name);
        foreach ($parts as $part) {
            $StudyName .= ucfirst($part);
        }
        return $StudyName;
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    public function loadFromArray($data=[])
    {
        if ($data['static'] && !empty($data['static_file'])) {
            $this->setAsStatic($data['static_file']);
        }

        foreach ($data as $column => $value) {
            $method_name = 'set'.$this->makeStudyCase($column);
            if ($column == 'category') {
                $method_name = 'setCategoryFromNames';
            }

            if (method_exists($this, $method_name) && !is_null($value)) {
                if ($method_name == 'setProperties' && !is_array($value)) {
                    continue;
                }
                if ($this->isDebug()) {
                    $this->blender->out(__METHOD__.' call: '.$method_name.' V: '.$value);
                }
                $this->$method_name($value);

            } elseif($this->isDebug()) {
                $this->blender->out(__METHOD__.' missing: '.$method_name.' V: '.$value, tru);
            }
        }
        return $this;
    }

    public function saveFromRaw($data, $overwrite=false)
    {
        $saved = false;
        $this->element = $this->modx->getObject($this->element_class, ['name' => $this->name]);
        if (is_object($this->element) && !$overwrite) {
            $this->error = true;
            $this->error_messages['exits'] = 'Element: '.$this->name . ' of type '.$this->element_class.' already exists ';
            return $saved;
        } else {
            $this->element = $this->modx->newObject($this->element_class);
        }
        unset($data['id']);
        $this->element->fromArray($data);

        return $this->element->save();
    }

    /**
     * @param bool $overwrite
     *
     * @return bool
     */
    public function save($overwrite=false)
    {
        $saved = false;

        $this->element = $this->modx->getObject($this->element_class, [$this->name_column_name => $this->name]);
        if (is_object($this->element)) {
            if (!$overwrite) {
                $this->error = true;
                $this->error_messages['exits'] = 'Element: ' . $this->name . ' of type ' . $this->element_class . ' already exists ';
                return $saved;
            }
        } else {
            $this->element = $this->modx->newObject($this->element_class);
        }

        $this->element->set($this->name_column_name, $this->name);

        if ( !empty($this->description)) {
            $this->element->set('description', $this->description);
        }

        if ($this->static) {
            // @TODO abstract media sources
            $this->element->set('source', $this->media_source_id);
            $this->element->set('static', 1);
            $this->element->set('static_file', $this->static_file);
            // do I need to fill the code/content from file_get_contents?

        } elseif ($this->static === false) {
            $this->element->set('source', 0);
            $this->element->set('static', 0);
            $this->element->set('static_file', '');

        }

        if ($this->code !== null && (($this->static && $this->overwrite_static) || $this->static !== true)) {
            // are all elements code column content?
            $this->element->set('content', $this->code);
        }
        // takes a string or array
        $this->element->set('properties', $this->properties->getData());

        $this->element->set('category', $this->getCategoryIDFromNames());

        $this->setAdditionalElementColumns();
        $this->relatedPieces();
        if ($this->element->save()) {
            $this->relatedPiecesAfterSave();
            if ($this->isDebug()) {
                $this->blender->out($this->getName() . ' has been installed/saved');
            }
            $saved = true;
        } else {
            if ($this->isDebug()) {
                $this->blender->out($this->getName() . ' did not install/update', true);
            }

        }
        //sync?

        return $saved;
    }

    /**
     * @return int
     */
    protected function getCategoryIDFromNames()
    {
        $category_id = 0;
        $categories = $this->blender->getCategoryMap();
        $refresh = false;
        foreach ($this->category_names as $count => $name_data) {
            $category = $name_data['name'];
            $lineage = $name_data['lineage'];

            if (isset($categories['lineage'][$lineage]) && isset($categories['lineage'][$lineage]['id'])) {
                $category_id = $categories['lineage'][$lineage]['id'];

            } else {
                $newCategory = $this->modx->newObject('modCategory');
                $newCategory->fromArray([
                    'parent' => $category_id,
                    'category' => $category,
                    'rank' => 0
                ]);
                $newCategory->save();
                $category_id = $newCategory->get('id');
                $refresh = true;
            }
        }
        $this->blender->getCategoryMap($refresh);
        return $category_id;
    }

    /**
     * @param int $category_id
     *
     * @return string
     */
    public function getCategoryAsString($category_id=0)
    {
        $categories = $this->blender->getCategoryMap();
        if (isset($categories['ids'][$category_id]) && isset($categories['ids'][$category_id]['lineage'])) {
            return $categories['ids'][$category_id]['lineage'];
        }

        return '';
    }
    /**
     * @param \modElement $element
     *
     * @return string
     */
    public function seedElement(\modElement $element)
    {
        $this->element = $element;
        // No IDs! must get the alias and get a seed key,
        $seed_key = $this->blender->getElementSeedKeyFromName($this->element->get($this->name_column_name), $this->element_class);
        $this->element_data = $this->element->toArray();

        $this->element_data['category'] = $this->getCategoryAsString($this->element_data['category']);

        $this->seedRelated($this->element);
        $this->element_data['related_data'] = $this->related_data;

        // https://docs.modx.com/revolution/2.x/developing-in-modx/other-development-resources/class-reference/modx/modx.invokeevent
        $this->modx->invokeEvent(
            'OnBlendSeedElement',
            [
                'blender' => $this->blender,
                'blendElement' => $this,
                'element_class' => $this->element_class,
                'element' => &$this->element,
                'data' => &$this->element_data
            ]
        );

        // now cache it:
        $this->modx->cacheManager->set(
            $seed_key,
            $this->element_data,
            $this->cache_life,
            $this->cacheOptions
        );

        return $seed_key;
    }
    /**
     * @param string $name
     *
     * @return bool|modElement
     */
    public function getElementFromName($name)
    {
        return $this->modx->getObject($this->element_class, [$this->name_column_name => $name]);
    }

    /**
     * Will load an existing modElement into element
     * @param string $name
     *
     * @return $this
     */
    public function loadElementFromName($name)
    {
        $this->element = $this->getElementFromName($name);

        if (is_object($this->element)) {
            $this->exists = true;
            $this->element_data = $this->element->toArray();
            $this->loadFromArray($this->element_data);
            // load related data:
            $this->loadRelatedData();
        }

        return $this;
    }

    /**
     * Override in child classes
     */
    protected function loadRelatedData()
    {

    }

    /**
     * @return array
     */
    public function getArrayForCopy()
    {
        $copy = $this->element_data;
        $copy['related_data'] = $this->related_data;
        return $copy;
    }

    /**
     * @param string $seed_key
     * @param bool $overwrite
     *
     * @return bool
     */
    public function blendFromSeed($seed_key, $overwrite=false)
    {
        $this->loadElementDataFromSeed($seed_key);
        return $this->blend($overwrite);
    }

    /**
     * @param bool $overwrite
     *
     * @return bool
     */
    public function blend($overwrite=false)
    {
        $save = false;
        // does it exist
        $name = $this->getName();

        $down = false;
        /** @var Element $currentVersion */
        $currentVersion = $this->loadCurrentVersion($name);
        if ($currentVersion->isExists()) {
            $this->exists = true;
            if (!$overwrite) {
                return $save;
            }
            $down = $currentVersion->getArrayForCopy();
        } else {
            $this->exists = false;
        }

        // @TODO manual loading does not load the element_data property
        unset($this->element_data['id']);

        $this->modx->invokeEvent(
            'OnBlendElementBeforeSave',
            [
                'blender' => $this->blender,
                'blendResource' => $this,
                'element' => &$this->element,
                'data' => &$this->element_data
            ]
        );

        // load from array:
        if (count($this->element_data)) {
            $this->loadFromArray($this->element_data);
        }
        $save = $this->save($overwrite);

        if ($save) {
            // write old version to disk:
            $this->modx->cacheManager->set(
                'down-'.$this->blender->getElementSeedKeyFromName($this->getName(), $this->element_class),
                $down,
                $this->cache_life,
                $this->cacheOptions
            );
            $this->modx->invokeEvent(
                'OnBlendElementAfterSave',
                [
                    'blender' => $this->blender,
                    'blendResource' => $this,
                    'element' => &$this->element,
                    'data' => &$this->element_data
                ]
            );
        } else {
            $this->blender->out('Error did not save ', true);
        }
        return $save;
    }

    /**
     * @param string $seed_key
     *
     * @return bool
     */
    public function revertBlendFromSeed($seed_key)
    {
        $this->loadElementDataFromSeed($seed_key);
        return $this->revertBlend();
    }

    /**
     * @return bool
     */
    public function revertBlend()
    {
        $reverted = false;

        $this->element = $this->getElementFromName($this->getName());
        if (!is_object($this->element)) {
            $this->element = $this->modx->getObject($this->element_class);
        }
        // 1. get previous data from cache:
        $data = $this->modx->cacheManager->get('down-'.$this->blender->getElementSeedKeyFromName($this->getName(), $this->element_class), $this->cacheOptions);

        if (!$data) {
            if ($this->isDebug()) {
                $this->blender->out('Remove old' . $this->getName());
            }
            $reverted = $this->element->remove();

        } elseif (is_array($data)) {
            if ($this->isDebug()) {
                $this->blender->out('Restore to old ' . $this->getName());
            }
            // load old data:
            $this->element->fromArray($data);
            $reverted = $this->element->save();
        }

        if ($reverted || $data === false) {
            $this->revertRelatedPieces($data);
        }

        return $reverted;
    }

    /**
     * Must implement in child class
     * @param string $name
     *
     * @return Element
     */
    protected function loadCurrentVersion($name)
    {
        return $this;
    }

    /**
     * @param string $seed_key
     *
     * @return $this
     */
    protected function loadElementDataFromSeed($seed_key)
    {
        $this->element_data = $this->modx->cacheManager->get($seed_key, $this->cacheOptions);
        if ($this->element_data == false) {
            $this->blender->out('Error: Seed could not be found: '.$seed_key.' aborting', true);
            exit();
        }
        $this->loadFromArray($this->element_data);
        return $this;
    }

    protected function setAdditionalElementColumns()
    {

    }

    protected function relatedPieces()
    {

    }

    /**
     * @param array|bool $data ~ the data loaded from the down seed
     */
    protected function revertRelatedPieces($data)
    {

    }

    protected function relatedPiecesAfterSave()
    {

    }

    /**
     * @param \modElement $element
     *
     * @return \modElement
     */
    protected function seedRelated($element)
    {
        return $element;
    }

    /**
     * @return array
     */
    public function getRelatedData()
    {
        return $this->related_data;
    }

    /**
     * Called from loadFromArray(), for build from seeds, override in child classes
     * @param mixed $data
     *
     * @return $this
     */
    protected function setRelatedData($data)
    {
        return $this;
    }
}
