zcswoole基于swoole框架,对swoole进行封装,能够帮助开发人员更加专注于业务逻辑,简单快速
#### 有哪些特点?
- zcswoole必须运作在cli模式下,不同于传统的web框架,zcswoole不依赖于apache和nginx等webserver服务,只需要安装了php就可以运行
- zcswoole是常驻内存的,这意味着可以提高效率.举个例子,nginx服务器下的php-fpm只需要加载一次php.ini文件,所有的worker进程可以使用php.ini,但是这毕竟是单页面级的,一个请求结束之后,所有的类和变量都会被销毁,像yii这种重型框架,一个请求需要加载各种文件,和实例化各种组件实在浪费,zcswoole可以在服务start()之前保留在内存中,不需要每次加载
- 多进程
- 定时器
- 异步MySQL,异步文件IO,异步TASK任务
- 消息队列
- 远程RPC

#### 性能:
![](https://box.kancloud.cn/b770e67f45bae287d25d8228f45cc69b_1357x450.png)

#### 有哪些设计模式?
- 命令行模式(名字来源于深入面向对象)
- IOC容器,依赖注入
- 单例模式
- 工厂模式
- 装饰器模式

#### 需要运行环境?
- php7.1+ 
- swoole4.x

#### 如何安装?
```json
git clone git@github.com:wuzhc/zcswoole.git
compser install -vvv
```

#### 如何运行?
```php
php zcswoole.php [service] [command] [args...]
php zcswoole.php http start  # 启动http服务
php zcswoole.php http stop   # 定制http服务
php zcswoole.php http reload # 重启http服务
php zcswoole.php http status # http服务状态
```
![](https://box.kancloud.cn/ce6854e25763bd46ad0cbc4eb37b2548_869x266.png)

#### [更多文档](https://www.kancloud.cn/wuzhc/zcswoole/727890)
