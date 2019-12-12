<?php
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
        $respond = $this->recv(20 + strlen($tube));
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
        $respond = $this->recv($this->maxReadSize);
        if (0 === mb_strpos($respond, 'INSERTED ') || 0 === mb_strpos($respond, 'BURIED ')) {
            return true;
        } else {
            throw new Exception("put job fail, server respond=\"$respond\".");
        }
    }

    public function reserve(): array
    {
        $command = "reserve\r\n";
        $this->sendTo($command);
        $respond = $this->recv($this->maxReadSize);
        if (0 === mb_strpos($respond, 'RESERVED ')) {
            $info = ltrim($respond, 'RESERVED ');
            list($id, $byte) = explode(' ', $info);
            $respond = $this->recv($byte + 2);
            return ['id' => $id, 'data' => $respond];
        } else {
            throw new Exception("reserve fail, server respond=\"$respond\".");
        }
    }

    public function delete(int $id)
    {
        $command = "delete $id\r\n";
        $this->sendTo($command);
        $respond = $this->recv(12);
        if (0 === mb_strpos($respond, 'DELETED')) {
            return true;
        } else {
            throw new Exception("delete fail, server respond=\"$respond\".");
        }
    }

    private function sendTo(string &$send)
    {
        $code = stream_socket_sendto($this->socket, $send);
        if (-1 == $code) {
            throw new Exception("stream_socket_sendto() fail");
        }
    }

    private function recv(int $byte): string
    {
        $respond = stream_get_line($this->socket, $byte, "\r\n");
        if (false === $respond) {
            throw new Exception('stream_get_line() return false');
        }
        return $respond;
    }
}
