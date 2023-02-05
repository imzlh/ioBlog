<?php
    /**
     * plugin/main : 插件管理器
     * 另外还有 plugin/pm : 插件包管理器，类似apt-get
     */

    class plugin{

        static private $plugLib = [],   // 插件列表
            $loaded = [];               // 已经启用的插件

        /**
         * 读取本地的插件列表
         * 
         * @return array
         */
        static function list():array{
            chdir(_plug);
            $list = array();
            foreach(scandir('./') as $plug){
                if(!is_dir($plug) or $plug == '..' or $plug == '.') continue;
                if(false === ($_ = @file_get_contents($plug.'/plugin.json'))){
                    if(!_q) echo "[  WAR  ] 无效的插件{$plug}:找不到配置文件\n";
                }else $list[] = json_decode($_,true);
            }
            return $list;
        }

        /**
         * 解析所有插件
         * 
         * @param array $list
         * @return bool
         */
        static function parseAll(?array $list=null):bool{
            if(is_null($list)) $list = config::get('plugin','list');
            $raise = '';
            foreach($list as $name)
                if(!self::enable($name)) if(_q) $raise .= $name;
                    else echo "[  WAR  ] 无法启动插件{$name}！\n";
                else if(!_q) echo "[  SUC  ] 成功启动插件 $name \n";
            if($raise != '') return !print("[  WAR  ] 启用这些插件失败:[$raise]\n");
            return true;
        }

        /**
         * 启用一个插件
         * 
         * @param string $plugin_name
         * @return bool
         */
        static function enable(string $name):bool{
            chdir(_plug);
            if(!is_dir($name) or !file_exists("$name/plugin.json")) 
                return _q?false:!print("[  ERR  ] 插件{$name}配置文件(plugin.json)不存在\n");
            // 读取插件配置
            $_ = (self::$plugLib[] = json_decode(file_get_contents($name.'/plugin.json'),true))['info'];
            // 解析依赖
            if(!is_array($dep = $_['depends'])) return _q?false:!print("[  ERR  ] 插件{$name}配置文件依赖错误\n");
            $pkg = self::list();
            foreach($dep as $deps) 
                if(!in_array($deps,$pkg)) self::enable($deps);     // 引入依赖包
                elseif(self::enable($deps)) return !print("[  ERR  ]");
                else echo "[ D:$deps ] ";
            // 读取入口
            if(!is_array($in = $_['require']) or count($in) <= 0)
                return _q?false:!print("[  ERR  ] 插件{$name}配置文件入口错误\n");
            // 将入口引入
            $raise = '';
            foreach($in as $file){
                if(!file_exists($__ = "$name/$file")){
                    if(!_q) print("[  WAR  ] 插件{$name}找不到入口{$file}\n");
                }else{ // 尝试引入文件
                    try{
                        if(!_q) echo "[  MSG  ] $name 引入了可执行文件 $file\n";
                        include_once($__);
                    }catch(Exception|Throwable|TypeError $e){
                        $opt = $e->getLine();
                        $msg = $e->getMessage();
                        $f   = basename($e->getFile());
                        !\_q?cli::print([
                            'color' =>  'red',
                            'bg'    =>  'white',
                            'str'   =>  "[  ERR  ] 无法启用插件{$name}:位于插件{$name}文件{$f}行{$opt}出错"
                        ]):$raise.="$name** ";
                        echo  "\n=> 出错啦! {$msg}\n";
                    }
                }
            }

            return true;
        }
    }
?>