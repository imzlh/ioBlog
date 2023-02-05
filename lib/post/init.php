<?php
    /**
     * 默认页面设置
     * 此文件不必在非ioBlog用途中引用
     */

    // 初始化环境
    include(__DIR__.'/rounger.php');     // 轻路由
    include(__DIR__.'/class.php');       // 文章解析
    include(__DIR__.'/vm.php');          // 虚拟字段解析器
    include(__DIR__.'/comment.php');     // 动态评论支持
    /*
     * ====================================================
     * 文章评论初始化:绑定几个页面
     * ====================================================
     */
    // ============================ 文章访问设置 ============================
    // 对首页进行设置
    rounger::bind(':/',function(\parentResponse|\response $self){
        post::index('id',$self);
    });
    // 文章页
    rounger::bind('/^\/[0-9]+\.'.config::get('post','ext').'/i',function(\parentResponse|\response $self){
        $pre = 1;
        $pos = strpos($self->path,'.',$pre);
        if(count(post::$db['by_id']) <= (int)substr($self->path,$pre)) event::call('error/404',[$self,'文章不存在']);
        else post::get(post::$db['by_id'][(int)substr($self->path,$pre)],$self);
    });
    // TAG、分类页
    rounger::bind('/^\/(tag|cat)\/[a-z]\/?/i',function(\parentResponse|\response $self){
        $mode = ($mod = explode('/',$self->path,4))[1] == 'tag' ?'tags':'cate';
        // if(!is_null(@$mod[3])) count(post::$db['by_'.$mode]) > $mod[3] ? $self->filter(404,'该文章不存在') : post::get('',$self);
        // else 
        post::index($mode,$self,$mod[2]);
    });
    // 按日期序列
    rounger::bind('/^\/[0-9]{2,4}\/([0-9]{2,2}\/)?$/',function(\parentResponse|\response $self){
        // event::call('error/403',[$self,'糟糕,这东西在TODO单上']);
        @list(,$year,$month) = explode('/',$self->path);
        $arr = is_null($month)?@post::$db['by_date'][$year]:@post::$db['by_date'][$year][$month];
        if(is_null($arr)) event::call('error/404',[$self,'找不到符合条件的文章?']);
        post::sindex($arr,$self);

    });
    // 文件下载
    rounger::bind('/^\/files\//',function(\parentResponse|\response $self){
        $self->file(_file.substr($self->path,6));
    });
    event::on('rounger/noMatch',function(\parentResponse|\response $self){
        if(array_key_exists($self->path,post::$db['by_url'])) post::get(post::$db['by_url'][$self->path],$self);
        else event::callMore();
    });

    // 错误页面，你可以细化如设置"error/404"
    event::on('error/*',function(\parentResponse|\response $self,string $desc,int $code):void{
        $self->filter($code,str_replace(['{code}','{desc}'],[$code,$desc], rounger::$e['error/index']));
    });

    // 搜索设置
    rounger::bind(':/search',function(response|parentResponse $self){
        if(is_null($self->getParam('w'))) $self->filter(403,'Need Param.缺少参数');
        $self->finish(
            post::sindex(post::search($self->getParam('w')),$self)
        );
    });

    // ==============================评论设置===================================
    // 增加评论api
    rounger::bind(':/api/comment',function(response|parentResponse $self){
        if((int)$self->getrHeader('content-length') > 8192) return $self->filter(400,'内容太长了');
        if(is_null($self->getParam('name',true)) or is_null($self->getParam('content',true))
            or is_null($self->getParam('from')) or is_null($self->getParam('to')))
            return $self->filter(400,'Bad form');
        $self->say(comment::add([
            'author'  =>  comment::xss($self->getParam('name',true),3),
            'time'    =>  time(),
            // 自动发现是回复还是文章评论
            $self->getParam('from')=='post'?'to':'reply'=>  (int)$self->getParam('to'),
            'content' =>  comment::xss($self->getParam('content',true),1),
            'link'    =>  comment::xss_link($self->getParam('link',true) ?? '#'),
            'email'   =>  comment::xss($self->getParam('email'),true)
        ],true)?'评论失败...':'恭喜，成功!');
        $self->finish('<script>setTimeout("history.back();",1000);</script>');
    });

    // 绑定自动保存
    event::on('io/exit',function(){
        comment::save();
        echo "[  SUC  ] 保存评论数据成功!\n";
    });

    rounger::bind(':/api/comSave',function(){
        comment::save();
    });

    // =============================== 启动文章解析 ============================
    // POST对象链接到rounger中
    post::$db['by_path'] = &rounger::$db;
    // 定义事件：重载
    event::on('io/reload',function(){
        post::parseAll(glob(_.'/post/*.md'));
    });
    // post先定义基础内容
    post::init();
    post::parseAll(glob(_post.'/*.md'));    // 解析文章

    /*
     * ===============================================
     * VM虚拟隔离式环境设置
     * ===============================================
     */
    // include函数
    vm::push('include',function($self,array $arg,array $head){
        $f = _.(vm::$var['root'] ?? '/').$arg[0];
        if(file_exists($f) and is_readable($f)) return vm::run($head,file_get_contents($f),$self);
        else echo "[  WAR  ] VM:无法引入文件{$f}\n";
    });
    // comment函数(本身的ioBlog、VM不支持评论)
    vm::push('comment',function($self,array $arg,array $head){
        if(is_null($head['time'])) return print('[  WAR  ] 在非文章模式下不可以使用comment!');
        return comment::build([
            'type'  =>  'by_postID',
            'value' =>  $head['time']
        ]);
    });

    // ===================== DEBUG函数 =============================
    rounger::bind(':/@debug/rounger',function($self){
        print_r(rounger::$db);
    })
?>