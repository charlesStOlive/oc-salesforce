<?php

namespace Waka\SalesForce\Classes;

use Carbon\Carbon;
use Waka\SalesForce\Models\Logsf;
use Waka\SalesForce\Models\LogsfError;
use Wcli\Wconfig\Models\Settings;

class SalesForceImport
{
    protected $query;
    protected $vars;
    protected $model;
    protected $transform;
    protected $mappedDatas;
    public $name;
    public $mappedRows;
    public $lastLogDate;
    private $queryModel;
    private $queryFnc;

    public function __construct(array $config)
    {
        //trace_log($config);
        $this->query = $config['query'] ?? null;
        $query_model = $config['query_model'] ?? false;
        $query_fnc = $config['query_fnc'] ?? false;
        
        $this->queryModel = $config['query_model'] ?? null;
        $this->model = $config['model'] ?? null;
        $this->name = $config['name'] ?? null;
        $this->lastLogDate = $this->getLastLogDate();
        $vars = $config['vars'] ?? null;
        $this->vars = $this->prepareVars($vars);
        $this->mappedDatas = $config['mapping'] ?? null;
        $this->mappedDatas = $config['mapping'] ?? null;
        $this->transform = $config['transform'] ?? null;
        if($query_model && $query_fnc) {
            $this->queryModel = new $query_model;
            $this->queryFnc = $query_fnc;
        } elseif (!$this->query || !$this->vars || !$this->mappedDatas) {
            throw new \ApplicationException('SalesForce impoter  error due to config ');
        }
        $this->createLog();
        $this->mappedRows = 0;

    }

    public function prepareVars($vars)
    {
        if($vars) {
            foreach ($vars as $key => $var) {
                switch ($var) {
                    case "day_m_1":
                        $vars[$key] = Carbon::now()->subDay()->format('Y-m-d\TH:i:s.uP');
                        break;
                    case "month_m_1":
                        $vars[$key] = Carbon::now()->subMonth()->format('Y-m-d\TH:i:s.uP');
                        break;
                    case "last_log":
                        $vars[$key] = $this->lastLogDate->format('Y-m-d\TH:i:s.uP');
                        break;
                    default:
                        $vars[$key] = $var;
                }
            }
            return $vars;
        } else {
            return [];
        }
        
        
    }

    public function getLastLogDate()
    {
        // trace_sql();
        // trace_log("getLastLogDate from " . $this->name);
        $lastImport = Logsf::where('name', $this->name)->where('is_ended', true)->orderBy('created_at', 'desc')->first();
        //trace_log($lastImport->created_at);
        if ($lastImport) {
            return $lastImport->created_at;
        } else {
            $date = Settings::get('sf_oldest_date');
            return Carbon::parse($date);
        }

    }

    public function prepareQuery()
    {
        $query = \Twig::parse($this->query, $this->vars);
        return $query;
    }

    public function createLog()
    {
        $this->logsf = Logsf::create([
            'name' => $this->name,
            'start_at' => $this->lastLogDate,
        ]);
    }
    public function updateLog($var, $value)
    {
        $this->logsf->update([
            $var => $value,
        ]);
    }
    public function updateAndCloseLog()
    {
        $this->logsf->update([
            'is_ended' => true,
            'ended_at' => Carbon::now(),
            'nb_updated_rows' => $this->mappedRows,
        ]);
    }

    public function executeQuery()
    {

        $query = $this->prepareQuery();
        $this->updateLog('query', $query);
        if ($query) {
            $this->sendQuery($query);
        }
        $this->updateAndCloseLog();
    }
    public function sendQuery($query = null, $next = null)
    {
        if (!$next && $query) {
            $result = \Forrest::query($query);
            $this->updateLog('sf_total_size', $result['totalSize'] ?? null);
            if($this->queryModel) {
                $classImport = $this->queryModel;
                $queryFnc = $classImport->{$this->queryFnc}($result['records']);
            } else {
                $this->mapResults($result['records']);
            }
            
            $next = $result['nextRecordsUrl'] ?? null;
            $this->sendQuery(null, $next);
        } else if ($next) {
            $result = \Forrest::next($next);
            if($this->queryModel) {
                $classImport = $this->queryModel;
                $queryFnc = $classImport->{$this->queryFnc}($result['records']);
            } else {
                $this->mapResults($result['records']);
            }
            $next = $result['nextRecordsUrl'] ?? null;
            $this->sendQuery(null, $next);
        } else {
            //trace_log('Terminé plus de next');
        }
    }

    public function mapResult($row)
    {
        $row = array_dot($row);
        $finalResult = [];
        foreach ($row as $column => $value) {
            //trace_log($column);
            $finalKey = $this->mappedDatas[$column] ?? null;
            if (!$finalKey) {
                continue;
            }
            $finalResult[$finalKey] = $this->transform($finalKey, $value);
        }
        return $finalResult;
    }

    public function mapResults($rows)
    {
        foreach ($rows as $row) {
            $mappedRow = $this->mapResult($row);
            $model = null;
            if (method_exists($this->model, 'withTrashed')) {
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
                $model = $this->model;
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

    public function transform($key, $value)
    {
        // if (!$this->transform) {
        //     return $value;
        // }
        $transform = $this->transform[$key] ?? null;
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
}
