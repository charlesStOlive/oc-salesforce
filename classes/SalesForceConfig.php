<?php

namespace Waka\SalesForce\Classes;

use Config;
use October\Rain\Support\Collection;
use Waka\Wconfig\Models\Settings;
use Yaml;

class SalesForceConfig
{
    public $salesForceConfig;

    public function __construct()
    {
        $this->salesForceConfig = new Collection($this->getSrConfig());
    }

    public function lists($type = null)
    {
        $lists = [];
        $configs = $this->salesForceConfig;
        if ($type) {
            $configs = $configs->where('type', $type);
        }
        foreach ($configs as $key => $config) {
            $lists[$key] = $config['name'];
        }
        return $lists;
    }

    public function getOneConfig($searchedKey)
    {
        foreach ($this->salesForceConfig as $key => $config) {
            if ($key == $searchedKey) {
                return $config;
            }
        }
        return null;
    }

    public function execOne(String $searchedKey)
    {

        foreach ($this->salesForceConfig as $key => $config) {
            if ($key == $searchedKey) {
                $import = new SalesForceImport($config);
                $import->executeQuery();

            }
        }

    }

    public function execImports()
    {
        $imports = Settings::get('sf_active_imports');
        foreach ($imports as $key => $import) {
            $import = $this->execOne($import);
        }

    }

    public function getSrConfig()
    {
        $configYaml = Config::get('waka.wconfig::salesForce.src');
        if ($configYaml) {
            return Yaml::parseFile(plugins_path() . $configYaml);
        } else {
            return Yaml::parseFile(plugins_path() . '/waka/wconfig/config/salesforce.yaml');
        }

    }
}
