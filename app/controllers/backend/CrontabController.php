<?php

namespace app\controllers\backend;


use Cron\CronExpression;
use zcswoole\http\HttpController;
use zcswoole\services\MysqliDB;

/**
 * 定时任务
 * Class Crontab
 * @package app\controllers\backend
 */
class CrontabController extends HttpController
{
    /**
     * 列表
     */
    public function index()
    {
        $records = MysqliDB::instance()->orderBy('id', 'desc')->get('crontab');
        array_walk($records, function(&$r) {
           $r['nextTimer'] = CronExpression::factory($r['timer'])
               ->getNextRunDate()
               ->format('Y-m-d H:i:s');
        });

        $this->render('index', [
            'records' => $records,
            'total' => count($records)
        ]);
    }

    /**
     * 添加或编辑定时器任务
     */
    public function add()
    {
        if ($postData = $this->request->post) {
            $data = [
                'timer' => $postData['timer'] ?? '* * * * *',
                'command' => $postData['command'] ?? '',
                'status' => $postData['status'] ?? 1,
                'remark' => strip_tags($postData['remark'] ?? ''),
                'create_time' => date('Y-m-d H:i:s')
            ];
            if (MysqliDB::instance()->insert('crontab', $data)) {
                $this->endJson(null, 0, '操作成功');
            } else {
                $this->endJson(null, 1, '操作失败');
            }
        } else {
            $this->render('add');
        }
    }

    /**
     * 编辑
     */
    public function edit()
    {
        if ($data = $this->request->post) {
            $id = $data['id'] ?? null;
            if (!$id) {
                $this->endJson(null, 1, '参数错误');
            } else {
                $data = [
                    'timer' => $data['timer'] ?? '* * * * *',
                    'command' => $data['command'] ?? '',
                    'status' => $data['status'] ?? 1,
                    'remark' => strip_tags($data['remark'] ?? ''),
                ];

                if (MysqliDB::instance()->updateByPk('crontab', $data, $id)) {
                    $this->endJson(null, 0, '操作成功');
                } else {
                    $this->endJson(null, 1, '操作失败');
                }
            }
        } else {
            $record = [];
            if ($id = $this->request->get['id']) {
                $record = MysqliDB::instance()->getOneByPk('crontab', $id);
            }
            $this->render('edit', [
                'record' => $record
            ]);
        }
    }

    /**
     * 删除定时器
     */
    public function delete()
    {
        $id = $this->request->get['id'];
        if (is_numeric($id)) {
            $id = (array)$id;
        }

        if (MysqliDB::instance()->deleteByPK('crontab', $id)) {
            $this->endJson(null, 0, '操作成功');
        } else {
            $this->endJson(null, 1, '删除成功');
        }
    }

    /**
     * 停用定时器
     */
    public function stop()
    {
        $id = $this->request->get['id'];
        $status = $this->request->get['status'] ?? 1;
        if (!is_numeric($id)) {
            $this->endJson(null, 1, '参数错误');
        } else {
            $res = MysqliDB::instance()->updateByPk('crontab', ['status' => $status], $id);
            if ($res) {
                $this->endJson(null, 0, '操作成功');
            } else {
                $this->endJson(null, 1, '删除成功');
            }
        }
    }
}