<?php

namespace command;


use Cron\CronExpression;
use Swoole\Http\Server;
use zcswoole\command\HttpServerCommand;
use zcswoole\services\MysqliDB;
use zcswoole\utils\ConsoleUtil;

/**
 * http服务,继承zcswoole的httpServer服务后,覆盖父类方法可以扩展自己功能
 * Class AppCommand
 * @package command
 */
class AppCommand extends HttpServerCommand
{
    // demo,为http服务添加一个定时器功能,重写父类onEvent方法
    public function onEvent()
    {
        parent::onEvent(); // 如果还需要父类方法的话
        $this->server->on('receive', [$this, 'receive']);
        $this->server->on('task', [$this, 'task']);
        $this->server->on('finish', [$this, 'finish']);
    }

    /**
     * 开始进程,重写父类workerStart方法
     * @param $server
     * @param $workerID
     */
    public function workerStart($server, $workerID)
    {
        // 为第一个进程安装定时器
        if (!$this->server->taskworker && $workerID == 0) {
            ConsoleUtil::msg('crontab task start......');
            swoole_timer_tick(1000, function(){
                $records = MysqliDB::instance()->where('status', 0)->get('crontab');
                foreach ($records as $record) {
                    $nextTime = CronExpression::factory($record['timer'])->getNextRunDate()->getTimestamp() - 1;
                    if (time() == $nextTime) {
                        $this->server->task($record['command']);
                    } else {
                        ConsoleUtil::msg("no run; next time is ".$nextTime.", now time is ". time());
                    }
                }
            });
        }
    }

    /**
     * 处理任务
     * @param Server $server
     * @param $taskID
     * @param $workerID
     * @param $data
     * @return string
     */
    public function task(Server $server, $taskID, $workerID, $data)
    {
        exec($data);
        return $data . 'success';
    }

    /**
     * 结束任务回调
     * @param Server $server
     * @param $taskID
     * @param $data
     */
    public function finish(Server $server, $taskID, $data)
    {
        ConsoleUtil::success('has run ' . $data);
    }
}