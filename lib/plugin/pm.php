<?php
    /**
     * 插件包管理器 (PackageManager)
     * 此文件依赖于main.php
     */

    class pm{
        // 插件包下载源
        static private $list = [];

        /**
         * 初始化插件包下载源
         * 
         * @return array
         */
        static function initConfig():array{
            if(!extension_loaded('zip')) die('[  ERR  ] ioPM无法继续:缺少zip扩展'.PHP_EOL);
            $_ = config::get('plugin','source');
            if(count($_) == 0) die('[  ERR  ] ioPM初始化失败:至少需要1个源'.PHP_EOL);
        }

        /**
         * 获取各插件包下载源的列表
         * 
         * @return array
         */
        static function getSource(array $source):array{
            $list = array();
            foreach($source as $s){
                echo "[  HITS  ] Source : $s \n";
                $_ = file_get_contents("$s/list.json",context:stream_context_create([
                    'http'=>[
                        'method'    =>  'GET',
                        'user_agent'=>  'ioPM',
                        'follow_location'=>true,
                        'max_redirects'=>1,
                        'timeout'   =>  5.0,
                    ]
                ]));
                if($_ === false) echo "[ FAILED ] Get $s/list.json\n";
                else{
                    $list = array_merge(json_decode($_,true),$list);
                    echo "[  GETS  ] PackageList:$s";
                };
            }
            return $list;
        }

        static function update(){
            self::$list = self::getSource(self::initConfig());
        }

        static function install(array $pkgList):bool{
            if(count(self::$list) == 0) return !print("[  WARN  ] 内存中没有任何内容。请先执行'update'!");
            $dl = [];
            $size = 0;
            foreach($pkgList as $pkg){
                echo "[  FIND  ] 安装{$pkgList}:正在全速扫描插件列表......";
                $exists = array_key_exists($pkg,self::$list);
                cli::print([
                    'color'     =>  $exists?'green':'red',
                    'str'       =>  $exists?"找到了\n":"未找到,忽略\n",
                    'bg'        =>  'black'
                ]);
                if($exists){
                    $tmp = &self::$list[$pkg];
                    $dl[$pkg] = $tmp['url'];
                    $size += $tmp['size'];
                }
            }
            $count = count($dl);
            if($size > 1024) $size = round($size/1024,1).'KB';
            elseif($size > 1048576) $size = round($size/1048576,2).'MB';
            else $size = $size.'B';
            if(cli::readChoice("[ ALERT  ] 即将安装{$count}个插件包，将消耗{$size}，继续吗？")){
                foreach($dl as $n=>$d){
                    $f = fopen($d,'rb',context:stream_context_create([
                        'method'    =>  'GET',
                        'user_agent'=>  'ioPM',
                        'follow_location'=>true,
                        'max_redirects'=>1,
                        'timeout'   =>  20.0,
                    ]));
                    if($f === false and file_put_contents('temp.zip',fread($f))) {
                        echo "[ FAILED ] 失败：插件包{$n}=>无法下载{$d}\n";
                        continue;
                    }
                    $zip = new ZipArchive();
                    if ($zip->open('temp.zip') === true) {
                        mkdir(_plug.'/'.$n);
                        $zip->extractTo(_plug.'/'.$n);
                        $zip->close();
                    }
                    echo "[  SUCC  ] 成功下载并解压插件包{$n}。\n";
                }
            }else{
                echo "[ ABORT  ] 退出安装\n\n";
            }
        }
    }

?>