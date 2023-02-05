<?php
    /**
     * HTTP服务器
     */

    /**
     * 处理基础HTTP任务的类
     * 快速使用：bind([port])();
     */
    class http{
        // 状态解释
        static public $status = [     // HTTP状态对应描述
            100 => 'Continue',
            200 => 'OK',
            206 => 'Partial Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            505 => 'HTTP Version not supported',
        ];
        // 默认响应头
        static public $default = [   // 默认HEADER，可以更改
            'Server'        =>  'ioBlog V1',
            'Content-Type'  =>  'text/html ;charset=UTF-8',
            'Connection'    =>  'colse'
        ];
        // MIME列表，通过init()初始化
        static public $mime = [];
        // 错误页
        static public $ePage = '{status}{message}';

        // SESSION存储器
        static private $session = [];

        /**
         * 读取SESSION
         */
        static function getSession(string $url , string $table):mixed{
            if(!array_key_exists($url,self::$session)) return false;
            list($n,$v) = explode('/',$table);
            return @self::$session [$url] [$n] [$v];
        }

        /**
         * 写入SESSION
         */
        static function setSession(string $url , string $table , mixed $val):void{
            if(!array_key_exists($url,self::$session)) self::$session[$url] = [];
            list($n,$v) = explode('/',$table);
            if(is_null($n) or is_null($v)) return;
            @self::$session [$url] [$n] [$v] = $val;
        }

        /**
         * 解析真实域名
         */
        static function getUrl(string $url){
            $pos = strrpos($url,':');
            return substr($url,0,$pos);
        }

        /**
         * 初始化MIME列表
         *  
         * @param string $mime_file_name
         * @return void
         */
        static public function init(string $f):void{
            $_ = [];
            foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $c) {
                if (\preg_match('/\s*(\S+)\s+(\S.+)/', $c, $match)) {
                    list(,$m,$e) = $match;
                    foreach (explode(' ',substr($e, 0, -1)) as $fe) $_[$fe] = $m;
                }
            }
            self::$mime = $_;
        }

        /**
         * 设置默认错误页面，指向文件
         * 
         * @param string $file_path
         * @return bool
         */
        static function setePage(string $file):bool{
            if(!file_exists($file)) return false;
            self::$ePage = file_get_contents(file_get_contents($file),maxlen : 1024*1024);
        }

        /**
         * 绑定端口并且开始监听
         * 
         * @param ?int $http_port
         * @param ?int $nettype
         * @param ?bool $use_https
         * @return callable
         */
        static public function bind(int $port=80,int $type = 6,int $max = 8,bool $https = false):callable{
            $ip = $type == 4 ? '0.0.0.0' : '[::]';              // 自动决定监听类型
            $e  = @stream_socket_server("tcp://{$ip}:{$port}",$code,$str);  // 创建服务
            if($e === false) {
                echo "[  ERR  ] http:无法监听(E:$code)!原因:[$str]\n";
                return false;
            }else{
                stream_set_blocking($e,true);
                \stream_set_read_buffer($e, 0);
                stream_context_set_params($e,[
                    'backlog'       =>  $max,
                    'ipv6_v6only'   =>  false,
                    'so_reuseport'  =>  true,
                ]);
                return $https ? function(bool|callable $run = true,string $cer,string $key) use ($e){
                    if(!extension_loaded('openssl'))
                        die("[  ERR  ] 你的PHP不支持HTTPS，请安装OPENSSL扩展！\n");
                    // 启用TLS V1.3，>=PHP8 ,OPENSSL >= 1.0
                    stream_socket_enable_crypto($e,true,STREAM_CRYPTO_METHOD_TLSv1_3_SERVER);
                    stream_context_set_option($e,[
                        'local_cert'=>$cer,        // 证书
                        'local_pk'=>$key,           // 私匙
                    ]);
                    // 下面都和普通HTTP一样
                    if(is_bool($run) and $run==false)       return $e;
                    $call = is_callable($run) ? $run : 'rounger::run';
                    while(($c = @stream_socket_accept($e,3600,$ip)) !== false){
                        // 预处理
                        stream_set_timeout($c,1);       // 防止阻塞
                        new response($c,$call,$ip);
                    }
                } :function(bool|callable $run = true) use ($e){
                    if(is_bool($run) and $run==false)       return $e;
                    $call = is_callable($run) ? $run : 'autorun';
                    while(($c = @stream_socket_accept($e,3600,$ip)) !== false){
                        // 预处理
                        stream_set_timeout($c,1);       // 防止阻塞
                        new response($c,$call,$ip);
                    }
                };
            }
        }

        /**
         * 提取上传的文件的信息，返回name、type、length
         * 
         * @param array $file
         */
        static function getUpload(array $file):false|array{
            if(!array_key_exists('content-type',$file)) return false;
            $type = self::parse($file['content-type'],'type');     // 包含类型、编码等
            if(array_key_exists('content-disposition',$file)) {
                $file = self::parse($file['content-disposition'],'from');// 包含方式、名称等
                return array_merge($type,$file,['encode'=>@$file['content-transfer-encoding']]);
            }else{
                return $type;
            }
        }

        static function parse(string $info,string $firstName = 'info'):array{
            $data = explode(';',$info);
            $temp = [];
            $temp[$firstName] = trim(array_shift($data));
            
            foreach($data as $v){
                $v = trim($v);
                list($name,$value) = explode('=',$v,2);
                if(substr($value,0,1) == '"')   // 带有冒号 
                    $value = substr($value,1,strlen($value)-2);
                $temp [ $name ] = $value;
            }
            return $temp;
        }

    }

    /**
     * 处理任务的类，详细使用方法可以看下面的源码
     */
    class parentResponse{

        private 
            $socket,                // 存储socket对象
            $done = false,          // 是否完事   
            $time = 0.0,            // 性能测试：消耗的时间 
            $cookies = [];          // COOKIE设置需要很多行

        public  
            $headers = [],          // 存储响应头
            $requestHeader = [],    // 客户端请求头
            $param = [],            // POST/GET参数
            $status = 200,          // 响应状态
            $temp = '',             // 缓冲区
            $file = [],             // 存储上传的文件
            $path,                  // 访问路径
            $type;                  // HTTP类型

        /**
         * 初始化一个父响应对象，此时会自动开始运行。此函数将阻塞直至请求完毕
         * 
         * @param resource $stream_socket
         * @param callable $callback
         * @param ?string $remote_ip 
         * @return void
         */
        public function __construct($c,callable|string $call,?string $ip = null){
            if($c === false)     return;

            // 用于统计响应时间
            $this->time = microtime(true)*1000;

            // 定义当前socket信息(IP)
            $this->socket = $c;
            $this->ip = $ip = $this->requestHeader['remote_ip'] = $ip ?? stream_socket_get_name($c,true);

            // 读取基础信息，若非3个参数、非GET、POST响应拦截
            @list($type,$path,$version) = explode(' ',fgets($c),3);
            if(is_null($type) or is_null($path) or is_null($version)) {
                return $this->filter(400,'[ ioHTTP ] Bad header');
            }elseif($type != 'GET' and $type != 'POST'){
                return $this->filter(405,'[ ioHTTP ] Unsupport HTTP protocol!');
            }
            // 处理URL参数，原先的PREG太慢了，改用查询"?"
            // if(\preg_match('/^\/[^\?]*\?([a-zA-Z0-9_]+[=&][\w\W]+)+$/',$path)) {
            if(strpos($path,'?') != false){
                list($path,$param) = \explode('?',$path,2);
                $this->param = self::parseParam($param);
            }
            $this->path = $path;
            $this->type = $type;
            $this->addr = $ip;
            // 输出消息
            echo _q ? "[ QHTTP ] $type | $ip | $path |" : "[  MSG  ] 新用户连接 | :$type | IP $ip || 路径 $path ||";
            // 开始处理消息
            try{
                call_user_func($call,$this); 
            }catch(Exception|TypeError $e){
                $this->filter(500,str_replace("\n",'<br>',(string)$e));
            }
            
        }

        /**
         * 销毁时输出的内容
         */
        public function __destruct(){
            if(false == $this->socket) return;     // 过滤关闭的连接

            // 如果是强制关闭的，则输出错误信息，否则浏览器会连接重置
            if(!$this->done) $this->filter(500,'[ 500 ] Server Error.<br>Nothing has been done.<br><b>Server</b>ioHTTP');
            
            // 统计响应时间
            echo " {$this->status} in ".(int)(microtime(true)*1000 - $this->time)." ms\n";
        }

        /**
         * 拦截一个请求
         * 
         * @param int $status_code
         * @param string $error_message
         * @return void
         */
        public function filter(int $code = 400,string $msg = '您的操作被拦截'):void{
            $d = @http::$status[$code] ?? 'Unexpect Error';
            $this->finish($msg,$code);
        }

        /**
         * 输出内容并结束请求
         * 
         * @param ?string $end_message
         * @param int $http_status_code
         * @return void
         */
        public function finish(string $m = '',int $status = 200):void{
            if($this->done)             return;         // 已经完成请求那么不能再写数据
            $this->mkHeader($status,['Content-Length'=>strlen($m)+strlen($this->temp)]);// 写HTTP响应头
            fwrite($this->socket,$this->temp);          // 写缓存的内容
            fwrite($this->socket,$m);                   // 写追加的内容
            stream_socket_shutdown($this->socket,STREAM_SHUT_RDWR);// 安全关闭连接
            $this->done = true;                         // 使命结束
        }

        /**
         * 设置响应状态码
         * 
         * @param int $http_status_code
         * @return void
         */
        public function status(int $http_status_code):void{
            $this->status = $http_status_code;
        }

        /**
         * 解析请求参数
         * 
         * @param string $param
         * @param ?string $split_string
         * @return array
         */
        public function parseParam(string $param,string $split = '&'):array{
            $tmp = [];
            foreach(\explode($split,$param) as $_) {
                list($name,$value) = \explode('=',$_,2);
                $tmp[urldecode(trim($name))]=\urldecode(trim($value));
            }
            return $tmp;
        }

        /**
         * 读取指定文件的MIME类型
         * 
         * @param string $file_name
         * @return string
         */
        public function getMime(string $file):string{
            $ext = @end(explode('.',$file));             // 截取末尾扩展名
            return http::$mime[$ext] ?? 'application/octet-stream';
        }

        /**
         * 解析当前请求的请求头，如第一个参数是false将不保存到缓存，用于忽略
         * 
         * @param ?bool $save
         */
        public function parserHeader(bool $save = true):array {
            $tmp = ['parsed'=>true];
            $len = 0;
            while(true) {
                if(($str = fgets($this->socket)) == PHP_EOL) break;
                $len += strlen($len);
                $str = trim($str);
                @list($name,$value) = \explode(':',$str,2);
                $tmp [ \strtolower($name) ] = trim($value);
                
            }
            $tmp['_length'] = $len;
            if($save === true) $this->requestHeader = $tmp;
            return $tmp;
        }
        
        /**
         * 创建响应头
         * 
         * @param ?int $status_code
         * @param ?array $add_header
         * @return void
         */
        public function mkHeader(?int $status = null , $header = []):void {
            if($this->headers === false) return;
            $status = $status ?? $this->status;
            $desc = http::$status[$status]??'Unexpected Error';
            $tmp = "HTTP/1.1 $status $desc";
            foreach(\array_merge(http::$default,$this->headers,$header,[
                'Date'=>\gmdate('D, d M Y H:i:s T')
            ]) as $n=>$v) {
                $tmp .= "\r\n$n: $v";
            }
            foreach($this->cookies as $cookie){
                $tmp .= "\r\nSet-Cookie: $cookie";
            }
            \fwrite($this->socket,$tmp."\r\n\r\n");
            $this->headers = false;
        }
        
        /**
         * 设置响应头
         * 
         * @param string $header_name
         * @param string $header_value
         * @return void
         */
        public function setHeader(string $name,string $value):void {
            $this->headers[$name] = $value;
        }

        /**
         * 获取已经设置的响应头，不存在返回NULL
         * 
         * @param string $name
         * @return string|null
         */
        
        public function getHeader(string $name):string|null {
            return @$this->requestHeader[$name];
        }

        /**
         * (带r的表示request，请求标头。注意默认不会解析请求标头(为了速度))
         * 获取已经设置的响应头，不存在返回NULL
         * 
         * @param string $name
         * @return string|null
         */
        public function getrHeader(string $name):string|null {
            if(\count($this->requestHeader) == 0 or @$this->requestHeader['parsed']!==true)
                $this->parserHeader();
            return @$this->requestHeader[$name];
        }

        /**
         * 解析请求头的末尾位置，一般是POST数据，不存在返回NULL
         * 
         * @return string|null
         */
        public function parseEndParam():void {
            if($this->type != 'POST') return;
            // if(\preg_match('/([1-9a-zA-Z_]+\=[\w\W^&]+\&?)+/',$t)) {// 匹配a=b&c=d这类
            // if(is_numeric(strpos($t = fgets($this->socket),'&'))){
            // if(strpos($this->getrHeader('content-type'),'application/x-www-form-urlencoded') !== false){
            if(substr($this->getrHeader('content-type'),0,33) == 'application/x-www-form-urlencoded'){
                // echo fgets($this->socket);
                $this->param['post'] = $this->parseParam(fgets($this->socket)); 
            // } elseif(preg_match('/^multipart\/form-data[ ;]+boundary=[\-0-9]$/i',$tmp = $this->getrHeader('content-type'))) {
            }elseif(substr($this->getrHeader('content-type'),0,19) == 'multipart/form-data'){
                $split = '--'.\substr($this->getrHeader('content-type'),
                    strpos($this->getrHeader('content-type'),'boundary=') + 9
                );// 读取分界符
                if($split.PHP_EOL != ($f = fgets($this->socket))) {
                    echo "[  ERR  ] POST出错：分界符不符合!原分界符:$split,传递的分界符:$f\n";
                    $this->filter(400,'POST BOUNDARY DOES NOT MATCH!');
                    return;
                }
                $len = strlen($f);                         // 统计最终数据
                // if($split != ($f = trim($f)) ) {        // 第一行检查是否正常
                //     if(is_numeric($n = strpos($split,$f)) or is_numeric($n = strpos($f,$split))) {
                //         echo _q?"[  WAR  ] 正确的分界符偏移了{$n}个字节\n":'*';
                //         $split = $f;
                //     }else{
                //         $this->filter(400,'错误的文件上传');
                //         if(!_q) print("[  ERR  ] 解析POST文件时出现了问题，分界符不符合!原分界符:$split,传递的分界符:$f\n");
                //         return ;
                //     }
                // }
                // 保存文件到缓冲区
                $tempPath = sys_get_temp_dir()??_;          // 使用系统暂存区，可以更改
                $maxLen = (int)$this->getrHeader('content-length');// 最终数据大小
                if(!_q) echo "\n开始接收POST文件 总大小:$maxLen \n";
                $start = time();
                if(!_q) $proc = new cliProcess();
                stream_set_timeout($this->socket,1);
                for ($i = 0 ; $i < $this->getrHeader('fileupload-count')??8 ; $i ++) {     // 最大允许同时上传8个文件
                    if($len >= $maxLen) break;
                    
                    $tmp = $this->parserHeader(false);// 由于文件信息与请求头很像所有直接调用
                    $len += $tmp['_length'];        // 这里卡了很久，忘记这些头也有长度
                    $file = \fopen(                 // 打开文件，其实说实在的保存到RAM也一样
                        $tmp['savePath'] = $tempPath . '/' . \md5(\rand(10,1000)) // 随机文件名
                    ,'w');
                    $data = '';
                    while(true) { // 未到分界处
                        $data = \fgets($this->socket);              // 每次读取32K数据
                        if($data === false) break ;
                        $len += strlen($data);                      // 统计读取的数据
                        if($split.PHP_EOL == $data) break;
                        fwrite($file,$data);           // 循环写到文件
                        if(!_q) $proc->set($len/$maxLen);// 设置进度条，这会消耗CPU，不过很少用到
                    }
                    fclose($file);
                    // 到了分界处，保存文件
                    array_push($this->file,$tmp);       // 保存到files
                }
                echo "\n接收完毕，总文件数$i,接收总大小$len,耗时".time() - $start." S\n";
            } else {
                $this->setHeader('status',400);
                if(!_q) echo "[  WAR  ] 客户端传输了未知的格式\n";
            }
        }

        /**
         * 获取一个URL参数，如果是POST的数据请第三个参数为true
         * 
         * @param string $param_name
         * @param bool $is_post
         * @return null|string
         */
        public function getParam(string $param,bool $type_post = false):string|null {
            if(is_null(@$this->param['post'])) $this->parseEndParam();
            return $type_post?@$this->param['post'][$param]:@$this->param[$param];
        }

        /**
         * 获取一个URL Cookie，客户端传来的。
         * 【注意】这并不安全，如果想安全使用getSession、setSession
         * 
         * @param string $get_cookie_name
         */
        public function getCookie(string $cookie):string|null {
            if(is_null($this->getrHeader('cookie'))) return null;
            return @$this->parseParam($this->getrHeader('cookie'),';')[$cookie];
        }

        /**
         * 写一个COOKIE
         * 
         * @param string $cookie_name
         * @param string $cookie_value
         * @param ?int $cookie_timeout_second
         * @param ?bool $allow_children_domain_use 
         * @param ?bool $allow_children_path_use
         */
        function setCookie(string $n,string $v,?int $t=null,bool $ajsu = false,bool $acdu = false,bool $acpu = true){
            $e = "$n=$v; ";
            if(!is_null($t)) $e .= "Max-Age=$t; ";  // 失效时间
            if($acdu) $e .= 'Domain='.$this->getrHeader('host').'; ';
            if($acpu) $e .= 'Path=/; ';
            if($ajsu) $e .= 'HttpOnly; ';
            $this->cookies[] = $e;
        }

        /**
         * 获取安全存储区的内容（session）
         * 注意了！不能保证绝对安全
         * 
         * @param string $get_session_name
         * @return mixed
         */
        public function getSession(string $session){
            $ip = http::getUrl($this->ip);
            if(is_null($sessid = $this->getCookie('__token'))) return null;
            return http::getSession($this->getrHeader('host'),"{$ip}/{$sessid}_{$session}");
        }

        /**
         * 写入安全存储区内容
         * 
         * @param string $set_session_name
         * @param mixed $value
         * @return void
         */
        public function setSession(string $session,$value){
            $ip = http::getUrl($this->ip);
            if(is_null($sessid = $this->getCookie('__token'))) 
                $this->setCookie('__token',$sessid = md5(rand(0,99999999)),acpu:true);
            http::setSession($this->getrHeader('host'),"{$ip}/{$sessid}_{$session}",$value);
        }

        /**
         * 处理一个状态码，如404
         * 
         * @param int $status_code
         * @param int $status_desc
         * @return false
         */
        function deal(?int $status = null,string $msg = '未知错误'){
            $this->finish(str_replace(
                ['status'                   , 'message'],
                [$status ?? $this->status   , $msg],
                http::$ePage
            ),$status);
        }

        /**
         * 缓存一些数据
         * 
         * @param string $str
         * @return void
         */
        function say(string $str):void{
            $this->temp .= $str;
        }
        
        /**
         * 输出一个文件。若指定offset为true将允许客户端断点续传
         * 【警告】若使用TEST模式，限速只会大大降低并发。
         * 不过可以减少带宽太小导致缓冲区挤满，可以设置为服务器的实际带宽
         * 
         * @param string $file_path
         * @param string $use_offset
         * @return bool
         */
        function file(string $file_name,bool|int $offset = false,int $limit = 1*1024*1024,int $age = 3600):bool{
            if(!is_file($file_name) or !is_readable($file_name)){
                $this->deal(404,'File not exists!');
                return false;
            }
            // 判断是否客户端是最新的文件，是的话返回304告诉客户端不用传了
            if($offset and !is_null($this->getrHeader('if-modified-since'))){
                $uv = strtotime($this->getHeader('if-modified-since')); 
                if($uv == filemtime($file_name)) {
                    $this->status(304);
                    return $this->done = true;
                }
            }
            // 先决定文件类型等
            $name = basename($file_name);       // 实际名称
            $ext  = $this->getMime($name);      // MIME类型
            // 决定是否内联，如果参数里内置download则直接下载
            $type = $ext == 'application/octet-stream' and is_null($this->getParam('download'))?'inline':'attachment';
            $this->setHeader('Content-Transfer-Encoding','binary');
            $this->setHeader('Content-Disposition',"$type;filename=$name");
            $this->setHeader('Content-Length',filesize($file_name));
            $this->setHeader('Content-Type',$ext);
            $this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s T',filemtime($file_name)));
            // $this->setHeader('Cache-Control', "max-age=$age, immutable");
            $this->mkHeader();
            $f = @fopen($file_name,'rb');
            if(is_numeric($offset)) fseek($f,$offset);
            elseif($offset == true) fseek($f,(function($self){
                $range = $self->getrHeader('range');
                if(is_null($range)) return 0;
                $start = (int)$range;       // 起始位置
                $end   = (int)substr($range,strpos('-',$range)+1);
                $self->status(206);         // 开始断点续传
                echo "断点续传模式 $start -> $end";
                return $start;
            })($this));
            while(true){
                fwrite($this->socket,fread($f,$limit));
                if(feof($f)) break; else sleep(1);  // 尽力降低危险
            }
            return $this->done = true;
        }

        /**
         * 使用GZIP进行压缩，这样会减小网页体积
         * GZ压缩级别可以为0~9，-1请绕道使用参数一致的finish
         * 
         * @param string $will_compressed_string
         * @param int $http_status_code
         * @param int $gz_compress_level
         * @return void
         */
        function useGZip(string $str,int $status = 200,int $level = 6):void{
            // 不存在zlib或者压根客户端不支持GZ压缩
            if($level = -1 or !extension_loaded('zlib') or stripos($this->getrHeader('accept-encoding')) === false) {
                $this->finish($str,$status);
            }else{
                if($this->headers === false) return;
                $str = gzencode($str,$level);   // 快速压缩
                $this->mkHeader($status,[       // 设置响应头
                    'Content-Encoding'  =>  'gzip',
                    'Vary'              =>  'Accept-Encoding',
                    'Content-Length'    =>  strlen($str)
                ]);
                fwrite($this->socket,$str);
            }
        }

        /**
         * 针对HTML压缩减小体积，比GZ压缩更快且更有效
         * 
         * @param string $will_compressed_string
         * @param int $http_status_code
         * @return void
         */
        function useCompress(string $str,int $status = 200):void{
            $this->finish(preg_replace([
                    // 过滤标记中空格    过滤HTML注释       过滤JS注释            过滤CSS注释
                    '/> *([^ ]*) *</', '/<!--[^!]*-->/', '/^"?\/\/[^\n]+/' , '/\/\*[^(\*\/)]+\*\//','/[\s]+/'   
                ],[ '>\\1<'          , ''              ,''                   ,''                     , ' '],$str)
            ,$status);
        }
    }
?>