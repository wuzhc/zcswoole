> zcswoole基于[swoole](https://wiki.swoole.com/)的开发的php框架,目的是为了能够帮助开发人员更加专注于业务逻辑开发;zcswoole高度封装swoole并保留良好扩展性开发人员可以通过配置文件或子类重写或扩展zcswoole功能

#### 特点:
- zcswoole必须运作在cli模式下,不同于传统的web框架,zcswoole不依赖于apache和nginx等webserver服务,只需要安装了php就可以运行
- zcswoole是常驻内存的,可以减少加载文件或变量带来的开销
- 简单快速实现多进程程序
- 内置定时器任务
- 异步文件IO,异步TASK任务
- 消息队列
- 远程RPC

#### 性能:
![](https://box.kancloud.cn/b770e67f45bae287d25d8228f45cc69b_1357x450.png)

#### 设计模式:
- 命令行模式
- IOC容器,依赖注入
- 单例模式
- 工厂模式
- ...

#### 运行环境:
- php7.1+ 
- swoole4.x

#### 安装:
```json
git clone git@github.com:wuzhc/zcswoole.git
compser install -vvv
```

#### 运行(以http服务为例子):
```php
php zcswoole.php [service] [command] [args...]
php zcswoole.php http start  # 启动http服务
php zcswoole.php http stop   # 定制http服务
php zcswoole.php http reload # 重启http服务
php zcswoole.php http status # http服务状态
```
![](https://box.kancloud.cn/ce6854e25763bd46ad0cbc4eb37b2548_869x266.png)