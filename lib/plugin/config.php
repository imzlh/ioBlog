<?php
    class config{
        static private $c,  // 配置
            $conf,          // 配置文件位置
            $save = 2;      // 0:未保存;1:已保存,2:无需保存

        /**
         * 初始化配置数据库
         * 
         * @param ?string $file_name
         * @return void
         */
        static function init(string $conf = _conf):void{
            self::$conf = $conf;
            if(!file_exists($conf) or 
                count(self::$c = json_decode(file_get_contents($conf),true)) == 0 
            ) cli::print([
                'color'      =>  'red',
                'str'        =>  '[  ERR  ] 无法加载配置文件'
            ]);
        }

        /**
         * 获取配置
         * 
         * @param string $config_type
         * @param string $config_name
         * @return mixed
         */
        static function get(string $type,string $name){
            return self::$c[$type][$name];
        }

        /**
         * 设置配置文件，注意不会直接保存
         * 
         * @param string $config_type
         * @param string $config_name
         * @param mixed $config_data_value
         * @return void
         */
        static function set(string $type,string $name,array|string|int|float $data):void{
            self::$c[$type][$name] = $data;
            self::$save = 0;
        }

        /**
         * 保存配置文件。此项没有参数。true为成功
         * 
         * @return bool
         */
        static function save():bool{
            if(self::$save != 0) return true;
            return !file_put_contents(self::$conf,json_encode(self::$c))?true:!print("[  WAR  ] 数据保存失败");
        }
    }
?>