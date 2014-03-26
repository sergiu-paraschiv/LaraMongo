<?php namespace SergiuParaschiv\LaraMongo;

use MongoClient;
use MongoDB;

class Connection extends \Illuminate\Database\Connection {

    private $connection;
    private $db;
    private $collection;

    public function __construct(array $config)
    {
        $dsn = $this->getDsn($config);
        
        $options = array();
        if(isset($config['options']))
        {
            $options = $config['options'];
        }
        
        $this->connection = new MongoClient($dsn, $options);
        $this->db = new MongoDB($this->connection, $config['database']);
    }
    
    public function collection($collectionName)
    {
        $query = new Query\Builder($this->db);

        return $query->collection($collectionName);
    }
    
    protected function getDsn($config)
    {
        extract($config);
        
        $dsn = 'mongodb://';
        
        if(isset($username) && isset($password))
        {
            $dsn .= "{$username}:{$password}@";
        }
        
        $dsn .= "{$host}:{$port}";
        
        return $dsn;
    }
}
