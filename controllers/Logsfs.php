<?php namespace Waka\SalesForce\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use System\Classes\SettingsManager;
use Waka\SalesForce\Classes\SalesForceImport;
use Waka\SalesForce\Models\Settings;

/**
 * Logsf Back-end Controller
 */
class Logsfs extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController',
        'Backend.Behaviors.RelationController'

    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    public $sfpopupWidget;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('October.System', 'system', 'settings');
        SettingsManager::setContext('Waka.SalesForce', 'logsfs');
        $this->sfpopupWidget = $this->createSfPopupWidget();
    }

    // public function isSfAuthorized()
    // {
    //     //trace_log("Est ce qu'il est auth");
    //     try {
    //         $forrest = \Forrest::identity();
    //         if ($forrest) {
    //             //trace_log($forrest);
    //             return true;
    //         } else {
    //             return false;
    //         }
    //     } catch (\Exception $e) {
    //         return false;
    //     }

    // }

    public function isSfOauthAuthenticate()
    {
        $forrest = null;
        try {
            $forrest = \Forrest::authenticate();
        } catch (\Exception $e) {
            return false;
        }
        try {
            $identity = \Forrest::identity();
        } catch (\Exception $e) {
            return false;
        }
        if ($identity) {
            return true;
        } else {
            return false;
        }
    }

    public function isSfWuAuthenticate()
    {

        try {
            $forrest = \Forrest::refresh();
        } catch (\Exception $e) {
            //trace_log("erreur");
            return false;
        }
        if ($forrest) {
            return true;
        } else {
            return $this->tryToConnect();
        }
    }

    public function tryToConnect()
    {
        try {
            $forrest = \Forrest::authenticate();
        } catch (\Exception $e) {
            return false;
        }
        if ($forrest) {
            //trace_log(get_class($forrest));
            return true;
        } else {
            return $this->tryToConnect();
        }
    }

    public function onManualImport()
    {
        $imports = Settings::get('sf_active_imports');
        foreach ($imports as $import) {
            SalesForceImport::find($import)->executeQuery();
        }
        return \Redirect::refresh();
    }

    public function onCallSfImportPopup()
    {
        $sf = new \Waka\SalesForce\Classes\SalesForceConfig();
        $this->sfpopupWidget->getField('active_imports')->options = $sf->lists('import');
        $this->vars['sfpopupWidget'] = $this->sfpopupWidget;
        return $this->makePartial('$/waka/salesforce/controllers/logsfs/_popup_config.htm');
    }

    public function onSfImportValidation()
    {
        $options = post('sfBehavior_array');
        $imports = $options['active_imports'];
        foreach ($imports as $import) {
            $datas = [
                'productorId' => $import,
                'options' => $options
            ];
            $job = new \Waka\SalesForce\Jobs\ImportSf($datas);
            $jobManager = \App::make('Waka\Wakajob\Classes\JobManager');
            $jobManager->dispatch($job, "Chargement SalesForce ".$import);
            //SalesForceImport::find($import)->changeMainDate($options['main_date'])->executeQuery();
        }
    }

    public function onCallExportValidation()
    {
    }

    public function onExportValidation()
    {
    }

    public function createSfPopupWidget()
    {
        $config = $this->makeConfig('$/waka/salesforce/controllers/logsfs/config_popup.yaml');
        $config->alias = 'sfBehaviorformWidget';
        $config->arrayName = 'sfBehavior_array';
        $config->model = new \Model();
        $widget = $this->makeWidget('Backend\Widgets\Form', $config);
        $widget->bindToController();
        return $widget;
    }

    public function getFalseResult()
    {
        $array1 = "Array
        (
            [attributes] => Array
                (
                    [type] => Filleul__c
                    [url] => /services/data/v50.0/sobjects/Filleul__c/a0G0W00001gqYsQUAU
                )

            [Id] => a0G0W00001gqYsQUAU
            [Name] => F-107731 RAHLAN H'Ka
            [Parrain__c] => 001d000000JDnnNAAT
            [Pays__c] => Vietnam
            [Programme__r] => Array
                (
                    [attributes] => Array
                        (
                            [type] => Programme__c
                            [url] => /services/data/v50.0/sobjects/Programme__c/a0Qd00000027OXnEAM
                        )

                    [Comit_de_traduction__r] => Array
                        (
                            [attributes] => Array
                                (
                                    [type] => Comitetrad__c
                                    [url] => /services/data/v50.0/sobjects/Comitetrad__c/a0td00000012HqCAAU
                                )

                            [Id] => a0td00000012HqCAAU
                        )

                )

        )";
        $array2 = "Array
        (
            [attributes] => Array
                (
                    [type] => Filleul__c
                    [url] => /services/data/v50.0/sobjects/Filleul__c/a0G0W00001pUr1ZUAS
                )

            [Id] => a0G0W00001pUr1ZUAS
            [Name] => F-112334 RMAH H’ NHIÊN
            [Parrain__c] => 001d000000JDiWlAAL
            [Pays__c] => Vietnam
            [Programme__r] => Array
                (
                    [attributes] => Array
                        (
                            [type] => Programme__c
                            [url] => /services/data/v50.0/sobjects/Programme__c/a0Qd00000027OXnEAM
                        )

                    [Comit_de_traduction__r] => Array
                        (
                            [attributes] => Array
                                (
                                    [type] => Comitetrad__c
                                    [url] => /services/data/v50.0/sobjects/Comitetrad__c/a0td00000012HqCAAU
                                )

                            [Id] => a0td00000012HqCAAU
                        )

                )

        )";
        $array3 = "Array
        (
            [attributes] => Array
                (
                    [type] => Filleul__c
                    [url] => /services/data/v50.0/sobjects/Filleul__c/a0G0W00001pUr36UAC
                )

            [Id] => a0G0W00001pUr36UAC
            [Name] => F-112335 KPĂ H’ MAI
            [Parrain__c] => 001d0000029YPauAAG
            [Pays__c] => Vietnam
            [Programme__r] => Array
                (
                    [attributes] => Array
                        (
                            [type] => Programme__c
                            [url] => /services/data/v50.0/sobjects/Programme__c/a0Qd00000027OXnEAM
                        )

                    [Comit_de_traduction__r] => Array
                        (
                            [attributes] => Array
                                (
                                    [type] => Comitetrad__c
                                    [url] => /services/data/v50.0/sobjects/Comitetrad__c/a0td00000012HqCAAU
                                )

                            [Id] => a0td00000012HqCAAU
                        )

                )

        )";
        $results = [];
        $results[0] = \Waka\Utils\Classes\ReverseLogArray::print_r_reverse(trim($array1));
        $results[1] = \Waka\Utils\Classes\ReverseLogArray::print_r_reverse(trim($array2));
        $results[2] = \Waka\Utils\Classes\ReverseLogArray::print_r_reverse(trim($array3));
        return $results;
    }
}
