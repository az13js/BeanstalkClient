# Beanstalk客户端（测试）

## 生产者

```PHP
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
```

## 消费者

```PHP
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
```
