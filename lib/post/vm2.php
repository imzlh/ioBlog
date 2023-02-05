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
        static private $var = [];

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
         * 解析VM的变量
         */

        /**
         * 运行虚拟解析器，用于将动态字段替换。
         * $head需要一个数组，在调用{!post.??}时会用到，可以传递空数组
         * $body为一个只差替换的HTML数据
         * $self用于处理client内容，当然不允许为空(代代传递)
         * 
         * @param array $post_head
         * @param string $todo_string
         * @param http $iohttp_class_self
         */
        static function run (array $head,string $body,\parentResponse|\response $self):string{
            \preg_match_all('/\{?[\S\s]+?\}/',$body,$match);
            foreach(array_unique($match[0]) as $raw){
                echo $raw;
                // 获取原始数据
                $querty = substr($raw,2,strlen($raw)-3);
                // 检查是否存在
                if(!array_key_exists($_ = substr($querty,0,strpos($querty,' ')),self::$func)){
                    $body = str_replace($raw,'',$body);
                    echo "[ VMERR ] VM内部错误:call to a undefined function $querty\n";
                    continue;
                }
                // 替换变量,变量为$aaa$
                if(preg_match_all('/\$?[a-zA-Z1-9]+?\$/',$querty,$match) != 0)
                    foreach(array_unique($match[0]) as $var){
                        $name = substr($var,1,strlen($var)-2);
                        $querty = str_replace($var,self::$var[$name],$querty);
                    }
                // 替换常量，常量为:aaaa:
                if(preg_match_all('/\:?[a-zA-Z1-9]+?\:/',$querty,$match) != 0)
                    foreach(array_unique($match[0]) as $var){
                        $name = substr($var,1,strlen($var)-2);
                        $querty = str_replace($var,self::get($name,$self,$head),$querty);
                    }
                // vm执行
                try{
                    $body = str_replace($raw,call_user_func(self::$func[$_],$self,$querty),$body);
                }catch(Exception|TypeError $e){
                    $body = str_replace($raw,'',$body);
                    echo "[ VMERR ] {$e->getMessage()} 在文件{$e->getFile()}第{$e->getLine()}行 \n";
                }
            }
        return $body;
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
                        case 'header'   : return $self->parseHeader()[$more];   // 请求标头
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

                // 无匹配
                default:
                    return implode(event::call("$type/$key",[$self,$more]));  // 无对应
            }
            
        }
    }

    // include函数
    vm::push('include',function($self,string $str){
        $f = _. substr($str,7);
        if(file_exists($f) and is_readable($f)) return file_get_contents($f);
        else echo "[  WAR  ] VM:无法引入文件{$f}\n";
    });

    // 输出函数
    vm::push('echo',function($self,$str){
        return substr($str,5);
    });

    // 判断函数
    vm::push('if',function($self,$str){
        $_ = explode('::',substr($str,3));
        foreach($_ as $switch){
            list($if,$then) = explode('?',$switch,2);
            list($a,$b,$c) = explode(' ',$if);
            $__ = '';
            switch($b){
                case '=':
                    if(a == $c)
                        return $then;
                    else 
                        break;
                    
                case '->':
                    if(preg_match($a,$b))
                        return $then;
                    else
                        break;
                
                case '>':
                    if(a > $c)
                        return $then;
                    else 
                        break;

                case '<':
                    if(a < $c)
                        return $then;
                    else 
                        break;
            }
        }
    });

    // 定义函数
    vm::push('var',function($self,$str){
        $_ = explode(',',substr($str,4));
        foreach($_ as $__){
            list($n,$v) = explode('=',$__,2);
            if(is_null($n) or is_null($v)) return '';
            vm::var($n,$v);
        }
    })
?>