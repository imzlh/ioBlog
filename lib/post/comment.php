<?php
    /**
     * 评论处理类
     */
    class comment{
        // 存储所有评论
        static 
            $comments = [],
            $db = [
                'by_time'   =>  [],
                'by_author' =>  [],
                'by_follow' =>  [],
                'by_postID' =>  []
            ]    // 对应表
            ,$save = false;// 需要保存的内容
        
            // 评论信息
        public 
            // 内容/作者     /回复评论/日期/来源/主页/电子邮箱
            $content,$author,$reply,$date,$to,$link,$email,
            $follow = [],   // 回复评论
            $error = false;// 是否出错

        /**
         * 初始化所有评论
         */
        static function parseAll(string $file_name){
            if(!file_exists($file_name)) die('[  ERR  ] ioComments初始化失败！文件不存在');
            if(!_q) echo "[  INF  ] 开始解析评论:";
            foreach(json_decode(file_get_contents($file_name),true) as $i=>$comment) 
                print("$i ") and self::add($comment);

            // 对评论排序
            ksort(self::$db['by_time']);
            echo "\n[  SUC  ] 评论缓冲完毕，共".count(self::$db['by_time'])."条\n";
        }

        /**
         * 获取n条评论
         */
        static function join(int $n = 10):array{
            $t = [];
            $a = count(self::$db['by_time'])-1;
            $n = $a<$n ? $a : $n;
            for($i=0 ; $i<$n ; $i++){
                $d = self::$db['by_time'][$n];
                array_push($t,[
                    'author'    =>  $d->author,
                    'content'   =>  $d->content,
                    'date'      =>  $d->date,
                    'email'     =>  $d->email,
                    'link'      =>  $d->link,
                    'to'        =>  $d->to,
                    'reply'     =>  $d->reply
                ]);
            }
            return $t;
        }

        /**
         * 初始化一条评论，第二参数指定是否为新评论
         */
        static function add(array $data,bool $need_save = false):bool{
            self::$comments[] = $_ = new comment($data);
            if($need_save == true) self::$save = true;
            return $_->error;
        }

        /**
         * 推入数据库
         */
        static function push(self &$self):void{
            self::$db['by_author'][$self->author][] = &$self;
            self::$db['by_follow'][$self->reply][] = &$self;
            self::$db['by_time'][$self->time][] = &$self;
            self::$db['by_postID'][$self->to][] = &$self;
        }

        /**
         * 保存评论.JSON限制100条
         */
        static function save(){
            if(!self::$save) return;
            file_put_contents(_post.'/comment.json',json_encode(self::join(1000)));
        }

        /**
         * 按照一种方式获取评论
         */
        static function get(string|int $name,string $by='by_id'):self|null{
            return @self::$db[$by][$name];
        }

        /**
         * 初始化评论
         */
        function __construct(array $c){
            if(is_null($c['content']) or is_null($c['author']) 
                or (is_null(@$c['to']) and is_null(@$c['reply']))
                or !array_key_exists($c['to'],post::$db['by_time'])){
                echo "[  WAR  ] 解析评论时发现错误(ID:{$c['time']})\n";
                $this->error = true;
                return;
            }
            $this->content = $c['content'];          // 内容
            $this->author  = $c['author'];           // 创作者
            $this->date = date('Y-m-d h:m:s',$this->time = $c['time']);// 时间
            $this->reply = @$c['reply'];             // 回复至
            $this->to = @$c['to'];                   // 评论至
            $this->link = @$c['link'];               // 链接至对方主页
            $this->email = @$c['email'];             // 电子邮箱
            if(!is_null(@$c['reply']) and !is_null(self::get($c['reply'])))
                self::get($c['reply'])->follow($this);  // 如果是回复则加入
            self::push($this);
        }

        /**
         * 层次:加入回复评论
         * 
         * @param comment $self
         */
        function follow(comment $self):void{
            $this->follow[] = $self;
        }

        /**
         * 修改评论
         */
        function update(array $param):void{
            $_ -> author = $param['author'];
            $_ -> email = $param['email'];
            $_ -> content = ['content'];
            $_ -> link = ['link'];
            self::$save = true;
        }

        /**
         * 组装评论
         */
        static function build(array $opt){
            $e = self::$db[$opt['type']][$opt['value']];
            if(is_null($e) or !is_array($e)) return '<p>这篇文章没有评论</p>';
            $_ = '';
            foreach($e as $c) {
                $_ .= "<div class=\"comment-page\">
                    <h3><a href=\"{$c->link}\">{$c->author}</a></h3>
                    <p>{$c->content}</p>";
                foreach($c->follow as $f) 
                    $_ .= "<div class=\"comment-follow\">
                    <h3><a href=\"{$c->link}\">{$c->author}</a></h3>
                    <p>{$c->content}</p>
                    </div>";
                $_ .= "</div>";
            }
            return $_;
        }

        /**
         * 获取n行评论
         */
        static function gets(int $n = 3){
            $data = [];
            foreach(array_slice(self::$db['by_time'],-$n) as $t)
                foreach($t as $n)
                    array_push($data,[
                        'link'      =>  $n->link,
                        'author'    =>  $n->author,
                        'email'     =>  $n->email,
                        'content'   =>  $n->content
                    ]);
            return $data;
        }

        /**
         * 获取评论数量
         */
        static function count(){
            return count(self::$comments);
        }

        /**
         * XSS简单过滤器
         * 
         * @param int $xss_string 过滤的字符串
         * @param int $level 过滤等级,1=>严格模式,2=>白名单模式,3=>仅脚本过滤
         * @return string 
         */
        static function xss(?string $xss,int $level=1):string{
            if(is_null($xss)) return '';
            switch($level){
                case 1: // 过滤所有标签
                    return preg_replace('/\<[\w\W]+?\>/','',$xss);

                case 2: // 过滤安全警告
                    return  preg_replace([
                        '/on[a-z]+\=\"[\w\W^"]+?\")/i',        // 过滤事件
                        '/<\\?(style|script|link) [\w\W]+?>/i',// 过滤CSS、SCRIPT
                        '/\"\w*javascript:[\w\W]+?\"/i'        // JavaScript内联防御
                    ],['','',''],$xss);

                case 3:
                    // 最不严格,最省事
                    return htmlentities($xss);
            }
        }

        /**
         * 链接防御
         */
        static function xss_link(string $link):string{
            $protocol = explode('://',$link,2)[0];
            if(is_null(@[
                'http',
                'https',
                'HTTP',
                'HTTPS'
            ][$protocol])) return '';
            else return $link;
        }
    }
?>