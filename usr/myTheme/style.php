<?php
    /**
     * myTheme主题，为默认主题。使用rounger驱动
     * 内建SiteMap
     * 
     * @package myTheme
     * @version V1.2
     * @author imzlh
     * @link http://imzlh.top
     */

    // 设置页面
    rounger::set('error/index',file_get_contents(__DIR__.'/404.html'));
    rounger::set('index',file_get_contents(__DIR__.'/index.html'));
    rounger::set('page/post',file_get_contents(__DIR__.'/post.html'));

    // 绑定一个API
    // $api = file_get_contents(__DIR__.'/api.html');
    // rounger::bind('/@/myTheme/sysinfo',function(){
    //     vm::use([

    //     ])->replace([

    //     ])->in();
    // });
    // $d = new cache(__DIR__.'/cache.json');

    rounger::bind(':/!/sysinfo',function(parentResponse|response $self){
        $self->finish("<h1>系统信息</h1>
<p><b>MEM</b>".memory_get_usage()."</p>
<p><b>RUNNING SYSTEM</b>".php_uname('s')."</p>",200);
    });

    rounger::bind(':/sitemap.xml',function(parentResponse|response $self){
        $self->say('<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
 xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">');
        foreach(post::$db['by_id'] as $id=>$data){
            $self->say("<url>
        <loc>/{$id}."._config['postExt']."</loc>
        <changefreq>daily</changefreq>
    </url>");
        }
        $self->finish('</urlset><p>Powered by <a href="//io.imzlh.top">ioBlog</a></p>');
    });

    rounger::bind(':/xfile/style.min.css',function(){
        $self->file(__FILE__.'/sty.css');
    });
    rounger::bind(':/xfile/script.min.js',function($self){
        $self->file(__FILE__.'/scr.js');
    });
?>