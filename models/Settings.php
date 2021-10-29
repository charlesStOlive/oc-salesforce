<?php

namespace Waka\SalesForce\Models;

use Winter\Storm\Database\Model;

class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    // A unique code
    public $settingsCode = 'waka_salesforce_settings';

    // Reference to field configuration
    public $settingsFields = 'fields.yaml';

    public function listImports() {
        $sf = new \Waka\SalesForce\Classes\SalesForceConfig();
        return $sf->lists('import');
    }
    public function listUsers()
    {
        $users = \Backend\Models\User::get();
        $array = [];
        foreach ($users as $user) {
            $array[$user->id] = $user->fullName;
        }
        return $array;
    }
}
