<?php

namespace command;


use common\config\Constant;
use Swoole\WebSocket\Server;
use zcswoole\command\WebSocketServerCommand;
use zcswoole\services\MysqliDB;
use zcswoole\services\ZRedis;

/**
 * 聊天室
 * swoole_websocket_server
 * Class ChatCommand
 * @package app\command
 * @author wuzhc
 */
class ChatCommand extends WebSocketServerCommand
{
    // 存储fd和userID映射关系
    // TODO 很多fd时建议映射关系保存到redis,否则会占用当前worker进程内存
    protected $map = [];

    /**
     * 增加一个request事件
     * 增加一个task事件
     * 增加一个finish事件
     */
    public function onEvent()
    {
        parent::onEvent();
        $this->server->on('request', [$this, 'request']);
        $this->server->on('task', [$this, 'task']);
        $this->server->on('finish', [$this, 'finish']);
    }

    /**
     * 自定义open事件
     * @param Server $server
     * @param $request
     */
    public function open(Server $server, $request)
    {
        echo "server: handshake success with fd{$request->fd}\n";
    }

    /**
     * 自定义message事件
     * @param Server $server
     * @param $frame
     */
    public function message(Server $server, $frame)
    {

        $data = json_decode($frame->data, true);
        $userID = $data['from'] ?? null;
        $sender = MysqliDB::instance()->getOneByPk(Constant::CHAT_USER, $userID);

        $chatKey = 'chat:fd:user:' . $userID;
        $this->map[$frame->fd] = $userID;

        $opType = $data['opType'];
        switch ($opType) {
            case 'open':
                ZRedis::instance()->sadd($chatKey, $frame->fd, 7200);
                break;
            case 'message':
                if (!$data['from'] || !$data['to'] || !$data['targetType']) {
                    return ;
                }

                // 双方第一次聊天没有对话框,需要创建一条对话记录
                if (!$data['dialogID']) {
                    $data['dialogID'] = MysqliDB::instance()->insert(Constant::CHAT_DIALOG, [
                        'from_uid'    => $data['from'],
                        'status'      => 1,
                        'type'        => $data['targetType'],
                        'to_uid'      => $data['targetType'] == 1 ? $data['to'] : 0,
                        'group_id'    => $data['targetType'] == 2 ? $data['to'] : 0,
                        'create_time' => date('Y-m-d H:i:s'),
                    ]);
                }

                if (!empty($data['to']) && !empty($data['dialogID'])) {
                    if ($data['targetType'] == 2) {                         // 对象是群组
                        $group = MysqliDB::instance()->getOneByPk(Constant::CHAT_GROUP, $data['to']);
                        $pushData= [
                            'opType'        => 'message',
                            'taskType'      => 'broadcast',
                            'groupID'       => $data['to'],                    // 群ID
                            'from'          => $data['from'],
                            'content'       => $data['content'],
                            'dialogID'      => $data['dialogID'],
                            'time'          => date('Y-m-d H:i:s'),
                            'targetType'    => $data['targetType'],
                            'name'          => $sender['account'],
                            'portrait'      => $sender['portrait'],
                            'groupName'     => $group['name'] ?? '',
                            'groupPortrait' => $group['portrait'] ?? '',
                        ];
                        $server->task($pushData);                          // 广播给群成员,比较耗时,交给task进程处理
                    } elseif ($data['targetType'] == 1) {                  // 对象是个人
                        $toKey = 'chat:fd:user:' . $data['to'];
                        if ($fds = ZRedis::instance()->sMembers($toKey)) { // 查看对方是否在线,在线则发送消息给对方
                            $pushData= [
                                'opType'     => 'message',
                                'targetType' => 1,
                                'from'       => $data['from'],
                                'content'    => $data['content'],
                                'time'       => date('Y-m-d H:i:s'),
                                'dialogID'   => $data['dialogID'],
                                'name'       => $sender['account'],
                                'portrait'   => $sender['portrait'],
                            ];
                            foreach ($fds as $fd) {
                                $fdInfo = $server->connection_info($fd);
                                if (!empty($fdInfo['websocket_status'])) {
                                    $server->push($fd, json_encode($pushData)); // TODO 非正常关闭会出现 the connected client of connection[1] is not a websocket client
                                } else {
                                    ZRedis::instance()->sRem($toKey, $fd); // 清除异常退出的fd
                                }
                            }
                        }
                    }

                    // task异步任务保存聊天记录
                    $chatRecordData = [
                        'dialog_id'   => $data['dialogID'],
                        'user_id'     => $data['from'],
                        'group_id'    => $data['targetType'] == 2 ? $data['to'] : 0,
                        'status'      => 0,
                        'content'     => $data['content'],
                        'create_time' => date('Y-m-d H:i:s'),
                        'taskType'    => 'saveChatRecord'
                    ];
                    $server->task($chatRecordData); //TODO 投递容量超过处理能力,task会塞满缓冲区,导致worker进程阻塞,worker进程无法再接受请求
                }
                break;
        }
    }

    /**
     * @param Server $server
     * @param $taskID
     * @param $workerID
     * @param $data
     */
    public function task($server, $taskID, $workerID, $data)
    {
        $taskType = $data['taskType'] ?? 'saveChatRecord';
        switch ($taskType) {
            case 'broadcast':   // 广播消息
                unset($data['taskType']);
                $maps = MysqliDB::instance()
                    ->where('group_id', $data['groupID'])
                    ->get(Constant::CHAT_GROUPS_MAP, null, 'user_id');

                if ($maps) {
                    $dialogs = [];
                    foreach ($maps as $map) {
                        // 消息是本人发送,则不需要广播给自己
                        if ($map['user_id'] == $data['from']) {
                            continue;
                        }

                        // 没有对话框,则需要保存一条对话框记录
                        $record = MysqliDB::instance()->where('from_uid', $map['user_id'])
                            ->where('to_uid', $map['user_id'], '=', 'or')
                            ->where('group_id', $data['groupID'])
                            ->getOne(Constant::CHAT_DIALOG);
                        if (!$record) {
                            $dialogs[] = [
                                'type'        => 2,
                                'from_uid'    => 0,
                                'to_uid'      => $map['user_id'],
                                'group_id'    => $data['groupID'],
                                'status'      => 1,
                                'create_time' => date('Y-m-d H:i:s'),
                                'update_time' => date('Y-m-d H:i:s'),
                            ];
                        }

                        $toKey = 'chat:fd:user:' . $map['user_id'];
                        $fds = ZRedis::instance()->sMembers($toKey);
                        if (!$fds) { // 不在线不推送
                            continue;
                        }

                        foreach ($fds as $fd) { // 为每个用户的每个连接推送消息
                            $fdInfo = $server->connection_info($fd);
                            if (!empty($fdInfo['websocket_status'])) {
                                $server->push($fd, json_encode($data));
                            } else {
                                ZRedis::instance()->sRem($toKey, $fd);
                            }
                        }
                    }

                    if ($dialogs) {
                        MysqliDB::instance()->insertMulti(Constant::CHAT_DIALOG, $dialogs);
                    }
                }

                break;
            case 'saveChatRecord': // 聊天记录
                unset($data['taskType']);
                $lastRecordID = MysqliDB::instance()->insert('chat_record', $data);

                $dialog = MysqliDB::instance()->getOneByPk(Constant::CHAT_DIALOG, $data['dialog_id']);
                if ($lastRecordID && $dialog) {
                    if ($dialog['type'] == 1) {
                        MysqliDB::instance()->updateByPk(Constant::CHAT_DIALOG, [
                            'last_record_id' => $lastRecordID,
                            'update_time' => date('Y-m-d H:i:s'),
                            'status' => 1
                        ], $data['dialog_id']);
                    } elseif ($dialog['type'] == 2 && $dialog['group_id']) {
                        // 更新所有群成员聊天状态
                        MysqliDB::instance()->where('group_id', $dialog['group_id'])->update(Constant::CHAT_DIALOG, [
                            'last_record_id' => $lastRecordID,
                            'update_time' => date('Y-m-d H:i:s'),
                            'status' => 1
                        ]);

                        // 本人标记为已读
                        MysqliDB::instance()->updateByPk(Constant::CHAT_DIALOG, [
                            'status' => 0
                        ], $data['dialog_id']);
                    }
                }
                break;
        }
        $server->finish("workerID:$workerID taskID:$taskID finish");
    }

    /**
     * task进程的onTask事件中没有调用finish方法或者return结果，worker进程不会触发onFinish
     * @link https://wiki.swoole.com/wiki/page/136.html
     * @param $server
     * @param int $taskID
     * @param string $data
     */
    public function finish($server, $taskID, $data)
    {
        echo $data . PHP_EOL;
    }

    /**
     * 自定义close事件
     * @param Server $server
     * @param $fd
     */
    public function close(Server $server, $fd)
    {
        if (isset($this->map[$fd])) {
            $chatKey = 'chat:user:' . $fd;
            if (ZRedis::instance()->sIsMember($chatKey, $fd)) {
                ZRedis::instance()->sRem($chatKey, $fd);
            }
            unset($this->map[$fd]);
        }
        echo "client {$fd} closed\n";
    }
}