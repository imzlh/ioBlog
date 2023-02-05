<?php
    /**
     * Cli 管理员工具
     */

    cli::clean();
    if(true !== cli::get('admin')) die(cp_vm::run(cli::get('admin')));
    echo " 
     _       ____  _             ____                  _
    (_) ___ | __ )| | ___   __ _|  _ \ __ _ _ __   ___| |
    | |/ _ \|  _ \| |/ _ \ / _` | |_) / _` | '_ \ / _ \ |
    | | (_) | |_) | | (_) | (_| |  __/ (_| | | | |  __/ |
    |_|\___/|____/|_|\___/ \__, |_|   \__,_|_| |_|\___|_|
                           |___/
欢迎来到ioBlog管理工具!
1) 半GUI管理
2) 虚拟终端模式
3) 安装模式
选择 >>> ";
    switch(substr(fgets(STDIN),0,1)){
        case 1:
            include_once _lib.'/plugin/config.php';
            config::init();
            cli::select([
                '文章&评论' =>  [

                ],
                '系统设置'  =>  [
                    '基础设置(config.json)' => [
                        '测试'  =>  function(){
                            $_ = str_replace(PHP_EOL,'',fgets(STDIN));
                            echo "$_:设置已保存...";
                        }
                    ],
                    '面板设置(panel/data.json)' =>  [
                        '用户信息(user)'    =>  [

                        ],
                        '邮箱访问(mail)'    =>  [

                        ],
                        '设置选项(opt)'     =>  [

                        ]
                    ],
                    '插件设置(plugin.json)' =>  [

                    ]
                ],
                '退出(EXIT)'    =>  [
                    '确定'  =>  function(){exit();}
                ]
            ]);
            break;

        case 2:
            echo "启动内建终端(Built in shell mode.)\n";
            while(true){
                echo ' >>> ';
                $_ = str_replace(PHP_EOL,'',fgets(STDIN));
                if($_ != '') cp_vm::run($_);
            }
            break;

        case 3:
            if(file_exists(_conf)){
                cli::print([
                    'bg'    =>  'black',
                    'color' =>  'red',
                    'str'   =>  '检测到(您)已经配置过了,再次配置将覆盖原有配置'
                ]);
                echo PHP_EOL;
                if(!cli::getChoice('确定继续?')) die('用户放弃配置');
            }
            // include_once __DIR__.'/plug.config.php';
            ($_ = new setup([
                'app'   =>  '安装程序',
                'default'   =>  [
                    'port'  =>  80,
                    'title' =>  'ioBlog',
                    'path'  =>  '/Y/m/d',
                    'ext'       => 'html',
                    'preview'   =>  40,

                ],
                'content'   =>  [
                    '1.HTTP设置'  =>  [
                        'port'      =>  'HTTP端口',
                        'thread'    =>  '服务线程',
                        'task'      =>  '服务进程',
                        'max'       =>  '最大单进程并发'
                    ],
                    '2.博客设置'    =>  [
                        'title'     =>  '博客名',
                        'desc'      =>  '博客描述',
                        'path'      =>  '文章路径',
                        'ext'       =>  '文章扩展名',
                        'preview'   =>  '预览长度'
                    ],
                    '3.插件设置'    =>  []
                ]
            ]))->run();
            break;

        default:
            die('我们不清楚你想做什么');
    }

    class cp_post{
        const name = '轻文章操作命令';
        const help = '文章管理器 V1
get:获取全局文章所有内容';

        static $post,$set;

        static function set(string|null $post){
            if(is_null($post)) echo "失败:请指定第二个参数";
            include_once _lib.'/post/class.php';
            if(!file_exists($_ = _post."/{$post}.md")){
                echo "错误:不存在指定的文章:{$post}\n";
            }else{
                self::$set = new post($_);
                echo "成功选择了文章,标题:".self::$set->data['title'].PHP_EOL;
            }
        }

        static function get(string $param = ''){
            if(is_null(self::$set)) return print('请先set设置文章!'.PHP_EOL);
            if($param == '') foreach(self::$set->data as $n=>$v) echo "$n\t\t$v\n";
            else echo (@self::$set->data[$param] ?? $param.'不存在哦:(').PHP_EOL;
        }
    }

    class cp_blog{
        const name = 'ioBlog操作助手';
        const help = 'ioBlog操作，与直接启动一样
start : 启动';

        static function start(){
            if(!function_exists('pcntl_fork')) {
                echo '无法启动:缺少pcntl扩展';
                return 1;
            }
            $_ = pcntl_fork();
            if($_ < 0) {
                echo '失败:无法启动子进程处理任务';
                return 3;
            }
            elseif($_ > 0) {
                define('__children_path',__DIR__);
                $argv = [ '-sAt' ];                 // 伪装参数:启动+允许ROOT+强制test模式
                include_once(__DIR__.'/../../run.php');// 导入入口文件
            }else return true;
        }
    }

    class cp_vm{
        const name = 'VM执行器';
        const help = 'VM:执行命令类
run : 执行一条命令';
        static private
            $lastCode,  // 退出的状态码
            $lastErr;   // 执行的错误，可以用Exception抛出

        static function run(string $command):void{
            $arg = explode(' ',$command);
            $arg = str_replace('_',' ',$arg);
            $class = array_shift($arg);
            switch($class){
                case 'lastError':
                    if(!is_null(self::$lastErr)) echo $lastErr;
                    echo "没有错误.尝试 'lastCode' 获取退出状态\n";
                    break;

                case 'lastCode':
                    echo [
                        '执行成功/0',
                        '环境错误/1',
                        '参数错误/2',
                        '执行失败/3',
                        'PHP错误/4'
                    ][self::$lastCode ?? 0] ?? '错误/5';
                    echo PHP_EOL;
                    break;

                case 'exit':
                    die("再见\n");

                case 'clean':
                    cli::clean();
                    break;
                
                case 'help':
                    if(count($arg) == 0){
                        echo "ioShell V1 for ioBlog(CLI)\n请使用_代替空格!多个参数用空格隔开\n";
                        foreach(get_declared_classes() as $class){
                            if(substr($class,0,3) == 'cp_') echo substr($class,3)."\t||".$class::name.PHP_EOL;
                        }
                    }
                    foreach($arg as $c){
                        if(!class_exists($_ = 'cp_'.$c)) echo "(?) 找不到操作类{$c},试试看'help'?\n";
                        else echo "[[ $c ]] ".$_::help.PHP_EOL;
                    }
                    break;
                
                default:
                    if(!class_exists('cp_'.$class)) {
                        echo "未知命令'{$class}' 尝试'help'获取更多信息!\n";
                    }elseif('' == ($action = array_shift($arg))){
                        echo "缺少第二个参数(或者第一个参数存在错别字)\n";
                    }else{
                        try{
                            self::$lastCode = call_user_func_array("cp_{$class}::{$action}",$arg);
                        }catch(TypeError){
                            echo "命令不含有模式{$action}.尝试'help {$action}'获取完整命令列表\n";
                        }catch(Exception|Error $e){
                            echo "执行时出现错误:".$e->getMessage().".输入'lastError'获取更多内容\n";
                            self::$lastErr = (string)$e;
                        }
                    }
                    break;
            }
        }
    }
?>