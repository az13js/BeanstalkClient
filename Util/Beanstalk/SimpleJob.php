<?php
namespace Util\Beanstalk;

/**
 * Beanstalk 的 Job
 *
 * @author mengshaoying
 */
class SimpleJob extends Job
{
    /** @var int 整型值，为优先级，可以为0-2^32（4,294,967,295），值越小优先级越高，默认为1024 */
    public $pri = 1024;
    /** @var int 整型值，延迟ready的秒数，在这段时间job为delayed状态。*/
    public $delay = 0;
    /** @var int – time to run --整型值，允许worker执行的最大秒数，如果worker在这段时间不能delete，release，bury job，那么job超时，服务器将release此job，此job的状态迁移为ready。最小为1秒，如果客户端指定为0将会被重置为1。 */
    public $ttr = 1;
    /** @var int|null */
    public $id = null;
    /** @var object|null */
    private $bindQueue = null;

    /** @var string 消息，字符串 */
    protected $sourceData;

    /**
     * @param string $body
     * @param object $bindQueue
     */
    public function __construct(string $body, $bindQueue = null)
    {
        $this->sourceData = $body;
        $this->bindQueue = $bindQueue;
    }

    /**
     * 返回消息长度，不包括\r\n
     *
     * 文档：job body的长度，不包含命令结尾的\r\n，这个值必须小于max-job-size，默认为2^16。
     *
     * @return int
     */
    public function dataSize(): int
    {
        return strlen($this->sourceData);
    }

    /**
     * 返回字符串，此字符串就是消息的内容，不包括\r\n
     *
     * @return string
     */
    public function dataString(): string
    {
        return $this->sourceData;
    }

    /**
     * 删除 JOB
     *
     * @return bool 删除成功返回 true 否则返回 false
     */
    public function delete()
    {
        if (is_null($this->bindQueue)) {
            return false;
        }
        return $this->bindQueue->deleteJob($this->id);
    }
}
