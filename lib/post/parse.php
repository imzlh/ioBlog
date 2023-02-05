<?php
    /**
     * parse.php 解析文章
     */
    namespace post{
        $index = [
            'by_tags'=>array(),     // TAG查找
            'by_cate'=>array(),     // CATE查找
            'by_url' =>array(),     // URL列表查找
            'by_id'  =>array(),     // 首页按照ID排序
            'by_date'=>array(),     // 按日期查找
        ];
        $data = [];                 // 文章1数据
        $page = [];                 // 页面数据

        // 将文章数据分好类存放
        // $str : 文章地址
        function parseAll(array $list):void{
            global $index,$data;

            // ===============基础内容解析 =============================
            $total = \count($list);                        // 总文章数
            foreach($list as $count=>$p){                  // 逐个解析
                $count ++ ;                                // ID($count)不能从0开始
                if(!\quiet) echo '[  TIP  ] 开始解析文章'.basename($p)."!\t进度:[ $count/$total ]\n";
                if(!file_exists($p) or !\is_readable($p)) 
                    if(!_quiet) echo '[  WAR  ] 文章解析失败(E0)文章'.basename($p).'不可读'.PHP_EOL;
                    else        echo "[ Q:WAR ] 读取文章ID{$count}错误\n";

                // 分离头和主体，此时文章
                list($head, $post) = \explode("---",\file_get_contents($p),2);
                $data['posts'][$count] = [
                    'data'=>(new \Parsedown())->parse($post),// 解析的文章主体(只有主体)
                    'file'=>$p,                              // 原始文件名，便于追溯
                ];
                $a = &$data['posts'][$count];
                unset($post);                               // 清理

                foreach(\explode("\n",$head) as $n=>$line){ // 逐行解析
                    if(\trim($line) == '') continue;        // 空行
                    list($n,$v) = \explode(':',$line,2);    // 将每行按冒号分隔
                    
                    if(\is_null($n)) echo \quiet?"[ Q:ERR ] 文章".basename($p)."解析失败\n":
                        "[  ERR  ] 文章解析错误！位于行{$n}:' {$line} ':参数不可以为空\n";
                    elseif($n == 'data' or $n == 'file') echo \quiet?"*"
                        :"[  WAR  ] 关键词data、file已被使用！位于:".basename($p).":=>行{$n}\n";
                    else             $a[trim($n)] = trim($v);// 存储
                }
            }
            if(!\is_array(@$index['by_id'])) $index['by_id'] = array();
            array_push($index['by_id'],$a);             // 方便根目录时列表

            $ext = \_config['postExt'];                 // 文章扩展名
            // ================将URL信息存放在$url===============
            /**
             * 在$data[{POSTID}]里有这些东西
             * 
             * title: CM311-1a(S905L3系列)玩转桌面  （标题）
             * author: iz                          （作者，独有）
             * categories: 日常琐事                 （分类）
             * tags: [s905l3]                       (标签）
             * date: 2022-07-23 22:23:00            (日期)
             */
            $_ = \_config['postParam'];
            $time = \strtotime($a['date']);
            // 使用了指针，简化了代码 
            $index ['by_url'][date(\_config['postParam'],$time).'.'.\_config['postExt']] = $a;
            // 为了方便按时间检索文章，有了by_date了
            @$index[\date('Y',$time)][\date('m',$time)][\date('d',$time)][] = $a;
            // ===============将文章以TAG方式存放===================
            $tag = \trim($a['tags']);                                      // 去掉标签的空格
            if($tag != ''){                                                // 如果标签是空白的，那就跳过
                $tags = \explode(',',\substr($tag,1,\strlen($tag)-2));     // 读取多个标签
                foreach($tags as $t){
                    if(!\is_array(@$index['by_tags'][$t])) @$index['by_tags'][$t] = [];// 新建TAG
                    array_push($index['by_tags'][$t],$a);                 // 在目录里注册
                }
            }
            // ===============将文章以分类方式存放==================
            if(($cat = \trim($a['categories'])) != ''){                     // 分类只有一个
                if(!\is_array(@$index['by_cate'][$cat])) @$index['by_cate'][$cat] = [];// 创建分类
                array_push($index['by_cate'][$cat],$a);                    // 在标签中注册 
            }
        }
        
        // 按照指定模式列所有文章
        function index(string $type,                    // 类型，如tag
            \http\parentRresponse|\http\response $self, // HTTP类
            string $name = 'none',                      // 标签、分类名
            int $offset = 0,                            // 起始位置
            int $limit = 100                            // 限制最多长度
        ){
            global $data,$index;
            if(!\is_array($m = $index["by_$type"])) return false;            // 如果$type不是tags,cate,post一类
            $max = $limit > \count($m)? \count($m) : $limit - $offset -1 ;   // 自动决定最大循环次数

            // 循环的格式：只允许<>、数字、字母、引号、等号、空格换行，防止与JS的一些框架冲突
            // {{{[\s\w<>\/\'"=]+}}}
            if(!\preg_match('/{{{[\w\W]+}}}/',\style\index,$match)) throw new \Error('[ ERR ] index文件找不到循环体'.PHP_EOL);
            $loop = \substr($match[0],3,\strlen($match[0])-6);      // 提取的循环

            $e = $name == 'none' ? $m : $m[$name] ;     // 列表是否需要有键值
            $t = '';                                    // 暂存文章列表
            for($i = $offset ; $i < $max ; $i ++ ){     // 循环：每篇文章一次
                $to = $e[$i];                           // 数据或文章ID(thisObject)：包含文章所有信息
                // if(\is_numeric($to) and !is_array($to = $data[$to])) die('[ ERR ] 系统错误:<');
                $t .= \str_replace([                    // 全部替换 
                    '{loop.title}',                     // 标题
                    '{loop.desc}',                      // 描述，默认截取40个字符
                    '{loop.count}',                     // 第几篇文章，动态的
                    '{loop.href}'                       // 文章链接
                ],[
                    $to['title'],
                    \preg_replace('/<[^>]+$/','',       // 过滤掉完整的HTML标签
                        \preg_replace('/<[^<]+>/',''    // 过滤由于截取不当导致的半个HTML标签 
                    ,substr($to['body'],100))),         // 截取100个字符，不过存在已知问题：HTML标签干扰
                    $i - $offset,
                    "post/$i.".\_config['postExt']
                ],$loop);
            }
            return vmrun([
                'data'=>\str_replace($match[0],$t,\style\index),// 伪装一下
            ],$self);// 将基础语法替换
        }

        // 查找系统支持的substr
        function substr(string $str,int $len){
            // 看看是否有mb_string和iconv支持，防止substr导致中文乱码
            foreach(['\\mb_substr','\\iconv_substr'] as $t)
                if(\function_exists($t))    return \call_user_func($t,$str,0,$len);
            echo '[ TIP ] 建议：请安装扩展mb_string或iconv(不推荐)防止乱码！'.PHP_EOL;
            return substr($str,0,$len*2);// 只能赌一把了，乱码也没办法（就是因为我懒）
        }

        function fserver(string $path,\http\parentResponse|\http\response $self){
            $path = ".$path";                   // 一定得在当前目录
            if(!file_exists($path) or !is_readable($path)) return false;
            $self->setName(basename($path));    // 设置下载文件名，顺便MimeType也搞定了
            return file_get_contents($path);    // 返回文件
        }

        function get(int $id,\http\parentResponse|\http\response $self):string{
            global $data;
            if($id >= \count($data)){
                $self->setHeader('status',404);
                return '[404]';
            }
            return vmrun($data[$id],$self);
        }
    }
?>
