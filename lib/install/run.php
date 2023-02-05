<?php
    /**
     * ioBlog安装程序
     */

    // 检查环境
    cli::clean();
    if(!_q) echo '================ 欢迎使用ioBlog! =============='.PHP_EOL.'[  INF  ] 开始检查依赖(几乎没有)'.PHP_EOL;
    // foreach([
    //     '*JSON'     =>  'json_decode',
    //     'mbstring'  =>  'mb_substr',
    // ] as $name=>$item){

    // }
    echo '[  SUC  ] 没有找到依赖错误.';

    // 启动HTTP
    if(!_q)      echo '[  INF  ] 初始化ioHTTP......'.PHP_EOL;
    include(_lib.'/http/main.php');
    http::init(__DIR__.'/../http/mime.types');

    // 加载插件库
    include(_lib.'/plugin/main.php');

    $port = cli::get('start') ?? rand(1024,65535);
    $port = is_bool($port) ? rand(1024,65535) : (int)$port;
    $e = http::bind($port)(false);// 随机找到一个端口监听
    echo "[  MSG  ] ioBlog以安装模式启动，现在请打开[IP]:$port\n";
    $ck = md5(rand(1,100));         // 随机csrf
    
    for($tC = 0 ; true ; $tC ++) new parentResponse(stream_socket_accept($e),
        function(parentResponse $self) use ($ck){// 回调
            $path = $self->path;            // 读取路径
            $first = true;                  // 是否设置COOKIE
            if($path == '/'){               // 根目录
                if(!is_null($ck)) $self->setHeader('Set-Cookie',"csrf=$ck") and $ck = null;
                $self->useGZip(file_get_contents(_lib.'/install/index.html'),200);
            // }elseif($path == '/plugin'){
                // $self->finish(json_encode(plugin::list()),200);
            }elseif($path == '/submit'){    // 提交配置

                if($self->type !== 'POST') return $self->filter(400,'必须使用POST递交数据！');
                if($ck == $self->getCookie('csrf')){
                    $self->finish('Success',200);
                    // 创建文件夹
                    mkdir(_.'/post');
                    mkdir(_.'/file');
                    mkdir(_.'/etc');
                    // 读取前端的传来的数据
                    file_put_contents(_cfg.'/config.json',json_encode(array(
                        // ========= ioHTTP服务器设置 =================
                        'http'=>[
                            'port'      => $self->getParam('port',true) ?? 80,
                            'maxThread' => $self->getParam('max',true) ?? 8,
                            'process'   => $self->getParam('task',true) ?? 4,
                            'maxTask'   => $self->getParam('task',true) ?? 16,
                        ],
                        // ========== SSL(HTTPS)设置 =================
                        'ssl'=>[
                            'enable'    => $self->getParam('ssl[enable]',true) ?? false,
                            'cer'       => $self->getParam('ssl[cer]',true),
                            'key'       => $self->getParam('ssl[key]',true),
                        ],
                        // ========= ioBlog全局      =================
                        'blog'=>[
                            'name'      => $self->getParam('name',true) ?? 'ioBlog',
                            'desc'      => $self->getParam('desc',true) ?? '我的第n个博客',
                            'url'       => $self->getParam('url',true) ?? 'imzlh.top',
                            'keyword'   => $self->getParam('kwd',true) ?? 'PHP,ioBlog',
                        ],
                        // ========= 文章格式设置    ==================
                        'post'=>[
                            'ext'       => $self->getParam('ext',true) ?? 'html',
                            'param'     => $self->getParam('param',true) ?? 'Y/m/d',
                            'preview'   =>$self->getParam('view',true) ?? 50,
                        ],
                        // ========= 插件设置       ==================
                        'plugin'    =>[
                            'list'      => ['myTheme'],//array_keys(plugin::list()),
                            'source'    => json_decode($self->getParam('source',true) ?? '["http://imzlh.top:88/ioPkg"]')
                        ]
                    )));
                    // 解析插件
                    // chdir(_plug);
                    // foreach(plugin::list() as $n=>$c){
                    //     $conf = [];
                    //     foreach($c['config'] as $name=>$config){
                    //         // $name:配置项名字,$n:插件名
                    //         $conf[$name] = $self->getParam("$n.$name",true) ?? $config['default'];
                    //     }
                    //     file_put_contents("$n/cache.json",json_encode($conf));
                    //     if(!_q) echo "[  MSG  ] 成功写入插件配置:[$n]\n" ;
                    // }
                    // echo "[  SUC  ] 插件配置文件加载完毕!\n";
                    $date = date('Y-m-d h:m:s');
                    // 创建一个测试文章
                    file_put_contents(_post.'/test.md',"title: 欢迎来到ioBlog
author: imzlh
categories: ioBlog
tags: [ioBlog,ioBlogs]
date: $date
---
欢迎使用ioBlog!这是默认生成的测试文章，你可以更改、删除等。
访问后台`/panel`获取更多可用选项！欢迎探索！
");
                    echo "\n[  SUC  ] 成功完成配置。使命结束，退出中......\n";
                    exit;
                }else{
                    $self->filter(400,'[  ERR  ] Bad CSRF-Token!');
                }
            }elseif($path == '/getinfo'){    // 获取系统信息
                $self->finish(json_encode([
                    'system'    =>  [   // 系统信息
                        'version'   =>  php_uname('r'), // 系统版本
                        'type'      =>  php_uname('s'), // 系统类型
                        'arch'      =>  php_uname('m'), // 系统架构
                        'more'      =>  php_uname('v')  // 系统详细信息
                    ],
                    'ioBlog'    =>  [   // ioBlog信息
                        'version'   =>  $version,       // ioBlog版本
                        'BuiltTime' =>  '2023-1-16'     // ioBlog完工时间
                    ],
                    'php'       =>  [   // PHP信息
                        'version'   =>  PHP_VERSION,    // PHP版本
                        'zend'      =>  zend_version(), // ZEND版本
                        'instPath'  =>  DEFAULT_INCLUDE_PATH,// 安装路径
                        'exts'      =>  get_loaded_extensions()// 已经安装的扩展  
                    ]
                ]));
            }elseif($path == '/upload'){
                if($self->type !== 'POST') $self->filter(400,'必须使用POST递交数据！');
                $self->parseEndParam();
                $err = false;
                foreach($self->file as $file){
                    $name = http::getUpload($file)['name'];
                    if($name == 'cer')      rename($file['savePath'],_cfg.'/ssl.cer') or $err = true;
                    elseif($name == 'key')  rename($file['savePath'],_cfg.'/ssl.key') or $err = true;
                    else return $self->filter(403,'请上传正确的文件！');
                }
                echo _q?"\n Uploaded.":"[ ALERT ] 上传证书完毕. || STATUS:";
                $err
                ?$self->filter(403,'Cannot move uploaded file.')
                :$self->finish(json_encode([
                    'status'=> !$err,        // 状态，true则成功
                    'cer'   => _cfg.'/ssl.cer',
                    'key'   => _cfg.'/ssl.key',
                    'time'  => time()
                ]));
            }else{
                $self->file(_lib.'/install'.$path);
            }
        }
    );
?>