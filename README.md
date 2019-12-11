## PHP 的 socket 端口不可用时的反应

    // 10 是连接超时时间
    $socket = stream_socket_client('127.0.0.1:9990', $error, $errorInfo, 10);
    if (false === $socket) {
        var_dump($error, $errorInfo);
    } else {
        var_dump($socket);
    }

上面的 9990 端口是没有开监听的，得到的输出如下：

    PHP Warning:  stream_socket_client(): unable to connect to 127.0.0.1:9990 (Connection refused) in /path/index.php on line 7

    Warning: stream_socket_client(): unable to connect to 127.0.0.1:9990 (Connection refused) in /path/index.php on line 7
    int(111)
    string(18) "Connection refused"

## 端口可用的反应

代码

    // 183.232.231.172:80 这个是百度的网页服务器，80 端口可用
    $socket = stream_socket_client('183.232.231.172:80', $error, $errorInfo, 10);
    if (false === $socket) {
        var_dump($error, $errorInfo);
    } else {
        var_dump($socket);
    }

返回

    resource(6) of type (stream)

## 下面测试超时

    $socket = stream_socket_client('75.126.124.162:80', $error, $errorInfo, 10);
    if (false === $socket) {
        var_dump($error, $errorInfo);
    } else {
        var_dump($socket);
    }

返回

    PHP Warning:  stream_socket_client(): unable to connect to 75.126.124.162:80 (Connection timed out) in /path/index.php on line 7

    Warning: stream_socket_client(): unable to connect to 75.126.124.162:80 (Connection timed out) in /path/index.php on line 7
    int(110)
    string(20) "Connection timed out"

## stream_set_timeout 函数

PHP函数stream_set_timeout（Stream Functions）作用于读取流时的时间控制。

## stream_socket_sendto

stream_socket_sendto 无论 socket 是否连接都会发送出去，函数返回一个 int 值。这个返回值是写入 socket 的字节数，如果是 -1 那就表示写入失败。

## stream_get_line 函数

从资源流里读取一行直到给定的定界符。
