<?php
    /**
     * ioPanel 启动文件
     */

    // 导入ioPanel 类
    include(__DIR__.'/class.php');

    // 将数据存储
    define('_panel_',[
        'index' =>  file_get_contents(__DIR__.'/panel.html')
    ]);

    // 绑定路径
    rounger::bind('/^\/panel\/?/',function(response|parentResponse $self){
        $path = substr($self->path,7);
        if($path == '') return $self->finish(
            str_replace('{logined}',is_null($self->getSession('user'))?'false':'true'
        ,_panel_['index']));
        $path = explode('/',$path);
        switch($mode = array_shift($path)){
            case 'api':
                try{
                    call_user_func("admin::{$path[0]}",$self,$path);
                }catch(Exception|TypeError $e){
                    $self->filter(500,$e->getMessage());
                }
                break;

            case 'static':
                $self->file(__DIR__.'/static/'.implode('/',$path));
                break;

            default:
                $self->filter(404,'Request mode "'.$mode.'" does not exists');
        }
    })
?>