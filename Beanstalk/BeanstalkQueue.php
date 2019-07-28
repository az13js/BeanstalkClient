<?php
namespace Util\Beanstalk;

/**
 * Beanstalk
 *
 * @author mengshaoying
 */
class BeanstalkQueue implements Queue
{
    const MAX_SIZE = 655350;

    /** @var string */
    private $host;
    /** @var int */
    private $port;
    /** @var string */
    private $tube;
    /** @var int */
    private $timeout;
    /** @var resource resource of type (stream) */
    private $socket;
    /** @var string 最后一次错误的文字信息 */
    private $lastError = '';

    /**
     * 初始化连接，连接到一个 tube。
     *
     * @param array $config
     *
     * $config['host'] = '127.0.0.1';
     * $config['port'] = 11300;
     * $config['tube'] = 'default';
     * $config['timeout'] = 3;
     */
    public function __construct($config)
    {
        $this->host = isset($config['host']) ? $config['host'] : '127.0.0.1';
        $this->port = isset($config['port']) ? $config['port'] : 11300;
        $this->tube = isset($config['tube']) ? $config['tube'] : 'default';
        $this->timeout = isset($config['timeout']) ? $config['timeout'] : 3;
        $this->socket = stream_socket_client($this->host.':'.$this->port, $error, $errorInfo, $this->timeout);
        if (false === $this->socket) {
            throw new \Exception('Open socket fail, error: ' . $error . ', ' . $errorInfo);
        }
        stream_set_timeout($this->socket, $this->timeout);
    }

    /**
     * 对象销毁时关闭到服务器的连接
     */
    public function __destruct()
    {
        $this->command('quit');
        stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
    }

    /**
     * 判断队列是否为空
     *
     * @return bool
     */
    public function isEmpty()
    {
        return $this->length() <= 0;
    }

    /**
     * 判断是否已满
     *
     * 没有限制，总是返回 false
     *
     * @return bool
     */
    public function isFull()
    {
        return false;
    }

    /**
     * 将元素插入队列
     *
     * @param object $job
     * @return bool|int 失败返回 false，成功返回任务 id。可以使用 getLastError() 获得失败时服务器返回的消息
     */
    public function push($job)
    {
        if ("USING {$this->tube}" != trim($this->command('use ' . $this->tube))) {
            return false;
        }
        // put 命令需要换行一次
        $result = $this->command("put {$job->pri} {$job->delay} {$job->ttr} " . $job->dataSize() . "\r\n" . $job->dataString());
        if (false !== mb_stripos($result, 'INSERTED')) {
            return intval(trim(ltrim($result, 'INSERTED ')));
        }
        if (false !== mb_stripos($result, 'BURIED')) {
            return intval(trim(ltrim($result, 'BURIED ')));
        }
        // 失败
        $this->lastError = $result;
        return false;
    }

    /**
     * 返回最前面的元素，但是不立即删除
     *
     * 超时的时候 getLastError() 返回的是 "TIMED_OUT"
     *
     * @return object|bool 失败返回 false
     */
    public function pop()
    {
        if ($this->isEmpty()) {
            $this->lastError = 'This tube is empty, tube: ' . $this->tube;
            return false;
        }
        // 取出任务
        $watchResult = $this->command('watch ' . $this->tube);
        if (false === mb_stripos($watchResult, 'WATCHING')) {
            $this->lastError = 'watch ' . $this->tube . ', return ' . $watchResult;
            return false;
        }
        if ($this->tube != 'default') {
            $this->command('ignore default');
        }
        $jobInfo = $this->command('reserve-with-timeout ' . $this->timeout);
        if (0 !== mb_stripos($jobInfo, 'RESERVED')) {
            $this->lastError = trim($jobInfo);
            return false;
        }

        // 读取任务数据
        $rows = explode("\r\n", $jobInfo);
        list($reserve, $id, $byte) = explode(' ', $rows[0]);
        unset($rows[0]);
        $job = new SimpleJob(trim(implode("\r\n", $rows)), $this);
        $job->id = intval($id);

        // 补全任务的其他状态信息
        $jobStats = $this->command('stats-job ' . $id);
        if (false === mb_stripos($jobStats, 'OK') || 'NOT_FOUND' == trim($jobStats)) { // 任务不存在表示已经被消费
            $this->lastError = 'job ' . $id . ' not found, Beanstalk return: ' . trim($jobStats);
            return false;
        }
        $jobStatsRows = explode("\r\n", $jobStats);
        unset($jobStatsRows[0]);
        $stats = yaml_parse(implode("\r\n", $jobStatsRows));
        if (!is_array($stats)) {
            $this->lastError = 'yaml_parse() error.';
            return false;
        }
        $job->pri = isset($stats['pri']) ? $stats['pri'] : $job->pri;
        $job->delay = isset($stats['delay']) ? $stats['delay'] : $job->delay;
        $job->ttr = isset($stats['ttr']) ? $stats['ttr'] : $job->ttr;
        return $job;
    }

    /**
     * 获取最后一次出错的信息
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * 返回队列长度
     *
     * @return int
     */
    public function length()
    {
        $parsed = $this->status();
        if (!is_array($parsed)) {
            if ($this->getLastError() == 'NOT_FOUND') { // 如果队列为空那beanstalk会自动删除，导致队列不存在
                return 0;
            }
            throw new \Exception('Error, Beanstalk stats-tube ' . $this->tube . ' but yaml_parse(data) fail, the data from server is "'.$statsTube.'"');
        }
        if (
            !isset($parsed['current-jobs-ready']) ||
            !isset($parsed['current-jobs-reserved']) ||
            !isset($parsed['current-jobs-delayed']) ||
            !isset($parsed['current-jobs-buried'])
        ) {
            throw new \Exception('Error, Beanstalk stats-tube ' . $this->tube . ' but data format error, the data from server is "'.$statsTube.'"');
        }
        return $parsed['current-jobs-ready'] + // 准备就绪，等待取出的 job
            $parsed['current-jobs-reserved'] + // 被 worker 取出预定的 job
            $parsed['current-jobs-delayed'] + // 等待一定时间后重新转为 ready 的
            $parsed['current-jobs-buried']; // 等待唤醒，通常在job处理失败时
    }

    /**
     * 返回当前队列状态
     *
     * stats-tube
     *
     * @return array|bool
     */
    public function status()
    {
        $statsTube = $this->command('stats-tube ' . $this->tube);
        if ('NOT_FOUND' == trim($statsTube)) {
            $this->lastError = 'NOT_FOUND';
            return false;
        }
        $rows = explode("\r\n", $statsTube);
        // 返回的内容块的大小，根据名称字段，肯定会大于211，小于的话就不对了
        if (intval(ltrim(trim($rows[0]), 'OK ')) < 211) {
            throw new \Exception('Error, Beanstalk stats-tube ' . $this->tube . ' but return data size<211, the data from server is "'.$statsTube.'"');
        }
        unset($rows[0]);
        return yaml_parse(implode("\r\n", $rows));
    }

    /**
     * 删除某个 JOB
     *
     * @param int $id job的id
     * @return bool 成功返回true 否则返回false
     */
    public function deleteJob($id)
    {
        $result = $this->command('delete ' . $id);
        if ('DELETED' != trim($result)) {
            $this->lastError = $result;
            return false;
        }
        return true;
    }

    /**
     * 向 Beanstalkd 发送命令并获得结果
     *
     * 如果从发送命令到返回的过程出错或者返回的 Beanstalkd 响应明确显示命令出错，则
     * 会抛出异常。换言之成功获得的结果肯定是字符串。
     *
     * @param string $command 不需要包括换行符号
     * @return string|bool 失败返回 false
     */
    public function command($command)
    {
        if (false === stream_socket_sendto($this->socket, $command . "\r\n")) {
            return false;
        }
        if ('quit' == $command) {
            return "\r\n";
        }
        $result = stream_get_line($this->socket, self::MAX_SIZE, "\r\n");
        // var_dump($result, $command);
        $this->checkReturn($result, $command);
        // beanstalk 几个特殊格式，由于最后有\r\n不读取就会影响下次，所以 +2
        if (0 === mb_stripos($result, 'FOUND')) {
            list($found, $id, $byte) = explode(' ', trim($result));
            $result .= "\r\n";
            $result .= stream_get_contents($this->socket, $byte + 2);
        }
        if (0 === mb_stripos($result, 'RESERVED')) {
            list($reserved, $id, $byte) = explode(' ', trim($result));
            $result .= "\r\n";
            $result .= stream_get_contents($this->socket, $byte + 2);
        }
        if (0 === mb_stripos($result, 'OK')) {
            list($ok, $byte) = explode(' ', trim($result));
            $result .= "\r\n";
            $result .= stream_get_contents($this->socket, $byte + 2);
        }
        return $result;
    }

    /**
     * 检查返回结果是否有异常
     *
     * @param string|bool $commandReturn 命令返回的字符串
     * @param string $command 命令，如果需要抛出异常那么会把命令作为提示信息一起抛出
     * @throw Exception
     */
    private function checkReturn($commandReturn, $command = '')
    {
        if (false === $commandReturn) {
            throw new \Exception('Beanstalk error, return = false. Command (if exists): "' . $command . '"');
        }
        if ('UNKNOWN_COMMAND' == trim($commandReturn)) {
            throw new \Exception('Beanstalk error, UNKNOWN_COMMAND. Command (if exists): "' . $command . '"');
        }
        if ('OUT_OF_MEMORY' == trim($commandReturn)) {
            throw new \Exception('Beanstalk error, OUT_OF_MEMORY. Command (if exists): "' . $command . '"');
        }
        if ('INTERNAL_ERROR' == trim($commandReturn)) {
            throw new \Exception('Beanstalk error, INTERNAL_ERROR. Command (if exists): "' . $command . '"');
        }
        if ('BAD_FORMAT' == trim($commandReturn)) {
            throw new \Exception('Beanstalk error, BAD_FORMAT. Command (if exists): "' . $command . '"');
        }
    }
}
