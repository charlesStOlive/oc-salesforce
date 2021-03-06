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
}
