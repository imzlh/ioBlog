<?php
    /**
     * event 事件管理器
     * 逻辑、代码很简单易懂的，相信我
     */

    class event{
        // 事件库
        static $lib = [];

        /**
         * 添加一个事件
         */
        static function on(string $event,callable|string $callback,bool $firstCall = false):bool{
            @list($type , $func ) = \explode('/',$event,2);     // 读取事件类型、事件名称
            if(\is_null($func))     return \_q?false:!print('[  WAR  ] 事件出错:事件不可以为空!'.PHP_EOL);
            if($func == '*')        @self::$lib[$type]['_default'] = $callback;
            if(!\is_array(@self::$lib[$type][$func]))  
                                    self::$lib[$type][$func] = array();// 创建一个事件表
            elseif($firstCall)      \array_unshift(self::$lib[$type][$func],$callback);   // 强制插队
            else                    \array_push(self::$lib[$type][$func],$callback);      // 先来后到
            return true;
        }

        /**
         * 清除一个事件 
         */
        static function clear(string $enent):bool{
            if(\strpos($event,'') == false){  // 警告：你将要将事件类连根拔起！
                if(!array_key_exists($event,self::$lib))  return \_q?false:!print('[  WAR  ] 事件类型不存在'.PHP_EOL);
                else                                      \unset(self::$lib[$event]);
            }else{
                if(!self::exists($event))                  return false;
                @list($type , $func ) = \explode('/',$event,2);
                unset(self::$lib[$type][$func]);
            }
            return true;
        }

        /**
         * 检查事件类是否存在
         */
        static function exists(string $event):bool{
            @list($type , $func ) = \explode('/',$event,2);
            if(\is_null($func))                       return \_q?false:!print('[  WAR  ] 事件出错:事件不可以为空!'.PHP_EOL);
            if(!array_key_exists($type,self::$lib))   return \_q?false:!print('[  WAR  ] 事件类型不存在.'.PHP_EOL);
            if(!\is_array(@self::$lib[$type][$func]) and !is_callable(@self::$lib[$type]['_default']))
                return \_q?false:!print('[  WAR  ] 事件出错:事件不存在!'.PHP_EOL);
            return true;
        }

        /**
         * 启动事件下的回调
         */
        static function call(string $event,array $param = []):array{
            if(!self::exists($event)) return ['Event not exists.']; 
            @list($type , $func ) = \explode('/',$event,2);
            $param[] = $func;
            try{
                $call = self::$lib[$type][$func]??[self::$lib[$type]['_default']];
                if(!is_array($call) or count($call) == 0) return [500];
                elseif(count($call) == 1) return [call_user_func_array($call[0],$param)];
                else return $ret = \call_user_func_array($call, $param);
            }catch(Exception|TypeError $e){
                return [(string)$e];
            }
        }

        /**
         * 轻量级仅调用模式，自动完成且仅调用第一个
         */
        static function run(string $event,\response|\parentResponse $self):bool{
            if(is_null($call = @self::$lib[$type][$func][0]??@self::$lib[$type]['_default'])) 
                return false;
            @list($type , $func ) = \explode('/',$event,2);
            $self->finish(\call_user_func($call, $self));
            return true ;
        }

        /**
         * 按照数组设置回调
         */
        static function set(array $event):bool{
            $r = true;
            foreach($event as $n=>$c){
                if(!self::on($n,$c)) $r = false;
            }
            return $r;
        }

        /**
         * 如果只调用了一次(run())，可以用callMore()调用剩余的事件
         */
        static function callMore(){
            
        }
    }
?>