<?php
require 'autoload.php';

$c = new BeanstalkClient('127.0.0.1:11300', 2, 30);
for ($i = 0; $i < 2013475; $i++) {
    try {
        $info = $c->reserve();
        //echo $info['data'] . PHP_EOL;
        $c->delete($info['id']);
    } catch (Exception $e) {
        if (3 != $e->getCode()) {
            echo $e->getMessage() . PHP_EOL;
            die();
        }
    }
}
