<?php

return [
    'template_file_suffix' => '.html', // 模板文件后缀,默认为html
    'template_theme' => 'default', // 模板主题
    'command_namespace' => '\command',
    'crontab_dir' => DIR_ROOT . '/app/data/crontab.txt',
    'server' => [
        'port' => 9502,
        'host' => '127.0.0.1',
        'setting' => [
            'worker_num' => 1,
            'task_worker_num' => 1,
            'daemonize' => 0,
            'pid_file' => DIR_ROOT . '/app/data/server.pid',
            'log_file' => DIR_ROOT . '/app/log/server.log'
        ]
    ],
    'http_server' => [
        'port' => 9501,
        'host' => '0.0.0.0',
        'setting' => [
            'worker_num' => 4,
            'max_request' => 2000,
            'daemonize' => 0,
            'task_worker_num' => 10,
            'enable_static_handler' => true,
            'document_root' => DIR_ROOT . '/app/static',
            'pid_file' => DIR_ROOT . '/app/data/http.pid',
            'log_file' => DIR_ROOT . '/app/log/http.log'
        ]
    ],
    'websocket_server' => [
        'port' => 9502,
        'host' => '0.0.0.0',
        'setting' => [
            'worker_num' => 1,
            'daemonize' => 0,
            'enable_static_handler' => true,
            'task_worker_num' => 10,
            'document_root' => DIR_ROOT . '/app/static',
            'pid_file' => DIR_ROOT . '/app/data/websocket.pid',
            'log_file' => DIR_ROOT . '/app/log/websocket.log'
        ]
    ],
    'mysql' => [
        'user' => 'root',
        'password' => '',
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => 'met'
    ],
    'components' => [
        'table' => [
            'class' => 'zcswoole\Table',
        ],
    ],
];