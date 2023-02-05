<?php
    /**
     * ioAdmin V1
     * 现已经全面集成到ioBlog内部了
     */
    class admin{
        /**
         * ioAdmin 数据存储位置
         */
        const file = __DIR__.'/data.json';

        /**
         * ioAdmin主要数据，初始化后存放此
         */
        static private $data;

        /**
         * 访客计时器
         */
        static $count = 0;

        /**
         * IP计时器
         */
        static $ipTable = [];

        /**
         * ioBlog系统启动时间
         */
        static $start = 0.0;

        /**
         * 初始化ioAdmin
         */
        static function init():void{
            if(self::$start != 0.0) return;
            // 启动访客计数器
            event::on('http/new',function($self){
                admin::$count ++;  // 访问计数
                if(is_null(panel::$ipTable[$self->ip])) admin::$ipTable[$self->ip] = 0;
                admin::$ipTable[$self->ip] ++ ;// IP计数
            });
            // 初始化启动事件
            self::$start = time();
            self::$data = json_decode(file_get_contents(self::file));
            if(file_exists($_ = __DIR__.'/mail/'.strtoupper(self::$data->mail->type).'.php')){
                require_once $_;
                // 导入mail发邮件支持
                require_once __DIR__.'/mail/Exception.php';
                require_once __DIR__.'/mail/PHPMailer.php';
            }else{
                die('[  ERR  ] 无法初始化mail环境:未知的协议:'.self::$data->mail->type.PHP_EOL);
            }
        }

        /**
         * 发送电子邮件，使用API
         * 
         * @param string $username
         * @param string $user_email_address
         * @param string $mail_name
         * @param string $mail_html_body
         * @param string $mail_noHTML_body
         * @return bool
         */
        static private function mail(string $user,string $mailto,string $name,string $body,string $nohtml_body):bool{
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);// 初始化mail
            try {
                //服务器配置
                $mail->CharSet ="UTF-8";                     //设定邮件编码
                $mail->SMTPDebug = 0;                        // 调试模式输出
                $mail->isSMTP();                             // 使用SMTP
                $mail->Host = self::$data->mail->server;     // SMTP服务器
                $mail->SMTPAuth = true;                      // 允许 SMTP 认证
                $mail->Username = self::$data->mail->user;   // SMTP 用户名  即邮箱的用户名
                $mail->Password = self::$data->mail->pw;     // SMTP 密码  部分邮箱是授权码(例如163邮箱)
                $mail->SMTPSecure = self::$data->mail->protocol;// 允许 TLS 或者ssl协议
                $mail->Port = self::$data->mail->port;       // 服务器端口具体要看邮箱服务器支持

                $mail->setFrom(self::$data->mail->from, self::$data->mail->name);//发件人
                $mail->addAddress($mailto, $user);            // 收件人
                $mail->isHTML(true);                          // 是否以HTML文档格式发送  发送后客户端可直接显示对应HTML内容
                $mail->Subject = $name;
                $mail->Body    = $body;
                $mail->AltBody = $nohtml_body;
                $mail->send();
                echo "\n[  SUC  ] admin:邮件发送'$to'成功\n";
                return true;
            } catch (\PHPMailer\PHPMailer\Exception $e) {
                echo "\n[  ERR  ] admin:邮件发送{$to}失败!{$mail->ErrorInfo}\n";
                return false;
            }
        }

        /**
         * 初始化mail模板(存放在mail.html)
         * 
         * @param array $param
         */
        static private function getMail(array $param){
            switch($param['type']){
                case 'pin':
                    $_ = "<p>您正在{$param['action']},请收好，您的PIN是</p>
                    <h1 style=\"text-align: center;letter-spacing: 0.5em;\">{$param['pin']}</h1>
                    <p>请在浏览器关闭前完成操作，否则将失效，感谢！</p>";
                    break;
                
                case 'do':
                    $_ = "<p>我们发现有人正在{$param['action']}，如这不是你的操作，请赶紧重启ioBlog并修改密码！</p>
                    <h3>更多信息:</h3><ul>
                        <li>对方的IP、端口:{$param['ip']}</li>
                        <li>对方浏览器信息:{$param['browser']}</li>
                        <li>对方执行的操作:{$param['action']}</li>
                        <li>执行时间：".date('Y-m-d h:m:s')."</li>
                    </ul>
                    <p>ioAdmin尽力保护你的博客 [:)]</p>";
            }
        }

        /**
         * 登录的实现
         */
        static function login(response|parentResponse $self){
            $realPw = self::$data->user->pw;
            $password = $self->getParam('pw',true);
            if(is_null($password) or strlen($password) < 8) 
                return $self->filter(403,'密码预检失败');
            $pw = md5($password);
            $name = $self->getParam('user',true);
            if(is_null($name))  return $self->filter(403,'用户名不能为空');
            if($name == self::$data->user->name and $pw == $realPw){
                // 登录成功
                $self->setSession('login',true);
                $self->setSession('time',time());
                $self->finish('登陆成功',200);
                // 不同步：发送email(时间比较久)
                if(self::$data->opt->mail_when_login) self::mail('管理员',self::$data->user->email,'登录警告',self::getMail([
                    'type'  =>  'do',
                    'action'=>  '登录',
                    'ip'    =>  $self->getrHeader('x_forwarded_for') ?? $self->ip,
                    'browser'=> $self->getrHeader('user-agent')
                ]));
            }else{
                $self->filter(403,'账号或密码错误');
            }
        }

        /**
         * 面板信息
         */
        static function getInfo(response|parentResponse $self){
            if(is_null($self->getSession('user'))) return $self->filter(403,'请先登录');
            // $self->finish(json_decode([
            //     'timeNow'   =>  time(),
            //     'startUp'   =>  time() - self::$start,
            //     'visit'     =>  self::$count,
            //     'ipCount'   =>  count(self::$ipTable),
            //     'commnts'   =>  comment::count(),
            //     'version'   =>  _v
            // ]));
            $self->finish(json_encode([
                'table'     =>  [
                    'visit'     =>  self::$count,
                    'comments'  =>  comment::count(),
                    'runtime'   =>  time() - self::$start,
                    'posts'     =>  post::count(),
                    'ip'        =>  count(self::$ipTable),
                    'version'   =>  _v
                ],'chart'   =>  [
                    '前天'  =>  20,
                    '昨天'  =>  30
                ],'comment' =>  comment::gets(5)
            ]));
        }

        /**
         * 超API认证
         */
        static function _login($self){
            $self->setSession('user',$self->getParam('user'));
        }

        /**
         * 一个API:检查内容是否有null
         */
        static private function null($self,...$param):bool{
            foreach($param as $p) 
                if(( $self->getParam($p,true) ?? '' ) == '') return true;
            return false;
        }

        /**
         * 保存文章
         */
        static function savePost(response|parentResponse $self){
            if(is_null($self->getSession('user'))) return $self->filter(403,'请先登录');
            if(self::null($self,'title','tags','date','cate','content'))
                return $self->filter(403,'预检失败:不能为空');
            (new post())->set([
                'title'      =>  $self->getParam('title',true),
                'tags'       =>  $self->getParam('tags',true),
                'categories' =>  $self->getParam('cate',true),
                'date'       =>  $self->getParam('date',true),
                'author'     =>  $self->getSession('user'),
            ],$self->getParam('content',true));
            $self->finish('成功!',200);
        }

        /**
         * 读取列表.这个API游客也可用!!!
         */
        static function getPost(response|parentResponse $self){
            $self->finish(json_encode(post::join()));
        }

        /**
         * 获取文章信息，这个API访客也可用
         */
        static function postGet(response|parentResponse $self){
            $time = (int)$self->getParam('id');
            if($time == 0) return $self->filter(400,'Bad ID');
            $post = @post::$db['by_time'][$time];
            if(is_null($post)) return $self->filter(404,'Post Not Exists');
            $self->finish(json_encode([
                'head'      =>  '编辑:'.$post->data['title'],
                'title'     =>  $post->data['title'],
                'content'   =>  $post->raw,
                'tags'      =>  $post->data['tags'],
                'cate'      =>  $post->data['categories'],
                'date'      =>  $post->data['date'],
                'save'      =>  '/panel/api/changePost'
            ]));
        }

        /**
         * 修改文章
         * 这个比较难(如果修改了TAG、CATE就会出现不存在的文章)
         */
        static function changePost(response|parentResponse $self){
            if(is_null($self->getSession('user'))) return $self->filter(403,'请先登录');
            if(self::null($self,'title','tags','date','cate','content'))
                return $self->filter(403,'预检失败:不能为空');
            if(is_null($_ = @post::$db['by_time'][strtotime($self->getParam('date',true))]))
                return $self->filter(403,'目标文章不存在');
            // 重置原始文字
            $_->raw = $self->getParam('content',true);
            $_->raw = post::mdParse($_->raw);
            // 重新写文件
            $_->set([
                'title'      =>  $self->getParam('title',true),
                'tags'       =>  $self->getParam('tags',true),
                'categories' =>  $self->getParam('cate',true),
                'date'       =>  $self->getParam('date',true),
                'author'     =>  $self->getSession('user'),
            ]);
            $self->finish('Success.');
        }

        /**
         * 评论类操作
         */
        static function commentsGet(response|parentResponse $self){
            $self->finish(json_encode(comment::join()));
        }

        /**
         * 修改评论
         */
        static function saveComment(response|parentResponse $self){
            if(is_null($self->getSession('user'))) return $self->filter(403,'请先登录');
            if(self::null($self,'link','author','email','content'))
                return $self->filter(403,'预检失败:不能为空');
            if(is_null($_ = @comment::$db['by_time'][strtotime($self->getParam('date',true))]))
                return $self->filter(403,'目标文章不存在');
            $_ -> update([
                'author'     => $self->getParam('author',true),
                'email'     => $self->getParam('email',true),
                'content'   => $self->getParam('content',true),
                'link'  => $self->getParam('link',true)
            ]);
            $self->finish('成功');
        }

        /**
         * 读取插件列表
         */
        static function pluginList($self){
            if(is_null($self->getSession('user'))) return $self->filter(403,'请先登录');
            $self->finish(json_encode(plugin::list()),200);
        }

        /**
         * 文件上传
         */
        static function upload($self){
            if(is_null($self->getSession('user'))) return $self->filter(403,'请先登录');
            $self->parseEndParam();
            @rename($self->file[0]['savePath'],_plug.'/tmp.zip') or $self->filter(500,'移动上传文件失败');
            if(class_exists('ZipArchive')) $self->filter(500,'无法解压，缺少ZIP扩展。<br>不过我们已经成功帮你上传至usr/tmp.zip了，请手动解压');
            $zip = new ZipArchive();
            if(true !== $zip->open(_plug.'/tmp.zip')) $self->filter('解压失败.');
            $zip->extractTo(_plug);
            $zip->close();
            $self->finish('成功');
        }
    }

    // 先初始化
    admin::init();
?>