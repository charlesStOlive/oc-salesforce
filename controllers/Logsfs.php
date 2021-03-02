<?php namespace Waka\SalesForce\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use System\Classes\SettingsManager;

/**
 * Logsf Back-end Controller
 */
class Logsfs extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController',
        'Backend.Behaviors.RelationController',

    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('October.System', 'system', 'settings');
        SettingsManager::setContext('Waka.SalesForce', 'logsfs');
    }

    // public function isSfAuthorized()
    // {
    //     trace_log("Est ce qu'il est auth");
    //     try {
    //         $forrest = \Forrest::identity();
    //         if ($forrest) {
    //             trace_log($forrest);
    //             return true;
    //         } else {
    //             return false;
    //         }
    //     } catch (\Exception $e) {
    //         return false;
    //     }

    // }

    public function isSfAuthenticate()
    {
        trace_log("Est ce qu'il est auth");
        try {
            $forrest = \Forrest::identity();
        } catch (\Exception $e) {
            trace_log("erreur");
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
            trace_log(get_class($forrest));
            return true;
        } else {
            return $this->tryToConnect();
        }
    }

    public function onManualImport()
    {
        $accounts = null;
        $sf = new \Waka\SalesForce\Classes\SalesForceConfig();
        $sf->execImports();
        // return \Redirect::refresh();

        // $filleulCOnfig = $sf->getOneConfig('importParrains');
        // $sfImport = new \Waka\SalesForce\Classes\SalesForceImport($filleulCOnfig);
        // $sfImport->executeQuery();
        //$falseResult = $this->getFalseResult();
        // trace_log($sfImport->prepareQuery());
        //$sfImport->mapResults($falseResult);
        // trace_log("---Real---");

    }

    public function onReinitaliseImport()
    {
        $accounts = null;
        $sf = new \Waka\SalesForce\Classes\SalesForceConfig();
        $sf->execImports();
        // return \Redirect::refresh();
        //$falseResult = $this->getFalseResult();
        // trace_log($sfImport->prepareQuery());
        //$sfImport->mapResults($falseResult);
        // trace_log("---Real---");

    }

    public function onCallImportPopup()
    {

    }

    public function onImportValidation()
    {

    }

    public function onCallExportValidation()
    {

    }

    public function onExportValidation()
    {

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
