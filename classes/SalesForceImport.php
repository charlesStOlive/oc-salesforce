<?php

namespace Waka\SalesForce\Classes;

use Carbon\Carbon;
use Waka\SalesForce\Models\Logsf;
use Waka\SalesForce\Models\LogsfError;
use Wcli\Wconfig\Models\Settings;

class SalesForceImport
{
    public $mappedRows;
    public $logsf;
    public $doLog;

    public static function find($key)
    {
        $salesForceConfgs = self::getSrConfig();
        $config = $salesForceConfgs[$key] ?? null;
        if (!$config) {
            throw new \ApplicationException("La clef " . $key . " n'existe pas sans wconfig->salesforce.yaml ");
        } else {
            self::$config = $salesForceConfgs[$key];
            self::instanciateValues();
        }
        return new self;
    }

    public function addOptions($array)
    {
        foreach ($array as $key => $value) {
            $this->config[$key] = $value;
        }
        return $this;
    }

    public function changeMainDate($dateObj)
    {
        $mainDateConfig = $this->config['vars']['main_date'] ?? false;
        if ($mainDateConfig) {
            $this->config['vars']['main_date'] = $dateObj;
        }
        return $this;
    }

    public function checkAndConfigImport(array $config)
    {
        if (!$this->config['query'] ?? false) {
            throw new \ApplicationException("Erreur : le query n'est pas renseigné");
        }
        if (!$this->config['model'] ?? false) {
            throw new \ApplicationException("Erreur : le model n'est pas renseigné");
        }
        if (!$this->config['name'] ?? false) {
            throw new \ApplicationException("Erreur : le name n'est pas renseigné");
        }
        //Gestion des deux types de configurations.
        $query_model = $this->config['query_model'] ?? false;
        $query_fnc = $this->config['query_fnc'] ?? false;
        $cconfigOk = false;
        if ($query_model && $query_fnc) {
            $configOk = true;
        } elseif ($this->config['mapping'] ?? false) {
            $configOk = true;
        }
        if (!$cconfigOk) {
            throw new \ApplicationException("Erreur : Soit query_model & query_fnc doit exister soit mapping");
        }
        if ($config['log'] ?? true) {
            $this->doLog = true;
        }
        $vars = $this->config['vars'] ?? null;
    }

    public function getSrConfig()
    {
        $configYaml = Config::get('wcli.wconfig::salesForce.src');
        if ($configYaml) {
            return Yaml::parseFile(plugins_path() . $configYaml);
        } else {
            return Yaml::parseFile(plugins_path() . '/wcli/wconfig/config/salesforce.yaml');
        }

    }

    public function prepareQuery()
    {
        $query = $this->config['query'];
        if ($config['vars'] ?? false) {
            $vars = $this->prepareVars($config['vars']);
            $query = \Twig::parse($query, $vars);
        }
        return $query;
    }

    public function prepareVars($vars)
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
                    $vars[$key] = $this->getLastLogDate()->format($format);
                    break;
                case "perso":
                    $vars[$key] = Carbon::parse($var['date'])->format($format);
                    break;
                default:
                    $vars[$key] = $var;
            }
            return $vars;
        }
    }

    public function executeQuery()
    {
        $this->checkAndConfigImport();
        $query = $this->prepareQuery();
        $this->logsf = $this->createLog($query);
        $this->mappedRows = 0;
        $this->sendQuery($query);
        $this->updateAndCloseLog($logsf);
    }
    public function sendQuery($query = null, $next = null)
    {
        if (!$next && $query) {
            $result = \Forrest::query($query);
            $this->updateLog('sf_total_size', $result['totalSize'] ?? null);
            if ($this->config['query_model'] ?? false) {
                $classImport = $this->config['query_model'];
                $this->mappedRows += $classImport->{$this->config['query_fnc']}($result['records']);
            } else {
                $this->mapResults($result['records']);
            }
            $next = $result['nextRecordsUrl'] ?? null;
            $this->sendQuery(null, $next);
        } else if ($next) {
            $result = \Forrest::next($next);
            if ($this->config['query_model'] ?? false) {
                $classImport = $this->config['query_model'];
                $this->mappedRows += $classImport->{$this->config['query_fnc']}($result['records']);
            } else {
                $this->mapResults($result['records']);
            }
            $next = $result['nextRecordsUrl'] ?? null;
            $this->sendQuery(null, $next);
        } else {
            //trace_log('Terminé plus de next');
        }
    }

    public function mapResults($rows)
    {
        foreach ($rows as $row) {
            $mappedRow = $this->mapResult($row);
            $model = $this->config['model'];
            if (method_exists($model, 'withTrashed')) {
                $model = $this->model::withTrashed();
                $mappedRow['deleted_at'] = null;
                $id = array_shift($mappedRow);
                try {
                    $model->updateOrCreate(
                        ['id' => $id],
                        $mappedRow
                    );
                    $this->mappedRows++;
                } catch (\Exception $e) {
                    $logsfError = new LogsfError(['error' => $id . " : " . $e->getMessage()]);
                    $this->logsf->logsfErrors()->add($logsfError);
                }
            } else {
                $id = array_shift($mappedRow);
                try {
                    $model::updateOrCreate(
                        ['id' => $id],
                        $mappedRow
                    );
                    $this->mappedRows++;
                } catch (\Exception $e) {
                    $logsfError = new LogsfError(['error' => $id . " : " . $e->getMessage()]);
                    $this->logsf->logsfErrors()->add($logsfError);
                }
            }

        }
    }

    public function mapResult($row)
    {
        $row = array_dot($row);
        $finalResult = [];
        foreach ($row as $column => $value) {
            //trace_log($column);
            $finalKey = $this->config['mapping'][$column] ?? null;
            if (!$finalKey) {
                continue;
            }
            $finalResult[$finalKey] = $this->transform($finalKey, $value);
        }
        return $finalResult;
    }

    public function transform($key, $value)
    {
        $transform = $this->config['transform'][$key] ?? null;
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
            case "other":
                return $value;
        }
        return $value;
    }

    /**
     * Travail sur lastLog pour preparer les requetes last_log : va importer uniquement les lignes MAJ depuis un dernier import réussi.
     */

    public function getLastLogDate()
    {
        $lastImport = Logsf::where('name', $this->config['name'])->where('is_ended', true)->orderBy('created_at', 'desc')->first();
        if ($lastImport) {
            return $lastImport->created_at;
        } else {
            $date = Settings::get('sf_oldest_date');
            return Carbon::parse($date);
        }

    }

    /**
     * Creattion des logs
     */

    public function createLog()
    {
        if (!$this->doLog) {
            return null;
        }
        $logsf = Logsf::create([
            'name' => $this->config['name'],
            'start_at' => $this->getLastLogDate(),
        ]);
        return $logsf;
    }
    public function updateLog($var, $value, $logsf = null)
    {
        if (!$this->doLog) {
            return null;
        }
        if (!$logsf) {
            $logsf = $this->$logsf;
        }
        if (!$logSf) {
            throw new \ApplicationException('Erreur au niveau des logsf');
        }
        $logsf->update([
            $var => $value,
        ]);
    }
    public function updateAndCloseLog($logSf)
    {
        if (!$this->doLog) {
            return null;
        }
        $logsf->update([
            'is_ended' => true,
            'ended_at' => Carbon::now(),
            'nb_updated_rows' => $this->mappedRows,
        ]);
        return $logsf;
    }

}
