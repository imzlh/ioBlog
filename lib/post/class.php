<?php
    /**
     * 重构版：新的文章解析系统
     */

    include_once(__DIR__.'/mdTool.php');

    class post{
        static public $md;       // MarkDown解析器
        static public $param;    // URL样式
        static $ext;             // 文章扩展名
        static public $db = [
            'by_id'     =>  [],
            'by_cate'   =>  [],
            'by_tags'   =>  [],
            'by_time'   =>  [],
            'by_path'   =>  []
        ];                       // 数据库
        static public $index_cache;// 缓存index的动态部分
        public $raw,    // 原始MD
        $body,          // 原始HTML
        $page,          // 解析完毕的页面
        $path,          // 文章路径
        $data = [],     // 文章数据
        $view,          // 预览
        $url;           // (几乎)唯一网址
        private $e  = true;     // 是否有错误
        
        /**
         * 初始化变量(PHP实在无语)
         */
        static function init(){
            self::$param = config::get('post','param');
            self::$ext   = config::get('post','ext');
        }
        /**
         * 解析所有文章
         */
        static function parseAll(array $list):void{
            $preTime = microtime(true);
            foreach($list as $file) new self($file);
            $time = (int) (microtime(true) - $preTime)*1000;
            $count = count($list);
            cli::print([
                'color' =>  'green',
                'bg'    =>  'black',
                'str'   =>  "[  SUC  ] 解析完毕,共 $count 篇文章,耗时 $time ms\n"
            ]);
        }

        /**
         * 设置文章，自动开始解析
         */
        public function __construct(string $file = ''){
            if($file == '') return;
            $this->path = $file;
            $this->parse();
            $this->initPreView();
            
            if(!_q) print('[  TIP  ] 解析'.basename($this->path = $file)) and cli::print([
                'color' =>  $this->e?'red':'green',
                'bg'    =>  'black',
                'str'   =>  $this->e?"[失败]\n":"[完毕]\n"
            ]);
        }

        /**
         * 解析文章
         */
        private function parse():void{
            if(!file_exists($this->path)) return;
            list($head,$body) = explode("---",file_get_contents($this->path));
            $this->data = self::parseHead($head);
            $this->raw = $body;
            $this->body  = self::mdParse($body);
            self::push($this);
            $this->e = false;
        }

        /**
         * 加载预览
         */
        private function initPreView(?int $len = null):void{
            if(is_null($len)) $len = config::get('post','preview');
            // 去除空格和非数字文本
            $str = preg_replace('/(\s|^\w)+/','',$this->raw);
            // 看看是否有mb_string和iconv支持，防止substr导致中文乱码
            foreach(['mb_substr','iconv_substr'] as $t)
                if(function_exists($t)){
                    $this->view = call_user_func($t,$str,0,$len);
                    return;
                }
            echo '[  TIP  ] 建议：请安装扩展mb_string或iconv(不推荐)防止乱码！'.PHP_EOL;
            $this->view = substr($str,0,$len*2);// 只能赌一把了，乱码也没办法（就是因为我懒）
        }

        /**
         * 设置文章
         * 
         * @param array $head_params
         * @param string|null $post_body_use_md
         * @return bool|null
         */
        public function set(array $param,?string $body = null){
            $t = '';
            foreach($param as $ti=>$ma) $t .= "$ti : $ma\n";
            $t .= '---'.PHP_EOL.$body ?? $this->raw;
            $name = $this->path == '' ? _post.'/'.(self::count()+1).'.md' : $this->path;
            if(file_exists($name)) rename($name,$name.'.bak');
            file_put_contents($name,$t);
            if($this->e == false) return true;
            else $this->__construct($name);
        }

        /**
         * 静态方法：解析头
         */
        static function parseHead(string $head):array{
            $a = [];
            foreach(\explode("\n",$head) as $n=>$line){ // 逐行解析
                if(\trim($line) == '') continue;        // 空行
                list($n,$v) = \explode(':',$line,2);    // 将每行按冒号分隔
                
                if(\is_null($n)) echo \_q?"[ Q:ERR ]文章".basename($p)."解析失败\n":
                    cli::print([
                        'color' =>  'red',
                        'bg'    =>  'white',
                        'str'   =>  "文章解析错误！位于行{$n}:' {$line} ':参数不可以为空\n"
                    ]);
                else             $a[strtolower(trim($n))] = trim($v);// 存储
            }
            return $a;
        }

        /**
         * 静态方法：解析MarkDown数据
         */
        static function mdParse(string $md):string{
            if(is_null(self::$md)) self::$md = new parseDown();
            return self::$md->parse($md);
        }

        /**
         * 静态方法：推送至URL数据库
         */
        static function push(\post &$post):void{
            $id = count(self::$db['by_id']);  // 获取文章ID
            self::$db['by_id'][] = &$post;    // 插入ID列表
            $post->data['time'] = $time = strtotime($post->data['date']);// 读取文章的时间
            self::$db['by_time'][$time] = &$post;                           // 按照时间戳排列文章
            self::$db['by_path'][
                ':'.($post->url = date(self::$param,$time).'.'.self::$ext)
            ] = function($self) use ($post){return post::get($post,$self);};// 按照指定模式解析URL
            self::$db['by_date'][date('Y',$time)][date('m',$time)] = &$post;// 便于按照日期索引
            $post->url = date(self::$param,$time).'.'.self::$ext;           // 唯一网址
            $post->id = $id;                                                // 唯一ID
            if(($tag = \trim($post->data['tags'])) != ''){                 // 如果标签是空白的，那就跳过
                $tags = \explode(',',\substr($tag,1,\strlen($tag)-2));     // 读取多个标签
                foreach($tags as $t) @self::$db['by_tags'][$t][] = &$post; // 存储在标签库
            }
            if(($cat = \trim($post->data['categories'])) != '') @self::$db['by_cate'][$cat][] = &$post;// 在标签中注册 
            echo "[  INF  ] 标签{$post->data['tags']},分类[$cat],日期[{$post->data['date']}]\n";
        }

        /**
         * 静态方法：获取文章总数
         */
        static function count(){
            return count(self::$db['by_id']);
        }

        /**
         * 按照指定模式列所有文章
         * 
         * @param string $in_db_list_type
         * @param class $self
         * @param string ?$list_type_name
         * @param int $start_offset
         * @param int $max_post_count
         * @return void
         */
        static function index(string $type,\parentRresponse|\response $self,string $name = 'none',int $offset = 0,int $limit = 100):void{
            if(!is_array($_ = $name != 'none' ? @self::$db["by_$type"][$name] : @self::$db["by_$type"]))  
                // $self->filter(404,'不存在:(');
                event::call('error/404',[$self,'不存在的内容']);
            else $self->finish(self::sindex($_,$self,$offset,$limit),200);
        }

        /**
         * 提供更原始的列文章方法
         */
        static function sindex(array $list,             // 需要列举的内容
            \parentRresponse|\response $self,           // HTTP类
            int $offset = 0,                            // 起始位置
            int $limit = 100                            // 限制最多长度
        ){
            if(is_null(self::$index_cache)){    // 初始化缓存
                if(!\preg_match('/\{\{\{[\w\W]+\}\}\}/',rounger::$e['index'],$match)) 
                    die('[  ERR  ] 在index中找不到循环体,无法继续!'.PHP_EOL);
                else self::$index_cache = $match[0];
            }
            if(count($list) == 0){
                $tmp = \str_replace([                  // 全部替换 
                    '{loop.title}','{loop.desc}','{loop.count}','{loop.href}'
                ],[
                    '糟糕,没有文章','怎么会这样呢?要不换个条件再试?','#','/'
                ],substr($_ = self::$index_cache,3,\strlen($_)-6));
            }else{
                $max = $limit > \count($list) - $offset? \count($list) : $limit - $offset -1 ;   // 自动决定最大循环次数
                // 循环的格式：只允许<>、数字、字母、引号、等号、空格换行，防止与JS的一些框架冲突
                // {{{[\s\w<>\/\'"=]+}}}
                $loop = \substr($_ = self::$index_cache,3,\strlen($_)-6);      // 提取的循环

                $tmp = '';
                for($i = $offset ; $i < $max ; $i ++ ){     // 循环：每篇文章一次
                    $data = &$list[$i];                     // 数据或文章ID(thisObject)：包含文章所有信息
                    if(is_null($data)) continue;            // 如果是空的跳过
                    $tmp .= \str_replace([                  // 全部替换 
                        '{loop.title}',                     // 标题
                        '{loop.desc}',                      // 描述，默认截取40个字符
                        '{loop.count}',                     // 第几篇文章，动态的
                        '{loop.href}'                       // 文章链接
                    ],[
                        $data->data['title'],
                        $data->view,
                        $i - $offset,
                        $data->url ?? "$i.".config::get('post','ext')
                    ],$loop);
                }
            }
            return vm::run([],\str_replace(self::$index_cache,$tmp,rounger::$e['index']),$self);// 将基础语法替换
        }


        /**
         * 静态方法：读取文章
         */
        static function get(\post $post,\parentResponse|\response $self):void{
            $self->finish(vm::run($post->data,
                str_replace('{{$post.body}}',$post->body,rounger::$e['page/post']) 
            ,$self),200);
        }

        /**
         * 静态方法：将所有文章聚合
         */
        static function join(){
            $_ = [];
            foreach(self::$db['by_id'] as $p)
                array_push($_ , [
                    'date'  =>  $p->data['date'],
                    'title' =>  $p->data['title'],
                    'view'  =>  $p->view,
                    'time'  =>  $p->data['time']
                ]);
            return $_;
        }

        /**
         * 实验功能：搜索
         * Level 1:仅扫描标题
         * Level 2:扫描正文、标题(推荐)
         * Level 3:模糊扫描Level2，性能较差
         * 
         * @param string $find 需要找的内容
         * @param int $level 扫描等级
         */
        static function search(string $find,int $level = 2){
            $tmp = [];
            switch($level){
                case 3:
                    $f = preg_quote($find);
                    foreach(self::$db['by_id'] as $p)
                        if(preg_match("/[$f]{2,}+/",$p->raw)) 
                            $tmp[] = $p;

                case 2:
                    foreach(self::$db['by_id'] as $p)
                        if(stripos($p->raw,$find) != false) 
                            $tmp[] = $p;

                case 1:
                    foreach(self::$db['by_id'] as $p)
                        if(stripos($p->data['title'],$find) != false) 
                            $tmp[] = $p;

                default:
                    return $tmp;
            }
        }
    }
?>