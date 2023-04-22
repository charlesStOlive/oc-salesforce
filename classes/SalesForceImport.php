<?php

namespace Waka\SalesForce\Classes;

use Carbon\Carbon;
use Waka\SalesForce\Models\Logsf;
use Waka\SalesForce\Models\LogsfError;
use Waka\SalesForce\Models\Settings;

class SalesForceImport
{
    public $mappedRows;
    public $logsf;
    public $doLog;
    public static $config;

    public static function find($key)
    {
        $salesForceConfgs = self::getSrConfig();
        $config = $salesForceConfgs[$key] ?? null;
        if (!$config) {
            throw new \ApplicationException("La clef " . $key . " n'existe pas dans wconfig->salesforce.yaml ");
        } else {
            self::$config = $salesForceConfgs[$key];
            //trace_log(self::$config);
        }
        return new self;
    }

    public function getConfig($key)
    {
        return self::$config[$key] ?? null;
    }
    public function setConfig($key, $value)
    {
        self::$config[$key] = $value;
    }

    public function addOptions($array)
    {
        foreach ($array as $key => $value) {
            $this->setConfig($key, $value);
        }
        return $this;
    }

    public function changeMainDate($date)
    {
        if($date) {
            $vars = $this->getConfig('vars');
            foreach ($vars as $key => $var) {
                if ($key == 'main_date') {
                    $vars[$key]['mode'] = 'perso';
                    $vars[$key]['date'] = $date;
                }
            }
            $this->setConfig('vars', $vars);
        }
        return $this;
    }

    private function checkAndConfigImport()
    {
        if (!$this->getConfig('query')) {
            throw new \ApplicationException("Erreur : le query n'est pas renseigné");
        }
        
        if (!$this->getConfig('name')) {
            throw new \ApplicationException("Erreur : le name n'est pas renseigné");
        }
        //Gestion des deux types de configurations.
        $query_model = $this->getConfig('query_model');
        $query_fnc = $this->getConfig('query_fnc');
        //trace_log($query_model);
        //trace_log($query_fnc);

        $cconfigOk = false;
        if ($query_model && $query_fnc) {
            //trace_log("C 'est ok");
            $configOk = true;
        } elseif ($this->getConfig('mapping')) {
            if (!$this->getConfig('model')) {
                throw new \ApplicationException("Erreur : le model n'est pas renseigné");
            }
            $configOk = true;
        }
        if (!$configOk) {
            throw new \ApplicationException("Erreur : Soit query_model & query_fnc doit exister soit mapping");
        }
        $this->doLog = true;
        return $configOk;
    }

    public static function getSrConfig()
    {
        $configYaml = \Config::get('wcli.wconfig::salesForce.src');
        if ($configYaml) {
            return \Yaml::parseFile(plugins_path() . $configYaml);
        } else {
            return \Yaml::parseFile(plugins_path() . '/wcli/wconfig/config/salesforce.yaml');
        }
    }

    private function prepareQuery()
    {
        $query = $this->getConfig('query');
        $vars = $this->getConfig('vars');
        if ($vars) {
            $vars = $this->prepareVars($vars);
            //trace_log($vars);
            $query = \Twig::parse($query, $vars);
        }
        return $query;
    }

    private function prepareVars($vars)
    {
        foreach ($vars as $key => $var) {
            $optformat = $var['format'] ?? 'time';
            $format = 'Y-m-d\TH:i:s.uP';
            if ($optformat == 'date') {
                $format = 'Y-m-d';
            }
            switch ($var['mode']) {
                case "d_1":
                    $vars[$key] = Carbon::now()->subDay()->format($format);
                    break;
                case "m_1":
                    $vars[$key] = Carbon::now()->subMonth()->format($format);
                    break;
                case "y_1":
                    $vars[$key] = Carbon::now()->subYear()->format($format);
                    break;
                case "l_log":
                    //trace_log("last log date");
                    //trace_log($this->getLastLogDate());
                    //trace_log($this->getLastLogDate()->format($format));
                    $vars[$key] = $this->getLastLogDate()->format($format);
                    //trace_log("fin last log");
                    break;
                case "perso":
                    $vars[$key] = Carbon::parse($var['date'])->format($format);
                    break;
                default:
                    $vars[$key] = $var;
            }
        }
        return $vars;
    }

    public function executeQuery()
    {
        $configOk = $this->checkAndConfigImport();
        if(!$configOk) {
            throw new \ApplicationException("Erreur Inconnu dans la config");
        }
        $query = $this->prepareQuery();
        $this->logsf = $this->createLog($query);
        $this->mappedRows = 0;
        return $this->sendFirstQuery($query); 
    }
    public function sendFirstQuery($query = null) {
        $auth = $this->authenticate();
        if(!$auth) {
            return 'error';
        }
        //trace_log('executeQuery');
        $result = \Forrest::query($query);
        $totalSize = $result['totalSize'] ?? null;
        $next = $result['nextRecordsUrl'] ?? null;
        $records = $result['records'];
        $this->updateLog('sf_total_size', $totalSize);
        return [
            'totalSize' => $totalSize,
            'next' => $next,
            'records' => $records
        ];
    }
    public function sendNextQuery($next) {
        $auth = $this->authenticate();
        if(!$auth) {
            return 'error';
        }
        $result = \Forrest::next($next);
        $records = $result['records'] ?? null;
        if($records) {
            $this->handleRequest($records);
        }
        $next = $result['nextRecordsUrl'] ?? null;
        return $next;
    }

    public function sendAllNextQuery($next) {
        $auth = $this->authenticate();
        if(!$auth) {
            return 'error';
        }
        $result = \Forrest::next($next);
        $records = $result['records'] ?? null;
        if($records) {
            $this->handleRequest($records);
        }

        $newNext = $result['nextRecordsUrl'] ?? null;
        //trace_log("newNext : ".$newNext);
        if($newNext) {
            //trace_log("newNext");
            $this->sendAllNextQuery($newNext);
        } else {
            //trace_log('updateAndCloseLog');
            $this->updateAndCloseLog($this->logsf);
        }
        
    }
    

    public function handleRequest($records) {
        if ($this->getConfig('query_model') && $this->getConfig('query_fnc')) {
            //trace_log('handleRequest 227 traitement manuel');
            $classImport = $this->getConfig('query_model');
            $classImport = new $classImport;
            $fnc = $this->getConfig('query_fnc');
            $this->mappedRows += $classImport->{$fnc}($records, $this->logsf );
        } elseif($this->getConfig('mapping')) {
            $this->mapResults($records);
        } else {
            throw new \ApplicationException("mapping pas configuré dans congif salesforce");
        }
    }

    private function mapResults($rows)
    {
        //trace_log('mapResults');
        foreach ($rows as $row) {
            $mappedRow = $this->mapResult($row);
            $model = $this->getConfig('model');
            // if (method_exists($model, 'withTrashed')) {
            //     $model = $model::withTrashed();
            //     $mappedRow['deleted_at'] = null;
            // }
            $id = array_shift($mappedRow);
            try {
                $model::updateOrCreate(
                    ['id' => $id],
                    $mappedRow
                );
                $this->mappedRows++;
                //trace_log('row ok');
            } catch (\Exception $e) {
                //trace_log($e->getMessage());
                $logsfError = new LogsfError(['error' => $id . " : " . $e->getMessage()]);
                $this->logsf->logsfErrors()->add($logsfError);
            }
            //trace_log('fin du mapResults');
        }
    }

    private function mapResult($row)
    {
        $row = array_dot($row);
        $finalResult = [];
        foreach ($row as $column => $value) {
            //trace_log($column);
            $finalKey = $this->getConfig('mapping')[$column] ?? null;
            if (!$finalKey) {
                continue;
            }
            $finalResult[$finalKey] = $this->transform($finalKey, $value);
        }
        return $finalResult;
    }

    private function transform($key, $value)
    {
        $transform = $this->getConfig('transform')[$key] ?? null;
        if (!$transform) {
            return $value;
        }
        switch ($transform['type']) {
            case "relation":
                $model = $transform['model'];
                $column = $transform['column'];
                if (!$model || !$column) {
                    throw new \ApplicationException('SalesForce config transform error');
                }
                $model = $model::where($column, $value)->first();
                if ($model) {
                    return $model->id;
                } else {
                    return null;
                }
                break;
            case "compare":
                $comparisons = $transform['comparisons'];
                $resultFind = false;
                //trace_log('valeur recherché : '.$value);
                if(!$value) {
                    return $transform['default'];
                }
                foreach($comparisons as $result=>$searchedArray) {
                    //trace_log($searchedArray);
                    if(in_array($value, $searchedArray)) {
                        $resultFind = true;
                        //trace_log('trouvé : '.$result);
                        return $result;
                    }
                }
                if(!$resultFind) {
                    //trace_log('pas trouvé');
                    return $transform['default'];
                }
            case "other":
                return $value;
        }
        return $value;
    }

    /**
     * Travail sur lastLog pour preparer les requetes last_log : va importer uniquement les lignes MAJ depuis un dernier import réussi.
     */

    private function getLastLogDate()
    {
        $lastImport = Logsf::where('name', $this->getConfig('name'))->where('is_ended', true)->orderBy('created_at', 'desc')->first();
        if ($lastImport) {
            //trace_log("last import");
            //trace_log($lastImport->created_at);
            return $lastImport->created_at;
        } else {
            //trace_log("PAS DE last import");
            $date = Settings::get('sf_oldest_date');
            //trace_log(Carbon::parse($date));
            return Carbon::parse($date);
        }
    }

    private function authenticate() {
        try {
            //trace_log('je tente l authentification');
            \Forrest::authenticate();
            return true;
        } catch (\Exception $e) {
            $this->updateAndCloseErrorLog();
            return false;
        }
    }

    /**
     * Creattion des logs
     */

    public function createLog($query)
    {
        // if (!$this->doLog) {
        //     return null;
        // }
        $logsf = Logsf::create([
            'name' => $this->getConfig('name'),
            'start_at' => $this->getLastLogDate(),
            'query' => $query,
        ]);
        return $logsf;
    }
    public function updateLog($var, $value, $logsf = null)
    {
        // if (!$this->doLog) {
        //     return null;
        // }
        if (!$logsf) {
            $logsf = $this->logsf;
        }
        if (!$logsf) {
            throw new \ApplicationException('Erreur au niveau des logsf');
        }
        $logsf->update([
            $var => $value,
        ]);
    }
    public function updateAndCloseLog()
    {
        $this->logsf->update([
            'is_ended' => false,
            'ended_at' => Carbon::now(),
            'nb_updated_rows' => $this->mappedRows,
        ]);
        return $this->logsf;
    }
    public function updateAndCloseErrorLog()
    {
        // if (!$this->doLog) {
        //     return null;
        // }
        $this->logsf->update([
            'is_ended' => true,
            'ended_at' => Carbon::now(),
            'nb_updated_rows' => $this->mappedRows,
        ]);
        return $this->logsf;
    }
}
