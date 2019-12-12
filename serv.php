<?php
require 'autoload.php';

$c = new BeanstalkClient('127.0.0.1:11300', 2, 2);
for ($i = 0; $i < 2013475; $i++) {
    try {
        $info = $c->reserveWithTimeout(1);
        //echo $info['data'] . PHP_EOL;
        $c->delete($info['id']);
    } catch (Exception $e) {
        //echo $e->getMessage() . PHP_EOL;
        //echo $e->getLine() . PHP_EOL;
        //die();
    }
}
