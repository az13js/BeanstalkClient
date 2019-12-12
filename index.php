<?php
require 'autoload.php';

$total_num = 500000;

$c = new BeanstalkClient('127.0.0.1:11300', 2, 2);

$start = time();
for ($i = 0; $i < $total_num; $i++) {
    $c->push($data);
}
$end = time();
echo ($total_num / ($end - $start)) . PHP_EOL;
