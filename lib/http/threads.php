<?php
    /**
     * 单进程多线程HTTP服务器
     * /server/http.php : ioBlog模块
     * 2023 iz copyright(C)
     */
    if(!class_exists('Thread')) die('PHP多线程模块未找到！请按照https://imzlh.top/2023/01/01.php安装!');
    else                        echo '[ MSG ] HTTP以多线程模式(按需)启动，模式代码T1.'.PHP_EOL;

    $tC = 0;

    // 创建多线程。此类没有多余用处
    class response extends \Thread{
        private $tC,$e;            // 线程ID&线程应付的客户端

        // 存储信息
        public function __construct($client,int $id){
            if($e === false){
                echo "[ WAR ] 连接断开，线程{$id}即将销毁！".PHP_EOL;
                $this->interrupt();         // 退出线程
            }else{
                $this->e = $client;
                $this->tC = $id;
            }
        }

        // 开始接受请求
        public function run(){
            (new parentResponse($this->e))->run();
        }

        // 销毁时将总线程池数量减1
        function __destruct(){
            echo "[ MSG ] 线程{$this->pC}使命结束，即将销毁。\n";
        }
    }
?>