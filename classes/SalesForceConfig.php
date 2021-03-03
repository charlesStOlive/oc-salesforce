<?php

namespace Waka\SalesForce\Classes;

use Config;
use October\Rain\Support\Collection;
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

    // public function execOne(String $searchedKey)
    // {
    //     SalesForceImport::find($searchedKey)->executeQuery();
    // }

    // public function execImports()
    // {
    //     $imports = Settings::get('sf_active_imports');
    //     foreach ($imports as $key => $import) {
    //         SalesForceImport::find($key)->executeQuery();
    //     }

    // }

    public function getSrConfig()
    {
        $configYaml = Config::get('wcli.wconfig::salesForce.src');
        if ($configYaml) {
            return Yaml::parseFile(plugins_path() . $configYaml);
        } else {
            return Yaml::parseFile(plugins_path() . '/wcli/wconfig/config/salesforce.yaml');
        }

    }
}
