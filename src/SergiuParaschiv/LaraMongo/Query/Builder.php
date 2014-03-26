<?php namespace SergiuParaschiv\LaraMongo\Query;

use MongoID;
use MongoDB;
use MongoCollection;

use SergiuParaschiv\LaraMongo\Processors\Processor;

class Builder {

    protected $db;
    protected $processor;
    
    protected $operators = array(
        '=', '<', '>', '<=', '>='
    );
    
    public $columns;
    public $collection;
    public $wheres;
    public $offset = 0;
    public $limit = 0;

    public function __construct(MongoDB $db)
    {
        $this->db = $db;
        $this->processor = new Processor();
        
        return $this;
    }
    
    public function collection($name)
    {
        $this->collection = $name;
        
        return $this;
    }
    
    public function select($columns = array())
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    public function addSelect($column)
    {
        $column = is_array($column) ? $column : func_get_args();

        $this->columns = array_merge((array) $this->columns, $column);

        return $this;
    }
    
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if(func_num_args() == 2)
        {
            list($value, $operator) = array($operator, '=');
        }
        elseif($this->invalidOperatorAndValue($operator, $value))
        {
            throw new \InvalidArgumentException('Value must be provided.');
        }


        if(!in_array(strtolower($operator), $this->operators, true))
        {
            list($value, $operator) = array($operator, '=');
        }
        
        $type = 'basic';

        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        return $this;
    }
    
    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }
    
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'between';

        $this->wheres[] = compact('column', 'type', 'boolean', 'values', 'not');

        return $this;
    }

    public function orWhereBetween($column, array $values, $not = false)
    {
        return $this->whereBetween($column, $values, 'or');
    }
    
    public function whereNotBetween($column, array $values, $boolean = 'and')
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    public function orWhereNotBetween($column, array $values)
    {
        return $this->whereNotBetween($column, $values, 'or');
    }
    
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $type = $not ? 'notin' : 'in';

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        return $this;
    }
    
    public function orWhereIn($column, $values)
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function orWhereNotIn($column, $values)
    {
        return $this->whereNotIn($column, $values, 'or');
    }
    
    public function dynamicWhere($method, $parameters)
    {
        $finder = substr($method, 5);

        $segments = preg_split('/(And|Or)(?=[A-Z])/', $finder, -1, PREG_SPLIT_DELIM_CAPTURE);

        $connector = 'and';

        $index = 0;

        foreach($segments as $segment)
        {
            if($segment != 'And' && $segment != 'Or')
            {
                $this->addDynamic($segment, $connector, $parameters, $index);
                $index++;
            }
            else
            {
                $connector = $segment;
            }
        }

        return $this;
    }
    
    protected function addDynamic($segment, $connector, $parameters, $index)
    {
        $bool = strtolower($connector);

        $this->where(snake_case($segment), '=', $parameters[$index], $bool);
    }
    
    public function offset($value)
    {
        $this->offset = max(0, $value);

        return $this;
    }

    public function skip($value)
    {
        return $this->offset($value);
    }

    public function limit($value)
    {
        if($value > 0)
        {
            $this->limit = $value;
        }

        return $this;
    }

    public function take($value)
    {
        return $this->limit($value);
    }
    
    public function forPage($page, $perPage = 15)
    {
        return $this->skip(($page - 1) * $perPage)->take($perPage);
    }
    
    public function find($id, $columns = array())
    {
        return $this->where('_id', '=', new MongoID($id))->take(1)->get($columns);
    }
    
    public function first($columns = array())
    {
        $results = $this->take(1)->get($columns);

        return count($results) > 0 ? reset($results) : null;
    }
    
    public function get($columns = array())
    {
        if(is_null($this->columns))
        {
            $this->columns = $columns;
        }
        
        $collection = new MongoCollection($this->db, $this->collection);
        
        $cursor = $collection->find($this->compileWheres(), $this->compileColumns());
        
        $cursor = $cursor->skip($this->offset)->limit($this->limit);
        
        return $this->processor->processSelect($cursor);
    }
    
    public function exists()
    {
        return $this->count() > 0;
    }
    
    public function count()
    {
        if(is_null($this->columns))
        {
            $this->columns = $columns;
        }
        
        $collection = new MongoCollection($this->db, $this->collection);
        
        $cursor = $collection->find($this->compileWheres(), $this->compileColumns());
        
        return $cursor->count();
    }
    
    public function insert(array $values)
    {
        if(!is_array(reset($values)))
        {
            $values = array($values);
        }
        
        $collection = new MongoCollection($this->db, $this->collection);
        
        return $collection->batchInsert($values);
    }
    
    public function insertGetId($value)
    {
        $collection = new MongoCollection($this->db, $this->collection);
        $collection->insert($value);

        return $value['_id'];
    }
    
    public function update(array $values)
    {
        $collection = new MongoCollection($this->db, $this->collection);
        
        $collection->update($this->compileWheres(), array('$set' => $values));
    }
    
    public function increment($column, $amount = 1)
    {
        $collection = new MongoCollection($this->db, $this->collection);
        
        $collection->update($this->compileWheres(), array('$inc' => array($column => $amount)));
    }

    public function decrement($column, $amount = 1)
    {
        $this->increment($column, -$amount);
    }

    public function delete($id = null)
    {
        $collection = new MongoCollection($this->db, $this->collection);
        
        if(!is_null($id))
        {
            $this->where('_id', '=', new MongoID($id));
        }
        
        $collection->remove($this->compileWheres());
    }
    
    public function truncate()
    {
        $collection = new MongoCollection($this->db, $this->collection);
        
        $collection->remove();
    }
    
    public function __call($method, $parameters)
    {
        if (starts_with($method, 'where'))
        {
            return $this->dynamicWhere($method, $parameters);
        }

        $className = get_class($this);

        throw new \BadMethodCallException("Call to undefined method {$className}::{$method}()");
    }

    protected function invalidOperatorAndValue($operator, $value)
    {
        $isOperator = in_array($operator, $this->operators);

        return ($isOperator && $operator != '=' && is_null($value));
    }
    
    protected function compileWheres()
    {
        $query = array();
        
        if(is_array($this->wheres))
        {
            foreach($this->wheres as $where)
            {
                $query[$where['column']] = $where['value'];
            }
        }
        
        return $query;
    }
    
    protected function compileColumns()
    {
        $columns = array();
        
        if(is_array($this->columns))
        {
            foreach($this->columns as $column)
            {
                $columns[$column] = true;
            }
        }
        
        return $columns;
    }
}
