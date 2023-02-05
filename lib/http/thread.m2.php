<?php
    /**
     * ioHTTP 模型：多线程
     */

    $socket = http::bind(_config['port'],6,_config['maxTask'],_config['ssl'])
        (false,@_config['https_cert'],@_config['https_key']);

    for($i = 0 ; $i < _config['maxThread']-1 ; $i ++){
        (new response($socket))->run();  // 启动线程
    }

    $th = _config['maxTask'];
    echo "[  SUC  ] 成功创建{$th}个线程!如果你希望终止";

    class response{

        public $socket;

        /**
         * 使用默认接口
         */
        public function __construct($c){
            $this->socket = $c;
        }

        /**
         * 启动按需服务器
         */
        public function run(){
            while(($c = @stream_socket_accept($this->socket,3600,$ip)) !== false){
                stream_set_timeout($c,1);       // 防止阻塞
                new response($c,$call,$ip);     // 开始接受请求
            }
        }
    }
?>