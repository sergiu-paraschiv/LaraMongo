<?php namespace SergiuParaschiv\LaraMongo\Processors;

use MongoCursor;

class Processor {

    public function processSelect(MongoCursor $results)
    {
        $results = iterator_to_array($results);
        
        return array_values(array_map(array($this, 'processSelectResult'), $results));
    }
    
    protected function processSelectResult($result)
    {
        $result['id'] = (string) $result['_id'];
        unset($result['_id']);
        
        return $result;
    }
}
