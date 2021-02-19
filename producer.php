<?php
require 'autoload.php';

$total_num = 10000;

$c = new BeanstalkClient('127.0.0.1:11300', 2, 2);

$data = '';
for ($i = 0; $i < 65535; $i++) {
    $data .= 'Z';
}

$start = time();
for ($i = 0; $i < $total_num; $i++) {
    try {
        $c->push($data);
    } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL;
        echo $i;
        break;
    }
}
$end = time();
echo (($i + 1) / ($end - $start)) . PHP_EOL;
