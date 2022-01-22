<?php

namespace Waka\SalesForce\Classes;

use Config;
use Winter\Storm\Support\Collection;
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

    public function getSrConfig()
    {
        try {
            $configYaml = Config::get('wcli.wconfig::salesForce.src');
            if ($configYaml) {
                return Yaml::parseFile(plugins_path() . $configYaml);
            } else {
                return Yaml::parseFile(plugins_path() . '/wcli/wconfig/config/salesforce.yaml');
            }
        } catch( \Exception $e) {
            throw new \SystemException('Il manque le fichier de config salesforce dans le repertoire config de wconfig');
        }
    }
}
