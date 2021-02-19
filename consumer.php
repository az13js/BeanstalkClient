<?php
require 'autoload.php';

$queue = new Util\Beanstalk\BeanstalkQueue(['host' => '127.0.0.1', 'port' => 11300]);

while (true) {
    try {
        $job = $queue->pop();
        if (false === $job) {
            echo 'pop fail, ' . $queue->getLastError() . PHP_EOL;
            sleep(1);
        } else {
            $result = $job->delete();
            if (false === $result) {
                echo 'delete fail, ' . $queue->getLastError() . PHP_EOL;
            }
        }
    } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL;
        sleep(1);
    }
}

echo 'Exit' . PHP_EOL;
