<?php
require 'autoload.php';

$total_num = 500000;

$c = new BeanstalkClient('127.0.0.1:11300', 2, 2);

$data = json_encode([
    'date' => '2019-12-01',
    'time' => '20:16:02',
    'ms' => 987.897,
    'params' => [
        [
            'name' => '百分比',
            'value' => 23.21,
        ],
        [
            'name' => '偏离',
            'value' => 0.5001,
        ],
    ],
]);
$start = time();
for ($i = 0; $i < $total_num; $i++) {
    $c->push($data);
}
$end = time();
echo ($total_num / ($end - $start)) . PHP_EOL;
