<?php
    /**
     * Hello ioBlogV0.1~重构版(重构+升级+去除复杂结构)
     * CLI工具，如启动、重启等操作
     * 
     * @copyright  Copyright (c) imzlh Term(http://imzlh.top)
     * @license    GNU MIT License 
     * @link       https://imzlh.top/project
     * @document   https://imzlh.top/project/docs/#/ 
     */

    // 定义路径参数
    const _     = __DIR__;       // 根目录
    const _lib  = _.'/lib';   // 库文件
    const _cfg  = _.'/etc';   // 配置文件
    const _plug = _.'/usr';   // 插件
    const _post = _.'/post';  // 文章
    const _file = _.'/file';  // 文件
    const _conf = _cfg.'/config.json';// 配置文件
    const _v    = '0.0.1';    // 注意：ioBlog重构版与ioBlog完全不同
    const pass  = true;       // 仿python
    define('_app', $_0 = \basename(array_shift($argv)));  // 第一个参数是文件路径，提取入口文件名

    // 定义参数列表
    $argList = [
        'long'=>[
            'start',        // 启动
            'stop',         // 停止
            'reload',       // 重载
            'restart',      // 重启
            'admin',        // 面板
            'password',     // 如使用了-a则需要密码
            'help',         // 获取帮助
            'version',      // ioBlog版本
            'allow-root',   // 允许以ROOT启动
            'quiet',        // 安静模式
            'daemon',       // 以后台进程模式启动
            'thread',       // 强制线程模式
            'no-plugin',    // 安全模式
            'process',      // 多进程
            'no-panel',     // 无前端面板
        ],'short'=>[
            // 都是第一个字母，较常用的为小写
            's' =>  'start',
            'S' =>  'stop',
            'R' =>  'reload',
            'r' =>  'restart',
            'a' =>  'admin',
            'h' =>  'help',
            'v' =>  'version',
            'A' =>  'allow-root',
            'q' =>  'quiet',
            'd' =>  'daemon',
            'n' =>  'no-plugin',
            'p' =>  'no-panel'
        ]
    ];

    include(_lib.'/cliTool.php');   // 引入CLI工具
    cli::parse($argv,$argList,true);// 解析参数
    define('_q' , !is_null(cli::get('quiet')));
    if(_q) error_reporting(0);      // 安静模式下禁止提示

    foreach([
        // 版本号
        'version'=> function(){
            echo 'ioBlog重制版 V'._v.' by imzlh'.PHP_EOL;
        },
        // 帮助
        'help'  =>  function(){
            $version = _v;
            $self    = _app;
            echo <<<EOF
Hello ioBlog V$version(重构版)
使用方法：
    $self --[长命令](=[值]) 或 -[多个短命令]
    => 如">>> $self --help --start=82" || ">>> $self -hs"
    => 先显示帮助再(在82端口)启动HTTP服务器
    (!)短命令不接受任何值！

命令列表：
    长命令              短命令    说明
    --start=[port]     -s        启动HTTP服务器
    --stop             -S        停止HTTP服务器
    --reload           -R        平滑重载ioBlog(不是HTTP)
    --restart          -s        重启ioHTTP(ioBlog也会重启)
    --admin            -a        管理员操作面板
    --no-panel         -p        禁用前端面板，即/panel
    --help             -h        显示本帮助
    --version          -v        显示ioBlog版本号
    --allow-root       -A        允许ROOT运行ioBlog且无任何提示
    --quiet            -q        安静模式
    --daemon           -d        以后台进程启动ioHTTP
    --thread=[mode]              强制以指定线程模式启动，可以是1=>按需,2=>全部阻塞,3=>端口复用型多个监听
    --process=[count]            强制以多进程模式启动。危险！
    --test             -t        设置为debug模式，即强制单进程、单线程
    --no-plugin        -n        不加载插件，即安全启动

Powered by imzlh(C) http://imzlh.top/project MIT Licnse
EOF;
        },
        'admin' =>  function(){
            include _lib.'/panel/cli.php';
        },
        // 启动
        'start' =>  function(){
            if(file_exists(_.'.pid') and 
                !cli::getChoice('[  WAR  ] 检测到ioBlog PID文件，这可能是ioBlog异常退出或正在运行,是否继续?')
            ) exit(0);
            cli::checkRoot();             // 检查ROOT用户
            
            if(!file_exists(_conf)){      // 安装模式
                // 注意：安装模式无法使用后台模式
                include(_lib.'/install/run.php');      // 引入启动文件
            }else{
                include(_lib.'/plugin/event.php');     // 事件管理器
                include(_lib.'/plugin/config.php');    // 配置文件解析器
                config::init(_conf);                   // 先初始化配置
                include(_lib.'/post/init.php');        // 初始化文章
                file_put_contents(_.'.pid',getmygid());// 设置PID文件
                event::on('io/exit',function(){        // 设置退出事件
                    if(!_q) echo "[  ERR  ] ioBlog异常退出，清理PID...";
                    if(file_exists(_.'/.pid')) unlink(_.'/.pid');
                });
                register_shutdown_function(function(){  // 注册退出事件
                    event::call('io/exit');
                });
                if(function_exists('pcntl_signal')) 
                    pcntl_signal(SIGALRM,function(){    // 安装重载(reload)处理
                    event::call('io/reload');
                });

                event::call('io/start');                // 解析&调用启动事件

                // 前端管理面板&插件
                if(is_null(cli::get('no-plugin'))) include(_lib.'/plugin/init.php');
                if(is_null(cli::get('no-panel')))  include(_lib.'/panel/init.php');

                // 启动HTTP
                include(_lib.'/http/main.php');         // 底层文件
                http::init(_lib.'/http/mime.types');    // 初始化HTTP
                // 测试模式无视要求
                if(!is_null(cli::get('test'))) include(_lib.'/http/test.php');
                if(function_exists('pcntl_fork') and is_null(cli::get('process')))
                    include(_lib.'/http/multiprocess.php');
                elseif(class_exists('Thread'))
                    if(cli::get('process') === true)        // 没有指定模式
                        include(_lib.'/http/thread.php');
                    elseif(is_null(cli::get('thread'))){// 按照ID自动处理
                        switch((int)cli::get('thread')){
                            case 1:     // 模式1:按需启动线程处理
                                include(_lib.'/http/thread.php');
                                break;
                            case 2:     // 模式2:同时启动线程accept()，SOCKET协议分流
                                include(_lib.'/http/thread.m2.php');
                                break;
                            case 3:     // 模式3:同时启动线程监听同一个端口，内核分流
                                include(_lib.'/http/thread.m3.php');
                                break;

                            default:    // 不正确的ID
                                die('[  ERR  ] 模式错误!'.PHP_EOL);
                        }
                    }else{
                        die('[  ERR  ] 模式只能位数字！'.PHP_EOL);
                    }
                else                                    include(_lib.'/http/test.php');
            }
        },
        // 停止
        'stop'  =>  function(){
            // 部分系统不支持KILL，没办法
            if(!function_exists('posix_kill')) die('[  ERR  ] 你的PHP不支持主动杀死进程'.PHP_EOL);

            // 检查PID是否存在
            if(!file_exists(\c\root.'/.pid')) die('[  WAR  ] 似乎ioBlog未运行......'.PHP_EOL);
            if(($pid = (int)file_get_contents(_.'/.pid')) <= 0 ) die('[  ERR  ] 系统错误，错误的进程ID号'.PHP_EOL);

            // 尝试杀死
            try{
                posix_kill($pid,SIGTERM);       // 按照SIGTERM安全杀死进程
            }catch(Exception $e){
                die('[  ERR  ] 杀死进程失败,原因:'.$e.PHP_EOL);
            }

            echo '[  SUC  ] 杀死进程成功'.PHP_EOL;
        },
        // 平滑重载
        'reload'=>  function(){
            if(!file_exists(_.'/.pid')) die('[  WAR  ] 似乎ioBlog未运行......'.PHP_EOL);
            if(($pid = (int)file_get_contents(_.'/.pid')) <= 0 ) die('[  ERR  ] 系统错误，错误的进程ID号'.PHP_EOL);
            if(!function_exists('posix_kill')) die('[  ERR  ] 你的PHP不支持主动重载进程'.PHP_EOL);
            else posix_kill($pid,SIGALRM);          // 发送闹钟，提醒重载
        },
        // 重启
        'restart'=>function(){
            if(!file_exists(_.'/.pid')) die('[  WAR  ] 似乎ioBlog未运行......'.PHP_EOL);
            if(($pid = (int)file_get_contents(_.'/.pid')) <= 0 ) die('[  ERR  ] 系统错误，错误的进程ID号'.PHP_EOL);
            if(!function_exists('posix_kill')) die('[  ERR  ] 你的PHP不支持主动重起进程'.PHP_EOL);
            else pcntl_fork();
        }
    ] as $mode=>$exec){
        if(!is_null(cli::get($mode))) call_user_func($exec);
    }
?>