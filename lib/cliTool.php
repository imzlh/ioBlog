<?php
    /**
     * cliTool--在php-cli中霸道
     */

    class cli{
        // 鼠标历史位置
        static protected $mouse_history;
        // 参数列表
        static public $arg;

        /**
         * 解析传入参数。允许--key=value -abcde
         * 
         * @param array $args
         * @param array $allow_arg_list
         * @param bool $save
         * @return array
         */
        static public function parse(array $argv,array $arglist=[],bool $load = false):array{
            if(is_null(@$arglist['long']) or is_null(@$arglist['short'])) return [];
            $tmp = [];
            foreach($argv as $data){
                if(\preg_match('/--[a-z]+[=\w\W]./',$data)){     // --[长参数]
                    @list($n,$v) = \explode('=',\substr($data,2));
                    if(!\in_array($n,$arglist['long'])) echo ("[ WAR ] 未知的参数 $data\n");
                    else                               $tmp[ $n ] = $v ?? true; // 如果没有值则为true
                }elseif(\preg_match('/-[a-z]+/i',$data)){        // -[n个短参数]
                    for($i=1 ; $i<=\strlen($data)-1 ; $i++){
                        $c = $data[$i];                         // 逐个字母解析
                        if(!\array_key_exists($c,$arglist['short'])) echo "[ WAR ] 未知的参数[ $c ]在[ $data ]中\n";
                        else $tmp[$arglist['short'][$c]] = true; // 当作长命令启动
                    }
                }else{
                    die("[ ERR ] 未知的参数 {$data},解析失败!\n");
                }
            }
            if($load) self::$arg = $tmp;
            return $tmp;
        }

        /**
         * 读取参数
         * 
         * @param string $name
         * @return mixed
         */
        static function get(string $name){
            return @self::$arg[$name];
        }


        /**
         * 检查是否为ROOT用户启动。
         * 无需参数，若为ROOT
         */
        static function checkRoot():void{
            if(\getmyuid() == 0){
                // 如果指定了允许ROOT，跳过警告
                if(!is_null(self::get('allow-root'))) return;

                // 如果指定安静模式，输出尽量少
                if(!is_null(self::get('quiet'))){
                    echo '[ Q:WAR ] 建议不要以ROOT用户启动，按Ctrl+C终止运行，回车继续运行';
                    \fgets(STDIN);
                }else{
                    echo '[  WAR  ] 警告!你正在以ROOT权限执行程序,这十分危险!!!'.PHP_EOL;
                    // 如果允许输入则要求用户输入
                    if(self::getChoice('[ ALERT ] 若执行恶意插件会造成难以估计的损失,是否继续执行?(Y/N)')) echo "\n";
                    else exit();
                }
            }
        }

        /**
         * 获取一个字符，判断用户的意愿
         * 
         * @param string $before_wait_message
         * @return bool
         */
        static function getChoice(string $msg):bool{
            if(\function_exists('posix_isatty') and !\posix_isatty(STDIN)) {
                echo '[  ERR  ] 请以交互终端再次运行!'.PHP_EOL;
                return false;
            }else{
                echo $msg;
                echo ' >>> ';
                $str = fgetc(STDIN);
                fgets(STDIN);
                if($str == 'y' or $str == 'Y') return true;
                else return false;
            }
        }

        /**
         * 输出彩色文字。可选字段:color|bg|
         * 
         * @param array $data
         * @return void
         */
        static function print(array $info):void{
            // 文字颜色
            $color = [
                'black' =>  30,
                'red'   =>  31,
                'green' =>  32,
                'yellow'=>  33,
                'blue'  =>  34,
                'purple'=>  35,
                'dgreen'=>  36,
                'white' =>  37
            ][@$info['color']] ?? 43;
            // 文章背景色
            $bg = [
                'black' =>  40,
                'red'   =>  41,
                'green' =>  42,
                'yellow'=>  43,
                'blue'  =>  44,
                'purple'=>  45,
                'dgreen'=>  46,
                'white' =>  47
            ][@$info['bg']] ?? '';
            // 标签闭合
            $end = "\033[0m";
            $out = "\033[{$color};{$bg}";
            $done = false;
            foreach([
                'highlight'     =>  '1m',
                'underline'     =>  '4m',
                'blink'         =>  '5m',
                'invert'        =>  '7m',
                'hide'          =>  '8m'
            ] as $name=>$val){
                if(array_key_exists($name,$info)){
                    $out .= ";$info";
                    $done = true;
                    break;
                }
            }
            if(!$done) $out .= 'm';
            echo "$out{$info['str']}$end";
            if(@$info['br']) echo "\n";
        }

        /**
         * 设置光标位置，(x,y)
         * 
         * @param int $x_offset
         * @param int $y_offset
         * @return voids
         */
        static function set(int $x = 0,int $y = 0):void{
            echo "\033[{$y};{$x}H";
        }

        /**
         * 保留光标原始位置
         */
        static function keep():void{
            echo "\033[s";   // 保存鼠标历史位置
            self::$mouse_history = true;
        }

        /**
         * 移动光标，注意是相对位置
         * 
         * @param string $toward
         * @param int $offset
         * @return void
         */
        static function move(string $type,int $offset):void{
            echo [
                'top'       =>  "\033[{$offset}A",
                'bottom'    =>  "\033[{$offset}B",
                'right'     =>  "\033[{$offset}C",
                'left'      =>  "\033[{$offset}D"
            ][$type];
        }

        /**
         * 恢复原来的位置
         * 
         * @return bool
         */
        static function recover():bool{
            if(!self::$mouse_history) return false;
            self::$mouse_history = false;
            echo "\033[u";
            return true;
        }

        /**
         * 清屏
         * 
         * @return void
         */
        static function clean(bool $end = false):void{
            echo $end?"\033[K":"\033[2J";
            echo "\n";
        }

        /**
         * 设置光标状态(是否显示)
         * 
         * @return void
         */
        static function mouse(bool $show = true):void{
            echo $show?"\033[?25h":"\033[?25l";
        }

        /**
         * 设置一个选择框
         */
        static function choose(array $opt,string $title = '',bool $important = false):int{
            $all = count($opt);
            if($opt <= 1 ) return $opt[0];
            self::clean();
            while(True){
                self::set(0,0);
                echo '==============='.$title.'==================='.PHP_EOL;
                foreach($opt as $i=>$o) echo ($i+1).") $o\n";
                echo "请选择项目\n >>> ";
                $set = (int)fgets(STDIN);
                if($set > $all or $set <= 0) echo '超界,请重试!';
                elseif($important){
                    self::set(0,$set+1);
                    cli::print([
                        'color' =>  'black',
                        'bg'    =>  'white',
                        'str'   =>  "$set) {$opt[$set-1]} "
                    ]);
                    if(self::getChoice(' => 确定(y/n)?')) return $set;
                }else return $set;
            }
        }

        /**
         * 多个select混合
         */
        static function select(array $table,array $arg = []){
            $do = [$table];   // 这是一个层次表，一级一级堆上去
            while(true){
                $now = end($do);            // 当前需要应付
                $key = array_keys($now);    // 列
                array_unshift($key,'上一级');// 插入退出
                $choose = self::choose($key);// 选择的ID
                if($choose == 1) {
                    if(count($do) == 1) self::show('没有上一级了...',0);
                    else array_pop($do);        // 上一级:将最后一个删掉
                }else{
                    $c = $now[$key[$choose-1]];  // 向上堆
                    if(is_callable($c)){        // 是函数
                        echo ":";
                        print_r(call_user_func_array($c,$arg));
                        sleep(3);
                    }elseif(is_array($c)){      // 是数组：继续堆
                        $do[] = $c;             // 堆上去
                    }else{
                        echo ":";
                        print_r($c);
                        sleep(3);
                    }
                }
            }
        }

        /**
         * 显示信息
         */
        static function show(string $msg,int $y = 2,int $sleep = 3,string $color = 'red'):void{
            cli::set(0,$y);
            cli::print([
                'bg'     =>  $color,
                'color'  =>  'black',
                'str'    =>  " $msg "
            ]);
            sleep($sleep);
            cli::set(0,$y);
            $c = strlen($msg);
            for($i=1 ; $i<=$c ; $i++) echo ' ';
            cli::recover();
        }
    }

    /**
     * 进度条工具，默认为白色
     */
    class cliProcess{
        private $now = 0;       // 已经有多少了
        private $width;         // 窗口宽度

        /**
         * 初始化屏幕宽度
         */
        public function __construct(){
            if(preg_match('/^(windows)([ a-z]+)$/i' , php_uname('s'))){
                $line = `mode`;
                if(!preg_match('/CON.*:(\n[^|]+?){3}(?<cols>\d+)/', $line, $matches)) $this->width = 100;
                else $this->width = $matches['列'] ?? $matches['cols']; // 真无语，语言差异
            }else{
                $line = (int)`tput cols`;
                if($line < 0) $this->width = 100;
                else          $this->width = $line;
            }
            echo "\033[47m";                      // 定义全白底
        }

        /**
         * 设置进度，必须小于等于1(100%)
         * 
         * @param float $process
         * @return void
         */
        public function set(float $process):void{
            if($process > 1.0) return;
            $p = ceil($process * $this->width) - $this->now; // 读取该写多少
            $this->now += $p;                                // 定义写了多少
            for($i = 0 ; $i < $p ; $i ++) echo " ";          // 输出空格
            if($process == 1) echo "\033[0m ";              // 恢复默认表示
        }
    }

    /**
     * 轻量级安装程序
     */
    class setup{
        /**
         * 要安装的app名称
         */
        public $app = '';
        /**
         * tab序列
         */
        public $tab = [];
        /**
         * 安装内容
         */
        public $content = [];
        /**
         * 用户回应
         */
        public $reply = [];
        /**
         * 是否完成
         */
        private $complete = false;

        /**
         * 批量初始化
         */
        function __construct(array $opt){
            $this->app = $opt['app'];
            $this->tab = array_keys($opt['content']);
            $this->content = $opt['content'];
            $this->reply = @$opt['default'] ?? [];
        }

        /**
         * 启动
         */
        function run():void{
            cli::clean();
            // 第一页
            $this->switch(0);
            $this->print(0);
        }

        /**
         * 切换tab
         */
        private function switch(int $tab):void{
            cli::clean();       // 清除
            cli::set(0,0);
            cli::print([
                'bg'    =>  'white',
                'color' =>  'black',
                'str'   =>  "                     {$this->app}                        "
            ]);
            cli::set(0,2);      // 回到最初位置
            $tab ++;            // 自增
            echo "\033[47m";    // 固定白底
            for($i = 0 ; $i < $tab ; $i++) echo '  '.$this->tab[$i].'  ';
            echo "\033[0m";     // 恢复原样
            for(; $i < count($this->tab) ; $i++) echo '  '.$this->tab[$i].'  ';
            echo PHP_EOL;
        }

        /**
         * 解析那一页内容
         */
        private function print(int $tab){
            $c = $this->content[$this->tab[$tab]];// 该页内容
            $k = array_keys($c);        // 按ID索引
            $ii = 0;
            foreach($c as $i => $n) {
                if(!is_string($n)) continue;
                echo ($ii<10?' ':'')."$ii) $n >>> ".@$this->reply[$i].PHP_EOL;
                $ii ++;
            }
            echo '-2) 完成，下一步'.PHP_EOL;
            echo '-1) 上一步'.PHP_EOL;
            echo "哪一项需要修改? >>> ";
            $maxLen = count($k);
            cli::keep();
            while(true){
                cli::keep();
                $r = (int)fgets(STDIN);
                if(-1 == $r) {
                    if($tab == 0) cli::show('没有上一阶了...',1);
                    else return $this->print($tab -1);
                }elseif(-2 == $r) {
                    if($tab+1 == count($this->tab)) return cli::show('完成啦!',1,color:'green',sleep:1);
                    else {$this->switch($tab +1);return $this->print($tab +1);}
                }elseif($r < $maxLen){
                    $ti = strlen(@$c[$i = @$k[$r]]);      // 前面长度
                    if(!is_null($ti)) {
                        cli::set($ti+8,3+$r);             // 设置位置
                        $res = fgets(STDIN);
                        $this->reply[$i] = str_replace(PHP_EOL,'',$res);
                    }
                    cli::recover();                     // 回恢复原来的位置
                }else{
                    cli::show('输入超界...',2);
                }
            }
        }
    }
?>