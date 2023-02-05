<?php
    /**
     * rounger URL路由
     * 原先是parse.php的一个无能函数，由于插件需要增加的
     * 支持以":"开头表示绝对路径和PREG正则路径
     */

    class rounger{
        static $db = [];    // URL库
        static $e  = [];    // 装载页面

        /**
         * 获取匹配列表
         */
        static function getMatch(string $path):callable|string|false{
            foreach(self::$db as $preg=>$call)
                if(substr($preg,0,2) == ':/')   {if(substr($preg,1) == $path) return $call; }
                elseif(preg_match($preg,$path)) return $call; 
            return false;
        }

        /**
         * 绑定路径
         */
        static function bind(string $path,callable|string $callback):void{
            self::$db[$path] = $callback;
        }

        /**
         * 自动完成URL匹配和执行
         */
        static function autoRun(\parentResponse|\response $self){
            $n = self::getMatch($self->path);
            if(is_string($n)) $self->finish($n,200);
            elseif(false == $n) $self->finish(implode('',event::call('error/404',[$self,'匹配不到请求的模式:('])));
            else call_user_func($n,$self);
        }

        /**
         * 设置特殊页面
         */
        static function set(string $type,string $str){
            $l = strlen(self::$e[$type] = $str);
            if(!_q)echo "[  INF  ] 装载页面{$type}成功，占用内存{$l}B \n";
        }
    }
?>