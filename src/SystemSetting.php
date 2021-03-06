<?php
/**
 * Created by PhpStorm.
 * User: jgulledge
 * Date: 1/2/2018
 * Time: 2:21 PM
 */

namespace LCI\Blend;


class SystemSetting
{
    /** @var  \modx */
    protected $modx;

    /** @var  Blender */
    protected $blender;

    /** @var string */
    protected $seeds_dir = '';

    /** @var array  */
    protected $cacheOptions = [];

    /** @var int $cache_life in seconds, 0 is forever */
    protected $cache_life = 0;

    /** @var string */
    protected $namespace = 'core';

    /** @var string ~ xtype */
    protected $type = 'textfield';

    /** @var string ~ the system setting name(key) */
    protected $name = '';

    /** @var  string */
    protected $area = '';

    /** @var mixed|bool|string */
    protected $current_value;

    /** @var mixed|bool|string */
    protected $value;

    /** @var string */
    protected $edited_on;

    /** @var bool|\modSystemSetting */
    protected $systemSetting = false;

    /** @var bool  */
    protected $exists = false;

    /** @var bool  */
    protected $changed = false;

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
            \xPDO::OPT_CACHE_KEY => 'settings',
            \xPDO::OPT_CACHE_PATH => $this->blender->getSeedsDirectory()
        ];
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
     * @deprecated v0.9.7, use setSeedsDir
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
    public function isExists(): bool
    {
        $this->loadObject();
        return $this->exists;
    }

    /**
     * @return bool
     */
    public function isChanged(): bool
    {
        $this->loadObject();
        if ( $this->systemSetting->get('namespace') != $this->getNamespace() ||
            $this->systemSetting->get('area') != $this->getArea() ||
            $this->systemSetting->get('key') != $this->getName() ||
            $this->systemSetting->get('value') != $this->getValue() ||
            $this->systemSetting->get('xtype') != $this->getType()
        ) {
            $this->changed = true;
        }

        return $this->changed;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @param string $namespace
     *
     * @return SystemSetting
     */
    public function setNamespace(string $namespace): SystemSetting
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @see https://docs.modx.com/revolution/2.x/administering-your-site/settings/system-settings/#SystemSettings-TypesofSystemSettings
     * @param string $type
     *
     * @return SystemSetting
     */
    public function setType(string $type): SystemSetting
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    // @TODO changeName()

    /**
     * @param string $name
     *
     * @return SystemSetting
     */
    public function setName(string $name): SystemSetting
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @deprecated
     * @param string $key
     *
     * @return SystemSetting
     */
    public function setKey(string $key): SystemSetting
    {
        return $this->setName($key);
    }

    /**
     * @return string
     */
    public function getArea(): string
    {
        return $this->area;
    }

    /**
     * @param string $area
     *
     * @return SystemSetting
     */
    public function setArea(string $area): SystemSetting
    {
        $this->area = $area;
        return $this;
    }

    /**
     * @return bool|mixed|string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param bool|mixed|string $value
     *
     * @return SystemSetting
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return bool|mixed|string ~ the current value of the system setting before blend/save
     */
    public function getCurrentValue()
    {
        return $this->current_value;
    }

    /**
     * @return string
     */
    public function getEditedOn(): string
    {
        return $this->edited_on;
    }

    /**
     * @param string $edited_on
     *
     * @return SystemSetting
     */
    public function setEditedOn(string $edited_on): SystemSetting
    {
        $this->edited_on = $edited_on;
        return $this;
    }


    /**
     * @return bool
     */
    public function blend()
    {
        $down = false;
        $this->loadObject();
        if ($this->isExists()) {
            $down = $this->systemSetting->toArray();
        }

        $saved = false;

        if ($this->save()) {
            // write old verison to disk:
            $seed_key = $this->blender->getSeedKeyFromAlias('down-'.$this->getNamespace().'-'.$this->getName());

            // now cache it:
            $this->modx->cacheManager->set(
                $seed_key,
                $down,
                $this->cache_life,
                $this->cacheOptions
            );
            $saved = true;
        }

        return $saved;
    }

    /**
     * @return bool
     */
    public function revertBlend()
    {
        $this->loadObject(true);
        $seed_key = $this->blender->getSeedKeyFromAlias('down-'.$this->getNamespace().'-'.$this->getName());
        // 1. get from cache:
        $data = $this->modx->cacheManager->get($seed_key, $this->cacheOptions);

        if (!$data) {
            return $this->systemSetting->remove();

        } elseif (is_array($data)) {
            // load old data:
            $this->systemSetting->fromArray($data);
            return $this->systemSetting->save();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function save()
    {
        $this->loadObject();
        if ($this->isChanged() || $this->isExists() ) {
            $this->systemSetting->set('editedon', date('Y-m-d H:i:s'));
        } elseif (!empty($this->getEditedOn())) {
            $this->systemSetting->set('editedon', $this->getEditedOn());
        }
        $this->systemSetting->set('namespace', $this->getNamespace());
        $this->systemSetting->set('area', $this->getArea());
        $this->systemSetting->set('key', $this->getName());
        $this->systemSetting->set('value', $this->getValue());
        $this->systemSetting->set('xtype', $this->getType());

        return $this->systemSetting->save();
    }

    /**
     * @param bool $load_defaults
     */
    protected function loadObject($load_defaults=false)
    {
        if (!$this->systemSetting) {
            $this->systemSetting = $this->modx->getObject('modSystemSetting', $this->name);
            if ($this->systemSetting instanceof \modSystemSetting) {
                $this->current_value = $this->systemSetting->get('value');
                $this->exists = true;
                if ($load_defaults) {
                    $this->setNamespace($this->systemSetting->get('namespace'));
                    $this->setArea($this->systemSetting->get('area'));
                    $this->setName($this->systemSetting->get('key'));
                    $this->setValue($this->systemSetting->get('value'));
                    $this->setType($this->systemSetting->get('xtype'));
                }
            }
        }
        if (!$this->systemSetting) {
            $this->systemSetting = $this->modx->newObject('modSystemSetting');
        }
    }

    /***********************
     * Core settings
     ***********************/


    /**
     * Comments~~~
     * @param bool|string|int $value
     *
     * @return $this
     */
    public function setBoolSetting(bool $value): SystemSetting
    {
        $this->setName('**');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Comments~~~
     * @param int $value
     *
     * @return $this
     */
    public function coreIntSetting(int $value): SystemSetting
    {
        $this->setName('**');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Comments~~~
     * @param string $value
     *
     * @return $this
     */
    public function coreStringSetting(string $value): SystemSetting
    {
        $this->setName('**');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }









    /**
     * Check Category Access ~
     * Use this to enable or disable Category ACL checks (per Context). <strong>NOTE: If this option is set to no, then ALL Category Access Permissions will be ignored!</strong>
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreAccessCategoryEnabled(bool $value): SystemSetting
    {
        $this->setName('access_category_enabled');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Check Context Access ~
     * Use this to enable or disable Context ACL checks. <strong>NOTE: If this option is set to no, then ALL Context Access Permissions will be ignored. DO NOT disable this system-wide or for the mgr Context or you will disable access to the manager interface.</strong>
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreAccessContextEnabled(bool $value): SystemSetting
    {
        $this->setName('access_context_enabled');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Access Policy Schema Version ~
     * The version of the Access Policy system. DO NOT CHANGE.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreAccessPoliciesVersion(string $value): SystemSetting
    {
        $this->setName('access_policies_version');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Check Resource Group Access ~
     * Use this to enable or disable Resource Group ACL checks (per Context). <strong>NOTE: If this option is set to no, then ALL Resource Group Access Permissions will be ignored!</strong>
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreAccessResourceGroupEnabled(bool $value): SystemSetting
    {
        $this->setName('access_resource_group_enabled');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Allow Forwarding Across Contexts ~
     * When true, Symlinks and modX::sendForward() API calls can forward requests to Resources in other Contexts.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreAllowForwardAcrossContexts(bool $value): SystemSetting
    {
        $this->setName('allow_forward_across_contexts');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Allow Forgot Password in Manager Login Screen ~
     * Setting this to "No" will disable the forgot password ability on the manager login screen.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreAllowManagerLoginForgotPassword(bool $value): SystemSetting
    {
        $this->setName('allow_manager_login_forgot_password');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Allow Duplicate Emails for Users ~
     * If enabled, Users may share the same email address.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreAllowMultipleEmails(bool $value): SystemSetting
    {
        $this->setName('allow_multiple_emails');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Allow Tags in POST ~
     * If false, all POST variables will be stripped of HTML script tags, numeric entities, and MODX tags. MODX recommends to leave this set to false for Contexts other than mgr, where it is set to true by default.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreAllowTagsInPost(bool $value): SystemSetting
    {
        $this->setName('allow_tags_in_post');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Disable eval in TV binding ~
     * Select this option to enable or disable eval in TV binding. If this option is set to no, the code/value will just be handled as regular text.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreAllowTvEval(bool $value): SystemSetting
    {
        $this->setName('allow_tv_eval');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Anonymous Sessions ~
     * If disabled, only authenticated users will have access to a PHP session. This can reduce overhead for anonymous users and the load they impose on a MODX site if they do not need access to a unique session. If session_enabled is false, this setting has no effect as sessions would never be available.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreAnonymousSessions(bool $value): SystemSetting
    {
        $this->setName('anonymous_sessions');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Force PCLZip Archives ~
     * If true, will use PCLZip instead of ZipArchive as the zip extension. Turn this on if you are getting extractTo errors or are having problems with unzipping in Package Management.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreArchiveWith(bool $value): SystemSetting
    {
        $this->setName('archive_with');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Automatic Check for Package Updates ~
     * If 'Yes', MODX will automatically check for updates for packages in Package Management. This may slow the loading of the grid.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreAutoCheckPkgUpdates(bool $value): SystemSetting
    {
        $this->setName('auto_check_pkg_updates');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Cache Expiration Time for Automatic Package Updates Check ~
     * The number of minutes that Package Management will cache the results for checking for package updates.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreAutoCheckPkgUpdatesCacheExpire(string $value): SystemSetting
    {
        $this->setName('auto_check_pkg_updates_cache_expire');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Set container automatically ~
     * If set to yes, container property will be changed automatically.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreAutoIsfolder(bool $value): SystemSetting
    {
        $this->setName('auto_isfolder');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Menu indexing default ~
     * Select 'Yes' to turn on automatic menu index incrementing by default.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreAutoMenuindex(bool $value): SystemSetting
    {
        $this->setName('auto_menuindex');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Automatically generate alias ~
     * Select 'Yes' to have the system automatically generate an alias based on the Resource's page title when saving.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreAutomaticAlias(bool $value): SystemSetting
    {
        $this->setName('automatic_alias');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Base Help URL ~
     * The base URL by which to build the Help links in the top right of pages in the manager.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreBaseHelpUrl(string $value): SystemSetting
    {
        $this->setName('base_help_url');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Blocked Minutes ~
     * Here you can enter the number of minutes that a user will be blocked for if they reach their maximum number of allowed failed login attempts. Please enter this value as numbers only (no commas, spaces etc.)
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreBlockedMinutes(string $value): SystemSetting
    {
        $this->setName('blocked_minutes');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Enable Action Map Cache ~
     * When enabled, actions (or controller maps) will be cached to reduce manager page load times.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCacheActionMap(bool $value): SystemSetting
    {
        $this->setName('cache_action_map');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Enable Context Alias Map Cache ~
     * When enabled, all Resource URIs are cached into the Context. Enable on smaller sites and disable on larger sites for better performance.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCacheAliasMap(bool $value): SystemSetting
    {
        $this->setName('cache_alias_map');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Enable Context Setting Cache ~
     * When enabled, context settings will be cached to reduce load times.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCacheContextSettings(bool $value): SystemSetting
    {
        $this->setName('cache_context_settings');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Enable Database Cache ~
     * When enabled, objects and raw result sets from SQL queries are cached to significantly reduce database loads.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCacheDb(bool $value): SystemSetting
    {
        $this->setName('cache_db');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Expiration Time for DB Cache ~
     * This value (in seconds) sets the amount of time cache files last for DB result-set caching.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreCacheDbExpires(string $value): SystemSetting
    {
        $this->setName('cache_db_expires');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Enable Database Session Cache ~
     * When enabled, and cache_db is enabled, database sessions will be cached in the DB result-set cache.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCacheDbSession(bool $value): SystemSetting
    {
        $this->setName('cache_db_session');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Expiration Time for DB Session Cache ~
     * This value (in seconds) sets the amount of time cache files last for session entries in the DB result-set cache.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreCacheDbSessionLifetime(string $value): SystemSetting
    {
        $this->setName('cache_db_session_lifetime');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Cacheable default ~
     * Select 'Yes' to make all new Resources cacheable by default.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCacheDefault(bool $value): SystemSetting
    {
        $this->setName('cache_default');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Disable Global Cache Options ~
     * Select 'Yes' to disable all MODX caching features. MODX does not recommend disabling caching.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCacheDisabled(bool $value): SystemSetting
    {
        $this->setName('cache_disabled');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Expiration Time for Default Cache ~
     * This value (in seconds) sets the amount of time cache files last for default caching.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreCacheExpires(string $value): SystemSetting
    {
        $this->setName('cache_expires');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Caching Format to Use ~
     * 0 = PHP, 1 = JSON, 2 = serialize. One of the formats
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreCacheFormat(string $value): SystemSetting
    {
        $this->setName('cache_format');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Caching Handler Class ~
     * The class name of the type handler to use for caching.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreCacheHandler(string $value): SystemSetting
    {
        $this->setName('cache_handler');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Cache Lexicon JS Strings ~
     * If set to true, this will use server headers to cache the lexicon strings loaded into JavaScript for the manager interface.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCacheLangJs(bool $value): SystemSetting
    {
        $this->setName('cache_lang_js');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Cache Lexicon Topics ~
     * When enabled, all Lexicon Topics will be cached so as to greatly reduce load times for Internationalization functionality. MODX strongly recommends leaving this set to 'Yes'.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCacheLexiconTopics(bool $value): SystemSetting
    {
        $this->setName('cache_lexicon_topics');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Cache Non-Core Lexicon Topics ~
     * When disabled, non-core Lexicon Topics will be not be cached. This is useful to disable when developing your own Extras.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCacheNoncoreLexiconTopics(bool $value): SystemSetting
    {
        $this->setName('cache_noncore_lexicon_topics');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Enable Partial Resource Cache ~
     * Partial resource caching is configurable by resource when this feature is enabled.  Disabling this feature will disable it globally.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCacheResource(bool $value): SystemSetting
    {
        $this->setName('cache_resource');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Expiration Time for Partial Resource Cache ~
     * This value (in seconds) sets the amount of time cache files last for partial Resource caching.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreCacheResourceExpires(string $value): SystemSetting
    {
        $this->setName('cache_resource_expires');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Enable Script Cache ~
     * When enabled, MODX will cache all Scripts (Snippets and Plugins) to file to reduce load times. MODX recommends leaving this set to 'Yes'.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCacheScripts(bool $value): SystemSetting
    {
        $this->setName('cache_scripts');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Enable System Setting Cache ~
     * When enabled, system settings will be cached to reduce load times. MODX recommends leaving this on.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCacheSystemSettings(bool $value): SystemSetting
    {
        $this->setName('cache_system_settings');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Refresh Trees on Site Cache Clear ~
     * When enabled, will refresh the trees after clearing the site cache.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreClearCacheRefreshTrees(bool $value): SystemSetting
    {
        $this->setName('clear_cache_refresh_trees');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Use Compressed CSS ~
     * When this is enabled, MODX will use a compressed version of its CSS stylesheets in the manager interface.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCompressCss(bool $value): SystemSetting
    {
        $this->setName('compress_css');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Use Compressed JavaScript Libraries ~
     * When this is enabled, MODX will serve a compressed version of the core scripts file.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreCompressJs(bool $value): SystemSetting
    {
        $this->setName('compress_js');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Maximum JavaScript Files Compression Threshold ~
     * The maximum number of JavaScript files MODX will attempt to compress at once when compress_js is on. Set to a lower number if you are experiencing issues with Google Minify in the manager.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreCompressJsMaxFiles(string $value): SystemSetting
    {
        $this->setName('compress_js_max_files');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Confirm Navigation with unsaved changes ~
     * When this is enabled, the user will be prompted to confirm their intention if there are unsaved changes.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreConfirmNavigation(bool $value): SystemSetting
    {
        $this->setName('confirm_navigation');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Container Suffix ~
     * The suffix to append to Resources set as containers when using FURLs.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreContainerSuffix(string $value): SystemSetting
    {
        $this->setName('container_suffix');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Enable Sorting of Contexts in Resource Tree ~
     * If set to Yes, Contexts will be alphanumerically sorted in the left-hand Resources tree.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreContextTreeSort(bool $value): SystemSetting
    {
        $this->setName('context_tree_sort');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Sort Field of Contexts in Resource Tree ~
     * The field to sort Contexts by in the Resources tree, if sorting is enabled.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreContextTreeSortby(string $value): SystemSetting
    {
        $this->setName('context_tree_sortby');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Sort Direction of Contexts in Resource Tree ~
     * The direction to sort Contexts in the Resources tree, if sorting is enabled.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreContextTreeSortdir(string $value): SystemSetting
    {
        $this->setName('context_tree_sortdir');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Language ~
     * Select the language for all non-manager Contexts, including web.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreCultureKey(string $value): SystemSetting
    {
        $this->setName('cultureKey');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Default Time Zone ~
     * Controls the default timezone setting for PHP date functions, if not empty. If empty and the PHP date.timezone ini setting is not set in your environment, UTC will be assumed.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreDateTimezone(string $value): SystemSetting
    {
        $this->setName('date_timezone');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Debug ~
     * Controls turning debugging on/off in MODX and/or sets the PHP error_reporting level. '' = use current error_reporting, '0' = false (error_reporting = 0), '1' = true (error_reporting = -1), or any valid error_reporting value (as an integer).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreDebug(string $value): SystemSetting
    {
        $this->setName('debug');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Default Content Type ~
     * Select the default Content Type you wish to use for new Resources. You can still select a different Content Type in the Resource editor; this setting just pre-selects one of your Content Types for you.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreDefaultContentType(string $value): SystemSetting
    {
        $this->setName('default_content_type');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Default Context ~
     * Select the default Context you wish to use for new Resources.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreDefaultContext(string $value): SystemSetting
    {
        $this->setName('default_context');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Default Duplicate Resource Publishing Option ~
     * The default selected option when duplicating a Resource. Can be either "unpublish" to unpublish all duplicates, "publish" to publish all duplicates, or "preserve" to preserve the publish state based on the duplicated Resource.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreDefaultDuplicatePublishOption(string $value): SystemSetting
    {
        $this->setName('default_duplicate_publish_option');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Default Media Source ~
     * The default Media Source to load.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreDefaultMediaSource(string $value): SystemSetting
    {
        $this->setName('default_media_source');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Default Per Page ~
     * The default number of results to show in grids throughout the manager.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreDefaultPerPage(string $value): SystemSetting
    {
        $this->setName('default_per_page');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Default Template ~
     * Select the default Template you wish to use for new Resources. You can still select a different template in the Resource editor, this setting just pre-selects one of your Templates for you.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreDefaultTemplate(string $value): SystemSetting
    {
        $this->setName('default_template');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Default username ~
     * Default username for an unauthenticated user.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreDefaultUsername(string $value): SystemSetting
    {
        $this->setName('default_username');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Path to CSS file ~
     * Enter the path to your CSS file that you wish to use within a richtext editor. The best way to enter the path is to enter the path from the root of your server, for example: /assets/site/style.css. If you do not wish to load a style sheet into a richtext editor, leave this field blank.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreEditorCssPath(string $value): SystemSetting
    {
        $this->setName('editor_css_path');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * CSS Selectors for Editor ~
     * A comma-separated list of CSS selectors for a richtext editor.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreEditorCssSelectors(string $value): SystemSetting
    {
        $this->setName('editor_css_selectors');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Registration Email From Address ~
     * Here you can specify the email address used when sending Users their usernames and passwords.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreEmailsender(string $value): SystemSetting
    {
        $this->setName('emailsender');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Registration Email Subject ~
     * The subject line for the default signup email when a User is registered.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreEmailsubject(string $value): SystemSetting
    {
        $this->setName('emailsubject');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Enable Drag/Drop in Resource/Element Trees ~
     * If off, will prevent dragging and dropping in Resource and Element trees.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreEnableDragdrop(bool $value): SystemSetting
    {
        $this->setName('enable_dragdrop');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Enable Gravatar ~
     * If enabled, Gravatar will be used as a profile image (if user do not have profile photo uploaded).
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreEnableGravatar(bool $value): SystemSetting
    {
        $this->setName('enable_gravatar');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Error Page ~
     * Enter the ID of the document you want to send users to if they request a document which doesn't actually exist (404 Page Not Found). <strong>NOTE: make sure this ID you enter belongs to an existing document, and that it has been published!</strong>
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreErrorPage(string $value): SystemSetting
    {
        $this->setName('error_page');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Extension Packages ~
     * A JSON array of packages to load on MODX instantiation. In the format [{"packagename":{"path":"path/to/package"}},{"anotherpackagename":{"path":"path/to/otherpackage"}}]
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreExtensionPackages(string $value): SystemSetting
    {
        $this->setName('extension_packages');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Failed Login Attempts ~
     * The number of failed login attempts a User is allowed before becoming 'blocked'.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFailedLoginAttempts(string $value): SystemSetting
    {
        $this->setName('failed_login_attempts');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Front-end Editor Language ~
     * Choose a language for the editor to use when used as a front-end editor.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFeEditorLang(string $value): SystemSetting
    {
        $this->setName('fe_editor_lang');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * MODX News Feed URL ~
     * Set the URL for the RSS feed for the MODX News panel in the manager.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFeedModxNews(string $value): SystemSetting
    {
        $this->setName('feed_modx_news');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * MODX News Feed Enabled ~
     * If 'No', MODX will hide the News feed in the welcome section of the manager.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreFeedModxNewsEnabled(bool $value): SystemSetting
    {
        $this->setName('feed_modx_news_enabled');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * MODX Security Notices Feed URL ~
     * Set the URL for the RSS feed for the MODX Security Notices panel in the manager.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFeedModxSecurity(string $value): SystemSetting
    {
        $this->setName('feed_modx_security');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * MODX Security Feed Enabled ~
     * If 'No', MODX will hide the Security feed in the welcome section of the manager.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreFeedModxSecurityEnabled(bool $value): SystemSetting
    {
        $this->setName('feed_modx_security_enabled');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * File Manager Path (Deprecated) ~
     * Deprecated - use Media Sources instead. IIS often does not populate the document_root setting properly, which is used by the file manager to determine what you can look at. If you're having problems using the file manager, make sure this path points to the root of your MODX installation.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFilemanagerPath(string $value): SystemSetting
    {
        $this->setName('filemanager_path');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Is File Manager Path Relative? (Deprecated) ~
     * Deprecated - use Media Sources instead. If your filemanager_path setting is relative to the MODX base_path, then please set this setting to Yes. If your filemanager_path is outside the docroot, set this to No.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreFilemanagerPathRelative(bool $value): SystemSetting
    {
        $this->setName('filemanager_path_relative');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * File Manager Url (Deprecated) ~
     * Deprecated - use Media Sources instead. Optional. Set this if you want to set an explicit URL to access the files in the MODX file manager from (useful if you have changed filemanager_path to a path outside the MODX webroot). Make sure this is the web-accessible URL of the filemanager_path setting value. If you leave this empty, MODX will try to automatically calculate it.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFilemanagerUrl(string $value): SystemSetting
    {
        $this->setName('filemanager_url');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Is File Manager URL Relative? (Deprecated) ~
     * Deprecated - use Media Sources instead. If your filemanager_url setting is relative to the MODX base_url, then please set this setting to Yes. If your filemanager_url is outside the main webroot, set this to No.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreFilemanagerUrlRelative(bool $value): SystemSetting
    {
        $this->setName('filemanager_url_relative');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Forgot Login Email ~
     * The template for the email that is sent when a user has forgotten their MODX username and/or password.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreForgotLoginEmail(string $value): SystemSetting
    {
        $this->setName('forgot_login_email');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Use All User Group Memberships for Form Customization ~
     * If set to true, FC will use *all* Sets for *all* User Groups a member is in when applying Form Customization Sets. Otherwise, it will only use the Set belonging to the User's Primary Group. Note: setting this to Yes might cause bugs with conflicting FC Sets.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreFormCustomizationUseAllGroups(bool $value): SystemSetting
    {
        $this->setName('form_customization_use_all_groups');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * sendForward Exclude Fields on Merge ~
     * A Symlink merges non-empty field values over the values in the target Resource; using this comma-delimited list of excludes prevents specified fields from being overridden by the Symlink.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreForwardMergeExcludes(string $value): SystemSetting
    {
        $this->setName('forward_merge_excludes');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * FURL Lowercase Aliases ~
     * Determines whether to allow only lowercase characters in a Resource alias.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreFriendlyAliasLowercaseOnly(bool $value): SystemSetting
    {
        $this->setName('friendly_alias_lowercase_only');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * FURL Alias Maximum Length ~
     * If greater than zero, the maximum number of characters to allow in a Resource alias. Zero equals unlimited.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFriendlyAliasMaxLength(string $value): SystemSetting
    {
        $this->setName('friendly_alias_max_length');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * FURL Alias Real-Time ~
     * Determines whether a resource alias should be created on the fly when typing the pagetitle or if this should happen when the resource is saved (automatic_alias needs to be enabled for this to have an effect).
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreFriendlyAliasRealtime(bool $value): SystemSetting
    {
        $this->setName('friendly_alias_realtime');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * FURL Alias Character Restriction Method ~
     * The method used to restrict characters used in a Resource alias. "pattern" allows a RegEx pattern to be provided, "legal" allows any legal URL characters, "alpha" allows only letters of the alphabet, and "alphanumeric" allows only letters and numbers.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFriendlyAliasRestrictChars(string $value): SystemSetting
    {
        $this->setName('friendly_alias_restrict_chars');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * FURL Alias Character Restriction Pattern ~
     * A valid RegEx pattern for restricting characters used in a Resource alias.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFriendlyAliasRestrictCharsPattern(string $value): SystemSetting
    {
        $this->setName('friendly_alias_restrict_chars_pattern');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * FURL Alias Strip Element Tags ~
     * Determines if Element tags should be stripped from a Resource alias.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreFriendlyAliasStripElementTags(bool $value): SystemSetting
    {
        $this->setName('friendly_alias_strip_element_tags');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * FURL Alias Transliteration ~
     * The method of transliteration to use on an alias specified for a Resource. Empty or "none" is the default which skips transliteration. Other possible values are "iconv" (if available) or a named transliteration table provided by a custom transliteration service class.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFriendlyAliasTranslit(string $value): SystemSetting
    {
        $this->setName('friendly_alias_translit');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * FURL Alias Transliteration Service Class ~
     * An optional service class to provide named transliteration services for FURL Alias generation/filtering.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFriendlyAliasTranslitClass(string $value): SystemSetting
    {
        $this->setName('friendly_alias_translit_class');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * FURL Alias Transliteration Service Class Path ~
     * The model package location where the FURL Alias Transliteration Service Class will be loaded from.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFriendlyAliasTranslitClassPath(string $value): SystemSetting
    {
        $this->setName('friendly_alias_translit_class_path');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * FURL Alias Trim Characters ~
     * Characters to trim from the ends of a provided Resource alias.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFriendlyAliasTrimChars(string $value): SystemSetting
    {
        $this->setName('friendly_alias_trim_chars');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * FURL Alias Word Delimiter ~
     * The preferred word delimiter for friendly URL alias slugs.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFriendlyAliasWordDelimiter(string $value): SystemSetting
    {
        $this->setName('friendly_alias_word_delimiter');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * FURL Alias Word Delimiters ~
     * Characters which represent word delimiters when processing friendly URL alias slugs. These characters will be converted and consolidated to the preferred FURL alias word delimiter.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreFriendlyAliasWordDelimiters(string $value): SystemSetting
    {
        $this->setName('friendly_alias_word_delimiters');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Use Friendly URLs ~
     * This allows you to use search engine friendly URLs with MODX. Please note, this only works for MODX installations running on Apache, and you'll need to write an .htaccess file for this to work. See the .htaccess file included in the distribution for more info.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreFriendlyUrls(bool $value): SystemSetting
    {
        $this->setName('friendly_urls');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Use Strict Friendly URLs ~
     * When friendly URLs are enabled, this option forces non-canonical requests that match a Resource to 301 redirect to the canonical URI for that Resource. WARNING: Do not enable if you use custom rewrite rules which do not match at least the beginning of the canonical URI. For example, a canonical URI of foo/ with custom rewrites for foo/bar.html would work, but attempts to rewrite bar/foo.html as foo/ would force a redirect to foo/ with this option enabled.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreFriendlyUrlsStrict(bool $value): SystemSetting
    {
        $this->setName('friendly_urls_strict');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Check for Duplicate URIs Across All Contexts ~
     * Select 'Yes' to make duplicate URI checks include all Contexts in the search. Otherwise, only the Context the Resource is being saved in is checked.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreGlobalDuplicateUriCheck(bool $value): SystemSetting
    {
        $this->setName('global_duplicate_uri_check');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Hide From Menus Default ~
     * Select 'Yes' to make all new resources hidden from menus by default.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreHidemenuDefault(bool $value): SystemSetting
    {
        $this->setName('hidemenu_default');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Show Inline Help Text for Fields ~
     * If 'Yes', then fields will display their help text directly below the field. If 'No', all fields will have tooltip-based help.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreInlineHelp(bool $value): SystemSetting
    {
        $this->setName('inline_help');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * URL Generation Scheme ~
     * URL generation scheme for tag [[~id]]. Available options <a href="http://api.modx.com/revolution/2.2/db_core_model_modx_modx.class.html#\modX::makeUrl()" target="_blank">here</a>.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreLinkTagScheme(string $value): SystemSetting
    {
        $this->setName('link_tag_scheme');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Locale ~
     * Set the locale for the system. Leave blank to use the default. See <a href="http://php.net/setlocale" target="_blank">the PHP documentation</a> for more information.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreLocale(string $value): SystemSetting
    {
        $this->setName('locale');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Lock Time-to-Live ~
     * The number of seconds a lock on a Resource will remain for if the user is inactive.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreLockTtl(string $value): SystemSetting
    {
        $this->setName('lock_ttl');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Logging Level ~
     * The default logging level; the lower the level, the fewer messages that are logged. Available options: 0 (FATAL), 1 (ERROR), 2 (WARN), 3 (INFO), and 4 (DEBUG).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreLogLevel(string $value): SystemSetting
    {
        $this->setName('log_level');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * setting_log_snippet_not_found ~
     * setting_log_snippet_not_found_desc
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreLogSnippetNotFound(bool $value): SystemSetting
    {
        $this->setName('log_snippet_not_found');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Logging Target ~
     * The default logging target where log messages are written. Available options: 'FILE', 'HTML', or 'ECHO'. Default is 'FILE' if not specified.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreLogTarget(string $value): SystemSetting
    {
        $this->setName('log_target');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Mail Charset ~
     * The default charset for emails, e.g., 'iso-8859-1' or 'utf-8'
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreMailCharset(string $value): SystemSetting
    {
        $this->setName('mail_charset');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Mail Encoding ~
     * Sets the Encoding of the message. Options for this are "8bit", "7bit", "binary", "base64", and "quoted-printable".
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreMailEncoding(string $value): SystemSetting
    {
        $this->setName('mail_encoding');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * SMTP Authentication ~
     * Sets SMTP authentication. Utilizes the mail_smtp_user and mail_smtp_pass settings.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreMailSmtpAuth(bool $value): SystemSetting
    {
        $this->setName('mail_smtp_auth');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * SMTP Helo Message ~
     * Sets the SMTP HELO of the message (Defaults to the hostname).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreMailSmtpHelo(string $value): SystemSetting
    {
        $this->setName('mail_smtp_helo');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * SMTP Hosts ~
     * Sets the SMTP hosts.  All hosts must be separated by a semicolon.  You can also specify a different port for each host by using this format: [hostname:port] (e.g., "smtp1.example.com:25;smtp2.example.com"). Hosts will be tried in order.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreMailSmtpHosts(string $value): SystemSetting
    {
        $this->setName('mail_smtp_hosts');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * SMTP Keep-Alive ~
     * Prevents the SMTP connection from being closed after each mail sending. Not recommended.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreMailSmtpKeepalive(bool $value): SystemSetting
    {
        $this->setName('mail_smtp_keepalive');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * SMTP Password ~
     * The password to authenticate to SMTP against.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreMailSmtpPass(string $value): SystemSetting
    {
        $this->setName('mail_smtp_pass');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * SMTP Port ~
     * Sets the default SMTP server port.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreMailSmtpPort(string $value): SystemSetting
    {
        $this->setName('mail_smtp_port');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * SMTP Connection Prefix ~
     * Sets connection prefix. Options are "", "ssl" or "tls"
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreMailSmtpPrefix(string $value): SystemSetting
    {
        $this->setName('mail_smtp_prefix');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * SMTP Single To ~
     * Provides the ability to have the TO field process individual emails, instead of sending to entire TO addresses.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreMailSmtpSingleTo(bool $value): SystemSetting
    {
        $this->setName('mail_smtp_single_to');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * SMTP Timeout ~
     * Sets the SMTP server timeout in seconds. This function will not work in win32 servers.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreMailSmtpTimeout(string $value): SystemSetting
    {
        $this->setName('mail_smtp_timeout');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * SMTP User ~
     * The user to authenticate to SMTP against.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreMailSmtpUser(string $value): SystemSetting
    {
        $this->setName('mail_smtp_user');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Use SMTP ~
     * If true, MODX will attempt to use SMTP in mail functions.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreMailUseSmtp(bool $value): SystemSetting
    {
        $this->setName('mail_use_smtp');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Main menu parent ~
     * The container used to pull all records for the main menu.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreMainNavParent(string $value): SystemSetting
    {
        $this->setName('main_nav_parent');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Manager Date Format ~
     * The format string, in PHP date() format, for the dates represented in the manager.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreManagerDateFormat(string $value): SystemSetting
    {
        $this->setName('manager_date_format');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Manager Text Direction ~
     * Choose the direction that the text will be rendered in the Manager, left to right or right to left.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreManagerDirection(string $value): SystemSetting
    {
        $this->setName('manager_direction');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Manager Favicon URL ~
     * If set, will load this URL as a favicon for the MODX manager. Must be a relative URL to the manager/ directory, or an absolute URL.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreManagerFaviconUrl(string $value): SystemSetting
    {
        $this->setName('manager_favicon_url');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Enable File Locking for Manager JS/CSS Cache ~
     * Cache file locking. Set to No if filesystem is NFS.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreManagerJsCacheFileLocking(bool $value): SystemSetting
    {
        $this->setName('manager_js_cache_file_locking');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Manager JS/CSS Compression Cache Age ~
     * Maximum age of browser cache of manager CSS/JS compression in seconds. After this period, the browser will send another conditional GET. Use a longer period for lower traffic.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreManagerJsCacheMaxAge(string $value): SystemSetting
    {
        $this->setName('manager_js_cache_max_age');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Manager JS/CSS Compression Document Root ~
     * If your server does not handle the DOCUMENT_ROOT server variable, set it explicitly here to enable the manager CSS/JS compression. Do not change this unless you know what you are doing.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreManagerJsDocumentRoot(string $value): SystemSetting
    {
        $this->setName('manager_js_document_root');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Enable zlib Output Compression for Manager JS/CSS ~
     * Whether or not to enable zlib output compression for compressed CSS/JS in the manager. Do not turn this on unless you are sure the PHP config variable zlib.output_compression can be set to 1. MODX recommends leaving it off.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreManagerJsZlibOutputCompression(bool $value): SystemSetting
    {
        $this->setName('manager_js_zlib_output_compression');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Manager HTML and XML Language Attribute ~
     * Enter the language code that best fits with your chosen manager language, this will ensure that the browser can present content in the best format for you.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreManagerLangAttribute(string $value): SystemSetting
    {
        $this->setName('manager_lang_attribute');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Manager Language ~
     * Select the language for the MODX Content Manager.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreManagerLanguage(string $value): SystemSetting
    {
        $this->setName('manager_language');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Alternate Manager Login URL ~
     * An alternate URL to send an unauthenticated user to when they need to login to the manager. The login form there must login the user to the "mgr" context to work.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreManagerLoginUrlAlternate(string $value): SystemSetting
    {
        $this->setName('manager_login_url_alternate');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Manager Theme ~
     * Select the Theme for the Content Manager.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreManagerTheme(string $value): SystemSetting
    {
        $this->setName('manager_theme');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Manager Time Format ~
     * The format string, in PHP date() format, for the time settings represented in the manager.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreManagerTimeFormat(string $value): SystemSetting
    {
        $this->setName('manager_time_format');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Show fullname in manager header  ~
     * If set to yes, the content of the "fullname" field will be shown in manager instead of "loginname"
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreManagerUseFullname(bool $value): SystemSetting
    {
        $this->setName('manager_use_fullname');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Week start ~
     * Define the day starting the week. Use 0 (or leave empty) for sunday, 1 for monday and so on...
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreManagerWeekStart(string $value): SystemSetting
    {
        $this->setName('manager_week_start');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Media Source icon ~
     * Indicate a CSS class to be used to display the Media Sources icons in the files tree. Defaults to "icon-folder-open-o"
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreMgrSourceIcon(string $value): SystemSetting
    {
        $this->setName('mgr_source_icon');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Context tree icon ~
     * Define a CSS class here to be used to display the context icon in the tree. You can use this setting on each context to customize the icon per context.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreMgrTreeIconContext(string $value): SystemSetting
    {
        $this->setName('mgr_tree_icon_context');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Media Browser Default Sort ~
     * The default sort method when using the Media Browser in the manager. Available values are: name, size, lastmod (last modified).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreModxBrowserDefaultSort(string $value): SystemSetting
    {
        $this->setName('modx_browser_default_sort');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Media Browser Default View Mode ~
     * The default view mode when using the Media Browser in the manager. Available values are: grid, list.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreModxBrowserDefaultViewmode(string $value): SystemSetting
    {
        $this->setName('modx_browser_default_viewmode');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Media Browser Tree Hide Files ~
     * If true the files inside folders are not displayed in the Media Browser source tree. Defaults to false.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreModxBrowserTreeHideFiles(bool $value): SystemSetting
    {
        $this->setName('modx_browser_tree_hide_files');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Media Browser Tree Hide Tooltips ~
     * If true, no image preview tooltips are shown when hovering over a file in the Media Browser tree. Defaults to true.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreModxBrowserTreeHideTooltips(bool $value): SystemSetting
    {
        $this->setName('modx_browser_tree_hide_tooltips');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Character encoding ~
     * Please select which character encoding you wish to use. Please note that MODX has been tested with a number of these encodings, but not all of them. For most languages, the default setting of UTF-8 is preferable.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreModxCharset(string $value): SystemSetting
    {
        $this->setName('modx_charset');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Delay Uncacheable Parsing ~
     * If disabled, uncacheable elements may have their output cached inside cacheable element content. Disable this ONLY if you are having problems with complex nested parsing which stopped working as expected.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreParserRecurseUncacheable(bool $value): SystemSetting
    {
        $this->setName('parser_recurse_uncacheable');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Password Auto-Generated Length ~
     * The length of the auto-generated password for a User.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePasswordGeneratedLength(string $value): SystemSetting
    {
        $this->setName('password_generated_length');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Minimum Password Length ~
     * The minimum length for a password for a User.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePasswordMinLength(string $value): SystemSetting
    {
        $this->setName('password_min_length');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Allow src Above Document Root ~
     * Indicates if the src path is allowed outside the document root. This is useful for multi-context deployments with multiple virtual hosts.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCorePhpthumbAllowSrcAboveDocroot(bool $value): SystemSetting
    {
        $this->setName('phpthumb_allow_src_above_docroot');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * phpThumb Max Cache Age ~
     * Delete cached thumbnails that have not been accessed in more than X days.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbCacheMaxage(string $value): SystemSetting
    {
        $this->setName('phpthumb_cache_maxage');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Max Cache Files ~
     * Delete least-recently-accessed thumbnails when cache has more than X files.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbCacheMaxfiles(string $value): SystemSetting
    {
        $this->setName('phpthumb_cache_maxfiles');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Max Cache Size ~
     * Delete least-recently-accessed thumbnails when cache grows bigger than X megabytes in size.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbCacheMaxsize(string $value): SystemSetting
    {
        $this->setName('phpthumb_cache_maxsize');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Cache Source Files ~
     * Whether or not to cache source files as they are loaded. Recommended to off.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCorePhpthumbCacheSourceEnabled(bool $value): SystemSetting
    {
        $this->setName('phpthumb_cache_source_enabled');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * PHPThumb Document Root ~
     * Set this if you are experiencing issues with the server variable DOCUMENT_ROOT, or getting errors with OutputThumbnail or !is_resource. Set it to the absolute document root path you would like to use. If this is empty, MODX will use the DOCUMENT_ROOT server variable.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbDocumentRoot(string $value): SystemSetting
    {
        $this->setName('phpthumb_document_root');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Error Background Color ~
     * A hex value, without the #, indicating a background color for phpThumb error output.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbErrorBgcolor(string $value): SystemSetting
    {
        $this->setName('phpthumb_error_bgcolor');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Error Font Size ~
     * An em value indicating a font size to use for text appearing in phpThumb error output.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbErrorFontsize(string $value): SystemSetting
    {
        $this->setName('phpthumb_error_fontsize');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Error Font Color ~
     * A hex value, without the #, indicating a font color for text appearing in phpThumb error output.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbErrorTextcolor(string $value): SystemSetting
    {
        $this->setName('phpthumb_error_textcolor');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Force Aspect Ratio ~
     * The default far setting for phpThumb when used in MODX. Defaults to C to force aspect ratio toward the center.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbFar(string $value): SystemSetting
    {
        $this->setName('phpthumb_far');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb ImageMagick Path ~
     * Optional. Set an alternative ImageMagick path here for generating thumbnails with phpThumb, if it is not in the PHP default.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbImagemagickPath(string $value): SystemSetting
    {
        $this->setName('phpthumb_imagemagick_path');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Hotlinking Disabled ~
     * Remote servers are allowed in the src parameter unless you disable hotlinking in phpThumb.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCorePhpthumbNohotlinkEnabled(bool $value): SystemSetting
    {
        $this->setName('phpthumb_nohotlink_enabled');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * phpThumb Hotlinking Erase Image ~
     * Indicates if an image generated from a remote server should be erased when not allowed.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCorePhpthumbNohotlinkEraseImage(bool $value): SystemSetting
    {
        $this->setName('phpthumb_nohotlink_erase_image');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * phpThumb Hotlinking Not Allowed Message ~
     * A message that is rendered instead of the thumbnail when a hotlinking attempt is rejected.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbNohotlinkTextMessage(string $value): SystemSetting
    {
        $this->setName('phpthumb_nohotlink_text_message');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Hotlinking Valid Domains ~
     * A comma-delimited list of hostnames that are valid in src URLs.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbNohotlinkValidDomains(string $value): SystemSetting
    {
        $this->setName('phpthumb_nohotlink_valid_domains');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Offsite Linking Disabled ~
     * Disables the ability for others to use phpThumb to render images on their own sites.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCorePhpthumbNooffsitelinkEnabled(bool $value): SystemSetting
    {
        $this->setName('phpthumb_nooffsitelink_enabled');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * phpThumb Offsite Linking Erase Image ~
     * Indicates if an image linked from a remote server should be erased when not allowed.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCorePhpthumbNooffsitelinkEraseImage(bool $value): SystemSetting
    {
        $this->setName('phpthumb_nooffsitelink_erase_image');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * phpThumb Offsite Linking Require Referrer ~
     * If enabled, any offsite linking attempts will be rejected without a valid referrer header.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCorePhpthumbNooffsitelinkRequireRefer(bool $value): SystemSetting
    {
        $this->setName('phpthumb_nooffsitelink_require_refer');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * phpThumb Offsite Linking Not Allowed Message ~
     * A message that is rendered instead of the thumbnail when an offsite linking attempt is rejected.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbNooffsitelinkTextMessage(string $value): SystemSetting
    {
        $this->setName('phpthumb_nooffsitelink_text_message');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Offsite Linking Valid Domains ~
     * A comma-delimited list of hostnames that are valid referrers for offsite linking.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbNooffsitelinkValidDomains(string $value): SystemSetting
    {
        $this->setName('phpthumb_nooffsitelink_valid_domains');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Offsite Linking Watermark Source ~
     * Optional. A valid file system path to a file to use as a watermark source when your images are rendered offsite by phpThumb.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbNooffsitelinkWatermarkSrc(string $value): SystemSetting
    {
        $this->setName('phpthumb_nooffsitelink_watermark_src');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * phpThumb Zoom-Crop ~
     * The default zc setting for phpThumb when used in MODX. Defaults to 0 to prevent zoom cropping.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePhpthumbZoomcrop(string $value): SystemSetting
    {
        $this->setName('phpthumb_zoomcrop');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Preserve Menu Index When Duplicating Resources ~
     * When duplicating Resources, the menu index order will also be preserved.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCorePreserveMenuindex(bool $value): SystemSetting
    {
        $this->setName('preserve_menuindex');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * ACL Targets to Load ~
     * Customize the ACL targets to load for MODX Users.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCorePrincipalTargets(string $value): SystemSetting
    {
        $this->setName('principal_targets');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Proxy Authentication Type ~
     * Supports either BASIC or NTLM.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreProxyAuthType(string $value): SystemSetting
    {
        $this->setName('proxy_auth_type');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Proxy Host ~
     * If your server is using a proxy, set the hostname here to enable MODX features that might need to use the proxy, such as Package Management.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreProxyHost(string $value): SystemSetting
    {
        $this->setName('proxy_host');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Proxy Password ~
     * The password required to authenticate to your proxy server.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreProxyPassword(string $value): SystemSetting
    {
        $this->setName('proxy_password');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Proxy Port ~
     * The port for your proxy server.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreProxyPort(string $value): SystemSetting
    {
        $this->setName('proxy_port');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Proxy Username ~
     * The username to authenticate against with your proxy server.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreProxyUsername(string $value): SystemSetting
    {
        $this->setName('proxy_username');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Published default ~
     * Select 'Yes' to make all new resources published by default.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCorePublishDefault(bool $value): SystemSetting
    {
        $this->setName('publish_default');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Resource path ~
     * Enter the physical path to the resource directory. This setting is usually automatically generated. If you're using IIS, however, MODX may not be able to work the path out on its own, causing the Resource Browser to show an error. In that case, you can enter the path to the images directory here (the path as you'd see it in Windows Explorer). <strong>NOTE:</strong> The resource directory must contain the subfolders images, files, flash and media in order for the resource browser to function correctly.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreRbBaseDir(string $value): SystemSetting
    {
        $this->setName('rb_base_dir');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Resource URL ~
     * Enter the virtual path to resource directory. This setting is usually automatically generated. If you're using IIS, however, MODX may not be able to work the URL out on its own, causing the Resource Browser to show an error. In that case, you can enter the URL to the images directory here (the URL as you'd enter it on Internet Explorer).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreRbBaseUrl(string $value): SystemSetting
    {
        $this->setName('rb_base_url');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Request Controller Filename ~
     * The filename of the main request controller from which MODX is loaded. Most users can leave this as index.php.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreRequestController(string $value): SystemSetting
    {
        $this->setName('request_controller');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Strict Request Method ~
     * If enabled, requests via the Request ID Parameter will be ignored with FURLs enabled, and those via Request Alias Parameter will be ignored without FURLs enabled.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreRequestMethodStrict(bool $value): SystemSetting
    {
        $this->setName('request_method_strict');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Request Alias Parameter ~
     * The name of the GET parameter to identify Resource aliases when redirecting with FURLs.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreRequestParamAlias(string $value): SystemSetting
    {
        $this->setName('request_param_alias');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Request ID Parameter ~
     * The name of the GET parameter to identify Resource IDs when not using FURLs.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreRequestParamId(string $value): SystemSetting
    {
        $this->setName('request_param_id');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Resolve hostnames ~
     * Do you want MODX to try to resolve your visitors' hostnames when they visit your site? Resolving hostnames may create some extra server load, although your visitors won't notice this in any way.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreResolveHostnames(bool $value): SystemSetting
    {
        $this->setName('resolve_hostnames');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Resource Tree Node Field ~
     * Specify the Resource field to use when rendering the nodes in the Resource Tree. Defaults to pagetitle, although any Resource field can be used, such as menutitle, alias, longtitle, etc.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreResourceTreeNodeName(string $value): SystemSetting
    {
        $this->setName('resource_tree_node_name');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Resource Tree Node Fallback Field ~
     * Specify the Resource field to use as fallback when rendering the nodes in the Resource Tree. This will be used if the resource has an empty value for the configured Resource Tree Node Field.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreResourceTreeNodeNameFallback(string $value): SystemSetting
    {
        $this->setName('resource_tree_node_name_fallback');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Resource Tree Tooltip Field ~
     * Specify the Resource field to use when rendering the nodes in the Resource Tree. Any Resource field can be used, such as menutitle, alias, longtitle, etc. If blank, will be the longtitle with a description underneath.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreResourceTreeNodeTooltip(string $value): SystemSetting
    {
        $this->setName('resource_tree_node_tooltip');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Richtext Default ~
     * Select 'Yes' to make all new Resources use the Richtext Editor by default.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreRichtextDefault(bool $value): SystemSetting
    {
        $this->setName('richtext_default');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Searchable Default ~
     * Select 'Yes' to make all new resources searchable by default.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreSearchDefault(bool $value): SystemSetting
    {
        $this->setName('search_default');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Send X-Powered-By Header ~
     * When enabled, MODX will send the "X-Powered-By" header to identify this site as built on MODX. This helps tracking global MODX usage through third party trackers inspecting your site. Because this makes it easier to identify what your site is built with, it might pose a slightly increased security risk if a vulnerability is found in MODX.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreSendPoweredbyHeader(bool $value): SystemSetting
    {
        $this->setName('send_poweredby_header');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Server offset time ~
     * Select the number of hours time difference between where you are and where the server is.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreServerOffsetTime(string $value): SystemSetting
    {
        $this->setName('server_offset_time');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Server type ~
     * If your site is on a https connection, please specify so here.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreServerProtocol(string $value): SystemSetting
    {
        $this->setName('server_protocol');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Session Cookie Domain ~
     * Use this setting to customize the session cookie domain. Leave blank to use the current domain.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreSessionCookieDomain(string $value): SystemSetting
    {
        $this->setName('session_cookie_domain');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Session Cookie HttpOnly ~
     * Use this setting to set the HttpOnly flag on session cookies.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreSessionCookieHttponly(bool $value): SystemSetting
    {
        $this->setName('session_cookie_httponly');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Session Cookie Lifetime ~
     * Use this setting to customize the session cookie lifetime in seconds.  This is used to set the lifetime of a client session cookie when they choose the 'remember me' option on login.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreSessionCookieLifetime(string $value): SystemSetting
    {
        $this->setName('session_cookie_lifetime');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Session Cookie Path ~
     * Use this setting to customize the cookie path for identifying site specific session cookies. Leave blank to use MODX_BASE_URL.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreSessionCookiePath(string $value): SystemSetting
    {
        $this->setName('session_cookie_path');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Session Cookie Secure ~
     * Enable this setting to use secure session cookies. This requires your site to be accessible over https, otherwise your site and/or manager will become inaccessible.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreSessionCookieSecure(bool $value): SystemSetting
    {
        $this->setName('session_cookie_secure');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Session Garbage Collector Max Lifetime ~
     * Allows customization of the session.gc_maxlifetime PHP ini setting when using 'modSessionHandler'.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreSessionGcMaxlifetime(string $value): SystemSetting
    {
        $this->setName('session_gc_maxlifetime');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Session Handler Class Name ~
     * For database managed sessions, use 'modSessionHandler'.  Leave this blank to use standard PHP session management.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreSessionHandlerClass(string $value): SystemSetting
    {
        $this->setName('session_handler_class');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Session Name ~
     * Use this setting to customize the session name used for the sessions in MODX. Leave blank to use the default PHP session name.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreSessionName(string $value): SystemSetting
    {
        $this->setName('session_name');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Set HTTP Headers ~
     * When enabled, MODX will attempt to set the HTTP headers for Resources.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreSetHeader(bool $value): SystemSetting
    {
        $this->setName('set_header');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Settings Distribution ~
     * The current installed distribution of MODX.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreSettingsDistro(string $value): SystemSetting
    {
        $this->setName('settings_distro');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Settings Version ~
     * The current installed version of MODX.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreSettingsVersion(string $value): SystemSetting
    {
        $this->setName('settings_version');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Show "Categories" Tabs Header with TVs ~
     * If "Yes", MODX will show the "Categories" header above the first category tab when editing TVs in a Resource.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreShowTvCategoriesHeader(bool $value): SystemSetting
    {
        $this->setName('show_tv_categories_header');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Sign-up email ~
     * Here you can set the message sent to your users when you create an account for them and let MODX send them an email containing their username and password. <br /><strong>Note:</strong> The following placeholders are replaced by the Content Manager when the message is sent: <br /><br />[[+sname]] - Name of your web site, <br />[[+saddr]] - Your web site email address, <br />[[+surl]] - Your site URL, <br />[[+uid]] - User's login name or id, <br />[[+pwd]] - User's password, <br />[[+ufn]] - User's full name. <br /><br /><strong>Leave the [[+uid]] and [[+pwd]] in the email, or else the username and password won't be sent in the mail and your users won't know their username or password!</strong>
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreSignupemailMessage(string $value): SystemSetting
    {
        $this->setName('signupemail_message');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Site name ~
     * Enter the name of your site here.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreSiteName(string $value): SystemSetting
    {
        $this->setName('site_name');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Site start ~
     * Enter the ID of the Resource you want to use as homepage here. <strong>NOTE: make sure this ID you enter belongs to an existing Resource, and that it has been published!</strong>
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreSiteStart(string $value): SystemSetting
    {
        $this->setName('site_start');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Site status ~
     * Select 'Yes' to publish your site on the web. If you select 'No', your visitors will see the 'Site unavailable message', and won't be able to browse the site.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreSiteStatus(bool $value): SystemSetting
    {
        $this->setName('site_status');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Site unavailable message ~
     * Message to show when the site is offline or if an error occurs. <strong>Note: This message will only be displayed if the Site unavailable page option is not set.</strong>
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreSiteUnavailableMessage(string $value): SystemSetting
    {
        $this->setName('site_unavailable_message');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Site unavailable page ~
     * Enter the ID of the Resource you want to use as an offline page here. <strong>NOTE: make sure this ID you enter belongs to an existing Resource, and that it has been published!</strong>
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreSiteUnavailablePage(string $value): SystemSetting
    {
        $this->setName('site_unavailable_page');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Rewrite browser paths? ~
     * If this is set to 'No', MODX will write file browser resource src's (images, files, flash, etc.) as absolute URLs. Relative URLs are helpful should you wish to move your MODX install, e.g., from a staging site to a production site. If you have no idea what this means, it's best just to leave it set to 'Yes'.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreStripImagePaths(bool $value): SystemSetting
    {
        $this->setName('strip_image_paths');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Merge Resource Fields in Symlinks ~
     * If set to Yes, will automatically merge non-empty fields with target resource when forwarding using Symlinks.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreSymlinkMergeFields(bool $value): SystemSetting
    {
        $this->setName('symlink_merge_fields');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Empty Cache default ~
     * Select 'Yes' to empty the cache after you save a resource by default.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreSyncsiteDefault(bool $value): SystemSetting
    {
        $this->setName('syncsite_default');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Show Descriptions in Top Menu ~
     * If set to 'No', MODX will hide the descriptions from top menu items in the manager.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreTopmenuShowDescriptions(bool $value): SystemSetting
    {
        $this->setName('topmenu_show_descriptions');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Resource Tree Default Sort Field ~
     * The default sort field for the Resource tree when loading the manager.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreTreeDefaultSort(string $value): SystemSetting
    {
        $this->setName('tree_default_sort');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Tree Root ID ~
     * Set this to a valid ID of a Resource to start the left Resource tree at below that node as the root. The user will only be able to see Resources that are children of the specified Resource.
     *
     * @param int $value
     *
     * @return $this
     */
    public function setCoreTreeRootId(int $value): SystemSetting
    {
        $this->setName('tree_root_id');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Move TVs Below Content ~
     * Set this to Yes to move Template Variables below the Content when editing Resources.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreTvsBelowContent(bool $value): SystemSetting
    {
        $this->setName('tvs_below_content');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Allow root ~
     * Do you want to allow your users to create new Resources in the root of the site?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreUdpermsAllowroot(bool $value): SystemSetting
    {
        $this->setName('udperms_allowroot');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Unauthorized page ~
     * Enter the ID of the Resource you want to send users to if they have requested a secured or unauthorized Resource. <strong>NOTE: Make sure the ID you enter belongs to an existing Resource, and that it has been published and is publicly accessible!</strong>
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreUnauthorizedPage(string $value): SystemSetting
    {
        $this->setName('unauthorized_page');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Uploadable File Types ~
     * Here you can enter a list of files that can be uploaded into 'assets/files/' using the Resource Manager. Please enter the extensions for the filetypes, seperated by commas.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreUploadFiles(string $value): SystemSetting
    {
        $this->setName('upload_files');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Uploadable Flash Types ~
     * Here you can enter a list of files that can be uploaded into 'assets/flash/' using the Resource Manager. Please enter the extensions for the flash types, separated by commas.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreUploadFlash(string $value): SystemSetting
    {
        $this->setName('upload_flash');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Uploadable Image Types ~
     * Here you can enter a list of files that can be uploaded into 'assets/images/' using the Resource Manager. Please enter the extensions for the image types, separated by commas.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreUploadImages(string $value): SystemSetting
    {
        $this->setName('upload_images');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Maximum upload size ~
     * Enter the maximum file size that can be uploaded via the file manager. Upload file size must be entered in bytes. <strong>NOTE: Large files can take a very long time to upload!</strong>
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreUploadMaxsize(string $value): SystemSetting
    {
        $this->setName('upload_maxsize');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Uploadable Media Types ~
     * Here you can enter a list of files that can be uploaded into 'assets/media/' using the Resource Manager. Please enter the extensions for the media types, separated by commas.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreUploadMedia(string $value): SystemSetting
    {
        $this->setName('upload_media');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Use Friendly Alias Path ~
     * Setting this option to 'yes' will display the full path to the Resource if the Resource has an alias. For example, if a Resource with an alias called 'child' is located inside a container Resource with an alias called 'parent', then the full alias path to the Resource will be displayed as '/parent/child.html'.<br /><strong>NOTE: When setting this option to 'Yes' (turning on alias paths), reference items (such as images, CSS, JavaScripts, etc.) use the absolute path, e.g., '/assets/images' as opposed to 'assets/images'. By doing so you will prevent the browser (or web server) from appending the relative path to the alias path.</strong>
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreUseAliasPath(bool $value): SystemSetting
    {
        $this->setName('use_alias_path');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Enable Resource Browser ~
     * Select yes to enable the resource browser. This will allow your users to browse and upload resources such as images, flash and media files on the server.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreUseBrowser(bool $value): SystemSetting
    {
        $this->setName('use_browser');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Use the context resource table ~
     * When enabled, context refreshes use the context_resource table. This enables you to programmatically have one resource in multiple contexts. If you do not use those multiple resource contexts via the API, you can set this to false. On large sites you will get a potential performance boost in the manager then.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreUseContextResourceTable(bool $value): SystemSetting
    {
        $this->setName('use_context_resource_table');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Enable Rich Text Editor ~
     * Do you want to enable the rich text editor? If you're more comfortable writing HTML, then you can turn the editor off using this setting. Note that this setting applies to all documents and all users!
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreUseEditor(bool $value): SystemSetting
    {
        $this->setName('use_editor');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Use Frozen Parent URIs ~
     * When enabled, the URI for children resources will be relative to the frozen URI of one of its parents, ignoring the aliases of resources high in the tree.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreUseFrozenParentUris(bool $value): SystemSetting
    {
        $this->setName('use_frozen_parent_uris');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Use Multibyte Extension ~
     * Set to true if you want to use the mbstring extension for multibyte characters in your MODX installation. Only set to true if you have the mbstring PHP extension installed.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreUseMultibyte(bool $value): SystemSetting
    {
        $this->setName('use_multibyte');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Use WebLink Target ~
     * Set to true if you want to have MODX link tags and makeUrl() generate links as the target URL for WebLinks. Otherwise, the internal MODX URL will be generated by link tags and the makeUrl() method.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreUseWeblinkTarget(bool $value): SystemSetting
    {
        $this->setName('use_weblink_target');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * User menu parent ~
     * The container used to pull all records for the user menu.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreUserNavParent(string $value): SystemSetting
    {
        $this->setName('user_nav_parent');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Web Reminder Email ~
     * Enter a message to be sent to your web users whenever they request a new password via email. The Content Manager will send an email containing their new password and activation information. <br /><strong>Note:</strong> The following placeholders are replaced by the Content Manager when the message is sent: <br /><br />[[+sname]] - Name of your web site, <br />[[+saddr]] - Your web site email address, <br />[[+surl]] - Your site URL, <br />[[+uid]] - User's login name or id, <br />[[+pwd]] - User's password, <br />[[+ufn]] - User's full name. <br /><br /><strong>Leave the [[+uid]] and [[+pwd]] in the email, or else the username and password won't be sent in the mail and your users won't know their username or password!</strong>
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreWebpwdreminderMessage(string $value): SystemSetting
    {
        $this->setName('webpwdreminder_message');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Web Signup email ~
     * Here you can set the message sent to your web users when you create a web account for them and let the Content Manager send them an email containing their username and password. <br /><strong>Note:</strong> The following placeholders are replaced by the Content Manager when the message is sent: <br /><br />[[+sname]] - Name of your web site, <br />[[+saddr]] - Your web site email address, <br />[[+surl]] - Your site URL, <br />[[+uid]] - User's login name or id, <br />[[+pwd]] - User's password, <br />[[+ufn]] - User's full name. <br /><br /><strong>Leave the [[+uid]] and [[+pwd]] in the email, or else the username and password won't be sent in the mail and your users won't know their username or password!</strong>
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreWebsignupemailMessage(string $value): SystemSetting
    {
        $this->setName('websignupemail_message');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Welcome Action ~
     * The default controller to load when accessing the manager when no controller is specified in the URL.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreWelcomeAction(string $value): SystemSetting
    {
        $this->setName('welcome_action');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Welcome Namespace ~
     * The namespace the Welcome Action belongs to.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreWelcomeNamespace(string $value): SystemSetting
    {
        $this->setName('welcome_namespace');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Show Welcome Screen ~
     * If set to true, the welcome screen will show on the next successful loading of the welcome page, and then not show after that.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreWelcomeScreen(bool $value): SystemSetting
    {
        $this->setName('welcome_screen');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }

    /**
     * Welcome Screen URL ~
     * The URL for the welcome screen that loads on first load of MODX Revolution.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreWelcomeScreenUrl(string $value): SystemSetting
    {
        $this->setName('welcome_screen_url');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Editor to use ~
     * Here you can select which Rich Text Editor you wish to use. You can download and install additional Rich Text Editors from Package Management.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreWhichEditor(string $value): SystemSetting
    {
        $this->setName('which_editor');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * Editor to use for Elements ~
     * Here you can select which Rich Text Editor you wish to use when editing Elements. You can download and install additional Rich Text Editors from Package Management.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCoreWhichElementEditor(string $value): SystemSetting
    {
        $this->setName('which_element_editor');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }


    /**
     * XHTML URLs ~
     * If set to true, all URLs generated by MODX will be XHTML-compliant, including encoding of the ampersand character.
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCoreXhtmlUrls(bool $value): SystemSetting
    {
        $this->setName('xhtml_urls');
        $this->loadObject(true);
        $this->setValue($value);

        return $this;
    }
}
