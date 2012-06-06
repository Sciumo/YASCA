<?php

class Cache {

    private $max_size;              // default 2k cache
    private $data;
    
    private $data_size;             
    private $size;
    
    private $data_age;              // used for cache evictions
    private $age;
    
    function __construct($max_size = 5100) {
        $this->max_size = $max_size;
        $this->data = array();
        $this->data_age = array();
        $this->data_size = array();
        $this->age = 0;
    }
    
    function contains($key) {
        return isset($this->data[$key]);
    }
    
    function put($key, $value) {
        if (strlen($value) > $this->max_size) return false;     // is it too big to begin with?
        while ($this->size + strlen($value) > $this->max_size) {
            $this->evict();
        }
        
        unset($this->data[$key]);
        $this->data[$key] = $value;
        $this->data_age[$key] = ++$this->age;
        $this->size += strlen($value);
    }
    
    function put_file_contents($filename) {
        if (is_file($filename) && is_readable($filename)) {
            $contents = file_get_contents($filename);

            $this->put($filename, $contents);
        }
    }
    
    function get_file_contents($filename) {
        $p = pathinfo($filename);
        $c_filename = $p['dirname'] . '/' . $p['basename'];
        $c = $this->get($c_filename);
        print $this->max_size - $this->size . " ";
        if ($c == false) {
            print "X [$c_filename] \n";
            if (is_file($filename) && is_readable($filename)) {
                $this->put($c_filename, file_get_contents($filename));
            }
            return $this->get($filename);
        } else {
            print ". [$c_filename]\n";
            return $c;
        }
    }
    
    function get($key) {
        if (isset($this->data[$key])) {
            $this->data_age[$key] = ++$this->age;
            return $this->data[$key];
        } else {
            return false;
        }
    }
    
    function evict() {
        $candidate_value = 0;
        $candidate_key = -1;
        
        foreach ($this->data_age as $da_key => $da_value) {
            if ($da_value < $candidate_value || $candidate_value == 0) {
                $candidate_key = $da_key;
                $candidate_value = $da_value;
            }
        }
        if ($candidate_value > 0) {
            $this->size -= strlen($this->data[$candidate_key]);
            unset($this->data[$candidate_key]);
            unset($this->data_age[$candidate_key]);
        }
             
    }
    function to_array() {
        return $this->data;
    }
}
?>
