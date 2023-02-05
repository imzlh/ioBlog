<?php
    /**
     * 警告！
     * test型HTTP服务器只可用于测试！
     * 性能十分差劲！并发很低！
     */
    echo "[  WAR  ] 您正在使用性能极差的TEST模式！端口:";
    echo $_ = cli::get('start')??config::get('http','port')==true?80:config::get('http','port');
    echo PHP_EOL;
    http::bind($_,config::get('http','maxTask')??8,config::get('ssl','enable'))
        ("rounger::autoRun",@cli::get('https_cert'),@cli::get('https_key'));
    class response extends parentResponse{}
?>