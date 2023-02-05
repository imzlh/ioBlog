<?php
    // 导入必须文件
    include(__DIR__.'/main.php');      // 插件初始化工具
    // 自动保存配置文件
    event::on('io/exit',function(){
        config::save();
    });
    // 开始解析
    plugin::parseAll();
?>