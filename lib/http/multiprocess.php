<?php
    /**
     * ioHTTP 多进程程版本
     */
    if(!\function_exists('pcntl_fork'))     die(  '[  ERR  ] 多进程扩展pcntl未找到！请百度安装......'.PHP_EOL);
    elseif(config::get('http','task') <= 1) print('[  ERR  ] 多进程模式下进程数量不得小于2，否则请使用test模式') and include(__DIR__.'test.php');
    elseif(!_q)                             echo  '[  MSG  ] 以多进程模式启动'.PHP_EOL;

    $pids = [];
    $n = is_null(cli::get('daemon'))?0:1;
    // 创建子线程
    for($i = 0 ; $i < \config\tasks-$n ; $i ++){
        $pid = pcntl_fork();        // 创建新进程，从这里开始运行
        if ($pid == -1) {           // 创建失败
            echo '[  ERR  ] 致命错误！无法创建子进程！';
            echo \pcntl_strerror(pcntl_get_last_error());
            die(PHP_EOL);
        }elseif($pid){              // 这是父进程，还需要检查其他进程窗体
            \array_push($pids,$pid); // 放入子进程列表
            \pcntl_signal(\SIGTERM, function(){
                echo '[  WAR  ] 子进程退出'.PHP_EOL;
                // TODO
            }, false);
        }else{                      // 这是子进程，管好自己即可
            // 子线程没有HTTP监听，得重新解析文章
            // const __children_path = __DIR__;    // 定义起始目录
            define('__children_path',__DIR__);
            $argv = [ '-sAt' ];                 // 伪装参数:启动+允许ROOT+强制test模式
            include_once(__DIR__.'/../../run.php');// 导入入口文件
            \cli_set_process_title('ioBlog WorkProcess');// 设置标题
        }
    }

    // 接下来如果是daemon则退出，不是则继续
    if(is_null(cli::get('daemon'))) include(__DIR__.'/test.php');
    else exit(0);
?>