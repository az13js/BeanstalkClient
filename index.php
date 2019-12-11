<?php
require 'autoload.php';

class BeanstalkClient
{
    public $maxReadSize = 65535;

    protected $socket;

    public function __construct(string $address, int $connectTimeout, int $streamReadTimeout)
    {
        $this->socket = stream_socket_client($address, $error, $errorInfo, $connectTimeout);
        if (false === $this->socket) {
            throw new Exception("Connect fail: $error, $errorInfo");
        } else {
            // 作用于读取流时的时间控制
            $result = stream_set_timeout($this->socket, $streamReadTimeout);
            if (false === $result) {
                throw new Exception("stream_set_timeout() fail");
            }
        }
    }

    public function useTube(string $tube): bool
    {
        //设置使用哪个 tube 函数 stream_socket_sendto 无论 socket 是否连接都会发送出去
        //默认是 default
        $sendData = "use $tube\r\n";
        $this->sendTo($sendData);
        // 在发送 use 命令后就算 stream_socket_sendto 没有异常我们也不知道有没有真的 use 指定的 tube
        // 只有收到服务端返回的信息才能判断。为什么多算 20 个字节，因为有可能发生 UNKNOWN_COMMAND\r\n
        // 之类的别的异常返回。
        $respond = stream_get_line($this->socket, 20 + strlen($tube), "\r\n");
        if (false === $respond) {
            // stream_get_line() 返回 false 只有一种可能，那就是出现意外的问题。
            throw new Exception('stream_get_line() return false');
        }
        if ("USING $tube" == $respond) {
            return true;
        } else {
            // 注意，这里如果用中文名作为Tube，它会返回 BAD_FORMAT
            throw new Exception("use $tube fail, server respond=\"$respond\"");
        }
    }

    public function push(string &$data, int $pri = 1024, int $delay = 0, int $ttr = 1): bool
    {
        $bytes = strlen($data);
        $sendData = "put $pri $delay $ttr $bytes\r\n$data\r\n";
        $this->sendTo($sendData);
        // 这个不知道会返回多大的数据
        $respond = stream_get_line($this->socket, $this->maxReadSize, "\r\n");
        if (false === $respond) {
            // stream_get_line() 返回 false 只有一种可能，那就是出现意外的问题。
            throw new Exception('stream_get_line() return false');
        }
        if (0 === mb_strpos($respond, 'INSERTED ') || 0 === mb_strpos($respond, 'BURIED ')) {
            return true;
        } else {
            throw new Exception("put job fail, server respond=\"$respond\".");
        }
    }

    private function sendTo(string &$send)
    {
        $code = stream_socket_sendto($this->socket, $send);
        if (-1 == $code) {
            throw new Exception("stream_socket_sendto() fail");
        }
    }
}

$c = new BeanstalkClient('127.0.0.1:11300', 2, 2);
$c->useTube('lubenwei');
$data = "卢本伟\r\n我没有开挂";
echo date('Y-m-d H:i:s') . PHP_EOL;
for ($i = 0; $i < 1000; $i++) {
    $c->push($data);
}
echo date('Y-m-d H:i:s') . PHP_EOL;
