<?php

namespace command;


use Cron\CronExpression;
use Swoole\Server;
use swoole_server;
use zcswoole\command\Command;
use zcswoole\command\CommandContext;
use zcswoole\Config;
use zcswoole\Event;
use zcswoole\services\MysqliDB;
use zcswoole\utils\Console;

/**
 * 独立的定时器
 * Class CrontabCommand
 * @package command
 */
class CrontabCommand extends Command
{
    use Event;

    /** @var Server */
    protected $server;

    /**
     * @param CommandContext $context
     */
    public function execute(CommandContext $context)
    {
        switch ($context->getAction()) {
            case 'start':
                $this->start();
                break;
            case 'status':
                $this->status();
                break;
            case 'reload':
                $this->reload('http_server');
                break;
            case 'stop':
                $this->stop('http_server');
                break;
        }
    }

    public function start()
    {
        $config = Config::get('server');
        $this->server = new swoole_server($config['host'], $config['port']);
        $this->server->set($config['setting'] ?? []);
        $this->server->on('workerStart', [$this, 'workerStart']);
        $this->server->on('receive', [$this, 'receive']);
        $this->server->on('task', [$this, 'task']);
        $this->server->on('finish', [$this, 'finish']);
        Console::msg('contrab starting');
        $this->server->start();
    }

    public function workerStart()
    {
        if (!$this->server->taskworker) {
            swoole_timer_tick(1000, function(){
                echo "run\n";
                $records = MysqliDB::instance()->where('status', 0)->get('crontab');
                foreach ($records as $record) {
                    $nextTime = CronExpression::factory($record['timer'])->getNextRunDate()->getTimestamp() - 1;
                    if (time() == $nextTime) {
                        $this->server->task($record['command']);
                    } else {
                        echo "no run; next time is ".$nextTime.", now time is ". time() . PHP_EOL;
                    }
                }
            });
        }
    }

    public function task(Server $server, $taskID, $workerID, $data)
    {
        exec($data);
        return $data . 'success';
    }

    public function finish(Server $server, $taskID, $data)
    {
        echo $data . PHP_EOL;
    }

}