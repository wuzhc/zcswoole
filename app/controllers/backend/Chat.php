<?php

namespace app\controllers\backend;


use common\config\Constant;
use zcswoole\http\HttpController;
use zcswoole\services\MysqliDB;
use zcswoole\ZCSwoole;

/**
 * 聊天
 * Class Chat
 * @package app\controllers\backend
 */
class Chat extends HttpController
{
    public $sessionKey = '';
    public $userID = '';

    /**
     * 登录检测
     */
    public function beforeAction()
    {
        $userID = $this->request->cookie['userID'] ?? null;
        if ($userID) {
            $this->userID = $userID;
            $this->sessionKey = 'session:user:' . $userID;
        }

        // 登录检测
        if (!$this->sessionKey || !ZCSwoole::$app->session->get($this->sessionKey, 'id')) {
            $this->response->redirect($this->createUrl('backend/site/login'), 302);
        }
    }

    /**
     * 首页
     */
    public function index()
    {
        // 根据最后聊天时间排序的对话框
        $dialogs = MysqliDB::instance()
            ->where('from_uid', $this->userID)
            ->where('to_uid', $this->userID, '=', 'or')
            ->orderBy('update_time', 'desc')
            ->get('chat_dialog');

        $data = [];
        foreach ($dialogs as $dialog) {
            $temp = array();
            $target = $this->getTarget($dialog);
            $temp['name'] = $target['name'] ?? '无名';
            $temp['portrait'] = $target['portrait'] ?? '无名';

            $record = $this->getLastChatRecord($dialog['last_record_id']);
            $temp['lastText'] = $record['content'];
            $temp['status'] = $dialog['status'] == 1 && ($record['user_id'] != $this->userID) ? 1 : 0;

            $temp['dialogID'] = $dialog['id'];  // 对话ID
            $temp['to'] = $target['id'];        // 对话对象
            $temp['type'] = $dialog['type'];    // 对话类型(私聊和群聊)
            $data[] = $temp;
        }

        $friends = MysqliDB::instance()
            ->join(Constant::CHAT_USER . ' user', 'user.id = map.friend_id', 'inner')
            ->where('map.user_id', $this->userID)
            ->where('map.status', 1)
            ->get(Constant::CHAT_FRIENDS_MAP . ' map', null, 'user.*');

        $groups = MysqliDB::instance()
            ->join(Constant::CHAT_GROUP . ' grp', 'grp.id = map.group_id', 'inner')
            ->where('map.user_id', $this->userID ?: 1)
            ->where('map.status', 1)
            ->get(Constant::CHAT_GROUPS_MAP . ' map', null, 'grp.*');

        $this->render('index', [
            'dialogs' => $data,
            'friends' => $friends,
            'groups' => $groups,
            'userID' => ZCSwoole::$app->session->get($this->sessionKey, 'id'),
            'portrait' => ZCSwoole::$app->session->get($this->sessionKey, 'portrait')
        ]);
    }

    /**
     * @param $args
     * @return array
     */
    protected function getTarget($args)
    {
        if ($args['type'] == 1) {           // 私聊用户
            if ($args['from_uid'] == $this->userID) {
                $user = MysqliDB::instance()->getOneByPk(Constant::CHAT_USER, $args['to_uid']);
            } else {
                $user = MysqliDB::instance()->getOneByPk(Constant::CHAT_USER, $args['from_uid']);
            }
            return $user;
        } elseif ($args['type'] == 2) {     // 群聊群组
            return MysqliDB::instance()->getOneByPk(Constant::CHAT_GROUP, $args['group_id']);
        }
    }

    /**
     * @param $id
     * @return string
     */
    protected function getLastChatRecord($id)
    {
        return MysqliDB::instance()->getOneByPk(Constant::CHAT_RECORD, $id);
    }

    /**
     * 获取聊天记录
     */
    public function getChatRecords()
    {
        $dialogID = $this->request->get['dialogID'];
        $dialog = MysqliDB::instance()->where('id', $dialogID)->getOne(Constant::CHAT_DIALOG);
        if (!$dialog) {
            $this->endJson([], 1, '数据不存在或已删除');
            return ;
        }

        $db = MysqliDB::instance();
        if ($dialog['type'] == 1) { // 私聊
            $db->where('dialog_id', $dialogID);
        } else {                    // 群聊
            $db->where('group_id', $dialog['group_id']);
        }

        $records = $db->orderBy('create_time', 'asc')->get(Constant::CHAT_RECORD);
        array_walk($records, function(&$r) {
           $user = MysqliDB::instance()->getOneByPk(Constant::CHAT_USER, $r['user_id']);
           $r['portrait'] = $user ? $user['portrait'] : '';
           $r['userName'] = $user ? $user['name'] : '';
        });

        $this->endJson(['data' => $records]);
    }

    /**
     * 加入好友
     */
    public function joinFriend()
    {
        $friendID = $this->request->get['friendID'];
        $record = MysqliDB::instance()
            ->where('friend_id',$friendID)
            ->where('user_id', $this->userID)
            ->getOne(Constant::CHAT_FRIENDS_MAP);

        if ($record) {
            if ($record['status'] == 0) {
                $this->endJson(null);
            } elseif ($record['status'] == 1) {
                $this->endJson(null, 1, '已是好友');
            } else {
                MysqliDB::instance()->updateByPk(
                    Constant::CHAT_FRIENDS_MAP,
                    ['status' => 1],
                    $record['id']
                );
            }
        } else {
            // 好友是相互的,因此要插入两条记录
            $res = MysqliDB::instance()->insertMulti(Constant::CHAT_FRIENDS_MAP, [
                [
                    'friend_id' => $friendID,
                    'user_id' => $this->userID,
                    'status' => 1
                ],
                [
                    'friend_id' => $this->userID,
                    'user_id' => $friendID,
                    'status' => 1
                ]
            ]);
            list($status, $msg) = $res ? array(1, '添加成功') : array(1, '添加失败');
            $this->endJson(null, $status, $msg);
        }
    }

    /**
     * 加入群组
     */
    public function joinGroup()
    {
        $groupID = $this->request->get['groupID'];
        $record = MysqliDB::instance()
            ->where('group_id', $groupID)
            ->where('user_id', $this->userID)
            ->getOne(Constant::CHAT_GROUPS_MAP);

        if ($record) {
            if ($record['status'] == 0) {
                $this->endJson(null);
            } elseif ($record['status'] == 1) {
                $this->endJson(null, 1, '已是群成员');
            } else {
                MysqliDB::instance()->updateByPk(
                    Constant::CHAT_GROUPS_MAP,
                    ['status' => 1],
                    $record['id']
                );
            }
        } else {
            $res = MysqliDB::instance()->insert(Constant::CHAT_GROUPS_MAP, [
                'group_id' => $groupID,
                'user_id' => $this->userID,
                'status' => 1
            ]);
            list($status, $msg) = $res ? array(0, '加入成功') : array(1, '加入失败');
            $this->endJson(null, $status, $msg);
        }
    }

    /**
     * 我的好友
     */
    public function myFriends()
    {
        $records = MysqliDB::instance()
            ->join(Constant::CHAT_USER . ' user', 'user.id = map.friend_id', 'inner')
            ->where('map.user_id', $this->userID)
            ->where('map.status', 1)
            ->get(Constant::CHAT_FRIENDS_MAP . ' map', null, 'user.*');
        $this->endJson(['data' => $records]);
    }

    /**
     * 我的群组
     */
    public function myGroups()
    {
        $records = MysqliDB::instance()
            ->join(Constant::CHAT_GROUP . ' grp', 'grp.id = map.group_id', 'inner')
            ->where('map.user_id', $this->userID ?: 1)
            ->where('map.status', 1)
            ->get(Constant::CHAT_GROUPS_MAP . ' map', null, 'grp.*');
        $this->endJson(['data' => $records]);
    }

    /**
     * 标记为已读消息
     */
    public function updateMsgStatus()
    {
        if ($this->request->get['dialogID']) {
            MysqliDB::instance()->updateByPk(Constant::CHAT_DIALOG, ['status' => 0], $this->request->get['dialogID']);
        }
        $this->endJson(null);
    }
}