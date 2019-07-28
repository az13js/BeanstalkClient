<?php
namespace Util\Beanstalk;

/**
 * 消息队列模型
 */
interface Queue
{
    /**
     * 初始化队列
     *
     * @param mix $config
     */
    public function __construct($config);

    /**
     * 判断队列是否为空
     *
     * @return bool
     */
    public function isEmpty();

    /**
     * 判断是否已满
     *
     * @return bool
     */
    public function isFull();

    /**
     * 将元素插入队列
     *
     * @param mix $data
     * @return mix
     */
    public function push($data);

    /**
     * 返回最前的元素
     *
     * @return mix
     */
    public function pop();
}
