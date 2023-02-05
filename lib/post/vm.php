<?php
    /**
     * PostVM 虚拟解析器
     * 事件：vmError/[key]       => VM找不到命令
     * 你可以利用这个事件处理自定义命令
     */
    
    class vm {
        /**
         * 虚拟变量库
         * 在PHP中用vm::var('name','value')
         * 在VM环境下用{#name=value}
         */
        static public $var = [];

        /**
         * 虚拟隔离函数库
         * 请使用vm::push()
         */
        static private $func = [];

        /**
         * VM预定义常量
         */
        const vm = [
            'version'   =>  '2.0.1',
            'date'      =>  '2021-1-27 19:54'
        ];
        /**
         * 将一个变量在VM环境定义
         * 
         * @param string $var_name
         * @param string $var_value
         * @return void
         */
        static function var(string $name,string $value):void{
            self::$var[$name] = $value;
        }

        /**
         * 定义一个VM函数
         * 
         * @param string $function_name
         * @param $function_or_functionNameString
         * @return void
         */
        static function push(string $fname,string|callable $callback):void{
            self::$func[$fname] = $callback;
        }

        /**
         * 运行虚拟解析器，用于将动态字段替换。
         * $head需要一个数组，在调用{!post.??}时会用到，可以传递空数组
         * $body为一个只差替换的HTML数据
         * $self用于处理client内容，当然不允许为空(代代传递)
         * 
         * @param array $post_head
         */
        static function run (array $head,string $body,\parentResponse|\response $self){
            \preg_match_all('/\{\{[\S\s]+?\}\}/',$body,$match);   // 将动态体提取出来
            foreach(\array_unique($match[0]) as $str){  // 将重复的剔除，循环下去
                $realstr    = substr($str,3,strlen($str)-5);    // 参数
                $func       = substr($str,2,1);                 // 方法名
                $_          = '';                               // 替换的内容
                if(preg_match_all('/\$[a-zA-Z1-9.]+/',$realstr,$match) != 0)
                    foreach(array_unique($match[0]) as $var){
                        $name = substr($var,1);
                        $realstr = str_replace($var,self::get($name,$self,$head),$realstr);
                    }
                switch($func){
                    case '%':   // 实验功能:简单函数
                        $args = explode(' ',$realstr);
                        $fname = array_shift($args);
                        try{
                            if(is_callable(self::$func[$fname])) $_ = call_user_func(self::$func[$fname],$self,$args,$head);
                            else $_ = event::call('vm/noMatch',[$self,$realstr,$head]);
                        }catch(Exception|TypeError $e){
                            echo "[ VMERR ] {$e->getMessage()} 在文件{$e->getFile()}第{$e->getLine()}行 \n";
                        }
                        break;

                    case '@':   // 判断:null合并
                        $_ = self::ifthen($realstr,$self,$head);
                        break;

                    case '?':   // 判断:是否为空
                        $_ = self::isnull($realstr,$self,$head);
                        break;

                    case '$':   // 获取变量
                        @list($n,$v) = explode('=',$realstr,2);
                        if(is_null($n)){
                            $_ = 'Syntax Error';
                        }elseif(is_null($v)){
                            $_ = self::get($realstr,$self,$head);
                        }else{

                            self::var($n,$v);
                            if($name == 'root' and is_dir(_.$v)) chdir(_.$v);
                        }
                        break;

                    case ':':  // 注释语句
                        break;

                    default:
                        echo '[  WAR  ] VM无法解析:语法错误:'.$str.PHP_EOL;
                }
                $body = str_replace($str,is_array($_)?implode($_):$_,$body);
            }
            return $body;
        }

        /**
         * 在主题中，你可以使用简单判断语法，如
         * {?opt.aabb:opt.aabb存在！}
         * 相当于：if(!is_null(opt.aabb)) echo opt.aabb存在
         * 
         * @param string $parse_string
         */
        static function isnull(string $parse_string,$self,$head){
            list($if,$str) = explode(':',$parse_string,2);
            $val = self::get($if,$self,$head);
            if(is_null($val) or $val == '') return $str;
        }

        /**
         * {@?:?}语法，提供null合并，如：
         * {@opt.aabb:不存在值，这是默认文字}
         * 相当于echo opt.aabb??'不存在值，这是默认文字'
         * 
         * @param string $parse_string
         */
        static function ifthen(string $parse_string,$self,$head){
            list($if,$str) = explode(':',$parse_string,2);
            $val = self::get($if,$self,$head);
            if(is_null($val) or $val == '') return $str;
            else return $val;
        }

        /**
         * 获取一个值，即{!??.!!.?!}
         * 
         * @param string $parse_string
         */
        static function get(string $name,$self,$head){
            @list($type,$key,$more) = \explode('.',$name,3);
            if(is_null($key)) return '';
            switch($type){
                // 日期选项
                case 'date':
                    return \date($key);

                // 文章选择，取决于$head传递
                case 'post':
                    return @$head[$key];

                // 读取配置
                case 'config':
                    if(!is_null($more)) return config::get($key,$more);
                    else return 'Bad Syntax';

                // 用户信息
                case 'client':
                    switch($key){
                        case 'addr'     : return $self->addr;     // 用户IP
                        case 'header'   : return $self->getrHeader($more);   // 请求标头
                        case 'session'  : return $self->getSession($more);      // 安全数据存储(SESSION)
                        case 'param'    : return $self->getParam($more);        // 获取请求参数
                        case 'cookie'   : return $self->getCookie($more);       // 读取COOKIE
                        default         : return 'Bad syntax';
                    }

                // VM变量
                case 'var':
                    return @self::$var[$key];

                // VM预定义变量
                case 'vm':
                    return @self::vm[$key];

                // JSON缓存
                case 'cache':
                    if(is_null(self::$cache))   
                        if(is_null($_ = self::$var['root']) or !file_exists($__ = $_.'/'.self::$var['cacheFile'] ?? 'cache.json'))
                            return print("[  WAR  ] 读取缓存失败:缓存不存在");
                        else self::$cache = json_decode(file_get_contents($__),true);
                        return is_null($value)?self::$cache[$key]:self::$cache[$key][$value];

                // 无匹配
                default:
                    echo "[  WAR  ] VM变量{$name}不存在\n";
                    return '';
            }
            
        }
    }
?>