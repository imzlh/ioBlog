/**
 * ioPanel V1
 */

// 初始化
window.onload = function(){
    $('#_loading').remove();
    if(_logined){
        msg.add('lightskyblue','已登陆','欢迎回来ioPanel');
        _logined = true;
        document.location.href = '#/view/index';
    }else{
        document.location.href = '#/user/login';
    }
}

// 判断url信息
var page = {
    to:function(p){
        if(!_logined) {
            windows.create({
                move:true,
                title:'登录',
                body:`<div style="padding:1rem;">
                <h1 class="lb">快速登录</h1>
                <p>用户名<input type="text" class="line-input" required id="login_user"></p>
                <p>密 码 <input type="password" class="line-input" required minlength="8" id="login_pw"></p>
                <p><button type="button" onclick="page.user._submit(this);">提交</button><a href="">忘记密码?</a></p>`,
                css:{
                    width:'400px',
                    height:'400px'
                }
            });
            return msg.add('lightcoral','无法切换','请先登录!');
        }
        if(undefined == p) return msg.add('lightcoral','未知的URL?');
        p = p.split('/',2);
        if(typeof this[p[0]] != 'object' || undefined == this[p[0]][p[1]]) 
            return msg.add('lightcoral','不存在',`访问不存在的页面${p[0]}->${p[1]}`);
        else try{
            this[p[0]][p[1]]();
        }catch(e){
            msg.add('lightcoral','出错','页面出了点问题...');
            throw e;
        }
    },
    mode:{
        sun:'',
        moon:''
    },
    // 亮暗模式
    switch:function(mode){
        var e1 = $('#mode_moon'),e2 = $('#mode_sun'),e3 = $('#switch_style');
        if(mode == 'sun'){
            e1.hide();
            e2.show();
            e3.html(this.mode.sun);
        }else{
            e1.show();
            e2.hide();
            e3.html(this.mode.moon);
        }
    },
    // 预览
    view : {
        loaded: false,
        index:function(s){
            if(this.loaded && !s) return;
            $.get('/panel/api/getInfo',function(data,status){
                try{
                    var d = JSON.parse(data);
                }catch(e){
                    return msg.add('lightcoral','错误','无法解析的内容');
                    throw e;
                }
                var time;
                if(d.table.runtime < 60) time = d.table.runtime+'秒';
                else if(d.table.runtime < 3600) time = parseInt(d.table.runtime / 60)+'分';
                else if(d.table.runtime < 86400) time = parseInt(d.table.runtime / 3600)+'时';
                else time = parseInt(d.table.runtime / 86400)+'天';
                $('#table_visit').html(d.table.visit);
                $('#table_comments').html(d.table.comments);
                $('#table_runtime').html(time);
                $('#table_posts').html(d.table.posts);
                $('#table_ip').html(d.table.ip);
                $('#table_version').html(d.table.version);
                goChart(d.chart);
                var tmp = '';
                for (d in d.comments ){
                    tmp += `<div class="commentList">
                        <p>
                            <a href="${d.link == null ? '#/user/'+d.author : d.link}"<b>${d.author}</b>
                            ${d.email}
                        </p>
                        <p>${d.content}</p>
                    </div>`;
                }
                $('#comment').html(tmp);
            });
            $('#home_msg').load('//blog.imzlh.top/api/home_msg.html');
            this.loaded = true;
        }
    },
    // 用户类型
    user:{
        _submit:function(self){
            $(self).html('稍等').attr('disabled',true);
            $.ajax({
                'url'   : '/panel/api/login',
                'type'  : 'POST',
                'cache' : false,
                'timeout':3,
                'data' : {
                    'user': $('#login_user').val(),
                    'pw'  : $('#login_pw').val(),
                },
                'success':function(){
                    msg.add('lightgreen','成功','登陆成功!开始初始化...',3);
                },
                'error':function(data){
                    msg.add('lightcoral','失败',data.responseText,5);
                    $(self).attr('disabled',false).html('重试');
                }
            });
        },
        exit:function(){
            windows.create({
                move:true,
                head:'退出',
                body:`<p>确定吗?<p>
                    <button onclick="
                        cookie.del('_token');
                        msg.add('lightgreen','成功','3秒后刷新界面:)');
                        setTimeout(function(){'document.location.reload();},3000);
                    ">确定</button>`,
                css:{
                    width : '300px',
                    height: '200px'
                }
            })
        }
    },
    // 文章类
    post:{
        write:function(post){
            var date = new Date(),content;
            var run = function(content){
                windows.create({
                    move:true,
                    head:content.head,
                    body:`<div style="margin:10px" class="box">
                        <!-- 左侧标题和内容 -->
                        <div style="width: 70%;" class="__full box">
                            <textarea class="_text lg-input">${content.content}</textarea>
                            <div style="width:49vw;background-color:white;margin:0;box-sizing:border-box;padding:10px;" class="hide __view"></div>
                        </div>
                        <!-- 右侧文章信息编辑 -->
                        <div style="width:20%;">
                            <div class="svgGroup">
                                <svg fill="currentColor" onclick="page.post.fullScreen(this)" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M5.828 10.172a.5.5 0 0 0-.707 0l-4.096 4.096V11.5a.5.5 0 0 0-1 0v3.975a.5.5 0 0 0 .5.5H4.5a.5.5 0 0 0 0-1H1.732l4.096-4.096a.5.5 0 0 0 0-.707zm4.344 0a.5.5 0 0 1 .707 0l4.096 4.096V11.5a.5.5 0 1 1 1 0v3.975a.5.5 0 0 1-.5.5H11.5a.5.5 0 0 1 0-1h2.768l-4.096-4.096a.5.5 0 0 1 0-.707zm0-4.344a.5.5 0 0 0 .707 0l4.096-4.096V4.5a.5.5 0 1 0 1 0V.525a.5.5 0 0 0-.5-.5H11.5a.5.5 0 0 0 0 1h2.768l-4.096 4.096a.5.5 0 0 0 0 .707zm-4.344 0a.5.5 0 0 1-.707 0L1.025 1.732V4.5a.5.5 0 0 1-1 0V.525a.5.5 0 0 1 .5-.5H4.5a.5.5 0 0 1 0 1H1.732l4.096 4.096a.5.5 0 0 1 0 .707z"/>
                                </svg>
                                <svg  fill="currentColor" onclick="page.post.save(this,'${content.save}')" viewBox="0 0 16 16">
                                    <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
                                </svg>
                            </div>
                            <p><input type="text" class="_title input" value="${content.title}"></p>
                            <p>日期:<input type="datetime-local" class="_date input" value="${content.date}"></p>
                            <p>分类:<input type="text" value="${content.cate}" class="input _cate"></p>
                            <p>标签:<input type="text" value="${content.tags}" class="input _tags"></p>
                            <p>多个标签用英文逗号隔开,请添加方括号!</p>
                        </div>
                    </div>`,
                    css:{
                        width:'800px',
                        height:'80vh'
                    }
                });
            }
            if(undefined != post) $.get('/panel/api/postGet?id='+post,c=>{run(JSON.parse(c));});
            else run({
                save    : '/panel/api/savePost',
                content : '# 在这里写MarkDown',
                head    : '写新文章',
                title   : '标题',
                tags    : '[ioBlog,ioblog]',
                cate    : 'default',
                date    : `${date.getFullYear()}-${date.getMonth()<10?'0'+date.getMonth():date.getMonth()}-${date.getDay()<10?'0'+date.getDay():date.getDay()}T${date.getHours()<10?'0'+date.getHours():date.getHours()}:${date.getMinutes()<10?'0'+date.getMinutes():date.getMinutes()}:${date.getSeconds()<10?'0'+date.getSeconds():date.getSeconds()}`
            });
        },
        // 请求全屏
        fullScreen:function(self){
            // 选取源DIV元素
            var select = $(self.parentElement.parentElement.parentElement),
                view = select.children().children('.__view'),
                input = select.children().children('._text');
            // 全屏
            select.children('.__full')[0].requestFullscreen();
            view.show();
            // 自动刷新
            input.on('change',function(){
                // 刷新值
                view.html(marked.parse(input.val()));
            }).css({'width':'50vw'});
            // 右键取消
            select.on('contextmenu',function(){
                document.exitFullscreen();  // 取消全屏
                view.hide();                // 隐藏全屏
                select.on('contextmenu',null)// 清空事件
                    .css({'width':'100%'}); // 最大化
                input.on('change',null);    // 清空自动刷新
                return false;               // 屏蔽事件
            });
        },
        save:function(self){
            var select = $(self.parentElement.parentElement).children('p');
            $.ajax({
                type : 'POST',
                url  : '/panel/api/savePost',
                data : {
                    content : select.parent().parent().children().children('._text').val(),
                    title   : select.children('._title').val(),
                    cate    : select.children('._cate').val(),
                    tags    : select.children('._tags').val(),
                    date    : select.children('._date').val()
                },
                success : function(){
                    msg.add('lightgreen','成功','您的文章保存成功啦!');
                },error : function(xhr){
                    msg.add('lightcoral','失败',xhr.responseText);
                }
            });
        },
        manage:function(){
            $.get('/panel/api/getPost',function(data,status){
                if(status != 'success') return msg.add('lightcoral','获取列表失败',data);
                var tmp = '<ul class="group">',p = JSON.parse(data),e;
                for(var i=0;i<p.length;i++ ){
                    e = p[i];
                    tmp += `<li class="overShow">
                        <p><b>${e.title}</b> ${e.date}</p>
                        <p>${e.view}</p>
                        <div class="__show hide">
                            <i class="hide" onclick="$(this).get('/panel/api/hidePost?id=${e.time}');">隐藏</i>
                            <i class="del" onclick="page.post.del(${e.time})">删除</i>
                            <i class="save" onclick="page.post.write(${e.time});">编辑</i>
                        </div>
                    </li>`;
                }
                tmp +='</ul>';
                this.e = windows.create({
                    move:true,
                    head:'文章管理',
                    body:tmp
                });
            });
        },
        // 删除文章
        del:function(time){
            window.create({
                head:'确定删除',
                body:`<p>您可以通过"隐藏"让文章不再显示</p>
                    <p>强制删除将无法找回，继续？</p>
                    <button onclick="$(this).get('/panel/api/delPost?id=${time}');">继续</button>`,
                move:true,
                css:{
                    width:'300px',
                    height:'200px'
                }
            })
        },
    },
    // 插件类
    plug : {
        manage:function(){
            if(undefined == this.cache) $.get('/panel/api/pluginList',function(res,sta){
                if(sta != 'success') msg.add('lightcoral','读取错误',res);
                else page.plug.list(page.plug.cache = res);
            });
            else page.plug.list(page.plug.cache);
        },
        store:function(){
            windows.create({
                head    :   'io轻商店',
                body    :   '<iframe style="border:none;width:100%;height:calc(100% - 3.5rem);" src="http://blog.imzlh.top/api/appStore.php"></iframe>',
                move    :   true
            });
        },
        list:function(data){
            var tmp = '<ul class="group">',p = JSON.parse(data),e;
            for(var i=0;i<p.length;i++ ){
                e = p[i];
                tmp += `<li class="overShow">
                    <p onclick="page.plug.edit('${e.info.name}');"><b>${e.info.name} </b> 版本:${e.info.version}</p>
                    <p><b>作者</b><a href="${e.info.link}"><i>${e.info.author}</i></a></p>
                    <p><b>描述:</b>${e.info.desc}</p>
                    <div class="__show hide">
                        <i class="del">禁用</i>
                        <i class="update" onclick="$(this).get('http://blog.imzlh.top/api/appUpdate.php?${e.info.name}=${e.info.version}')">检查更新</i>
                    </div>
                </li>`;
            }
            tmp +='</ul>';
            windows.create({
                move:true,
                head:'插件管理',
                body:tmp
            });
        },
        newConfig:function(d){
            var name,e = d.config,val,conf;
            tmp += `<h3><a href="${e.info.link}"><x> # </x> ${e.info.name} </a></h3><p>描述: ${e.info.desc}</p>`;
            for(key in e.config){
                val = e.config[key],conf = e.value[key];
                switch(val.type){
                    case 'input':
                        tmp += `<p>${val.name}<input type="${val.itype}" value="${this.null(conf,val.default)}" onchange="text.check${val.itype}(this);" name="${key}.${val.name}"></p>`
                        break;
                    case 'select':
                        tmp += `<p>${val.name}:<select name="${key}">`
                        for(key in val.item){
                            name = val.item[key];
                            tmp += `<option value="${name}" ${this.null(conf,val.default) == name?'selected':''}>${key}</option>`;
                        } 
                        tmp += `</select></p>`;
                        break;
                    default:
                        tmp += `<h1>该插件含有无效配置项${val.type}！</h1>`;
                        break;
                }
                if(val.desc != undefined) tmp += `<p><b>提示</b> : ${val.desc}</p>`;
            }
        },
        null:function(a,b){
            return (undefined == a || a == null)?b:a;
        },
        add:function(){
            windows.create({
                head    :   '拖拽上传',
                body    :   `<div class="fileDrop" onclick="$(this).next().click();"></div>
                    <input type="file" onchange="page.plug.fileUpload(this.files[0]);" style="display:none;">`,
                move    :   true,
                css     :   {
                    width:'200px',
                    height:'300px'
                }
            }).on('dragenter',function(e){
                e.preventDefault();
                e.stopPropagation();
                msg.add('文件','松开开始上传文件!');
                $(this).css('background-color','lightblue');
            }).on('drop',function(e) {
                e.preventDefault();
                this.fileUpload(e.dataTransfer.files);
                return false;
            }).on('dragleave',function(){
                msg.add('文件','怎么又移动走了？');
                $(this).css('background-color','white');
            });
        },
        fileUpload:function(file){
            var formData = new FormData();
            formData.append("Plugin", file);
            $.ajax({
                url :   '/panel/api/upload',
                data:   formData,
                type:   'POST',
                cache:  false,
                contentType:'multipart/form-data',
                processData: false,
                beforeSend:function(){
                    msg.add('lightgreen','上传','开始上传...');
                },
                success:function(res){
                    windows.create({
                        head    :   '安装'+d.info.name,
                        body    :   `<h1>⚠不安全!</h1>
                        <p> 盲目启动插件可能会产生危害，继续?</p>
                        <button onclick="$('body').append('<script src="${res}"></script>')">执行安装程序</button>`,
                        move    :   true
                    });
                },
                error:function(xhr){
                    return msg.add('lightcoral','上传失败',xhr.responseText);
                }
            })
        }
    },
    // 非页面类
    action : {
        panel:function(){
            windows.create({
                move:true,
                head:'快捷入口',
                body:'<h1>TODO</h1>'
            });
        }
    }
}

// 右键监听
document.oncontextmenu = function(e){
    $('#dropMenu').css({
        left : e.clientX,
        top  : e.clientY
    }).show();
    document.onclick = ()=>{
        $('#dropMenu').hide();
        document.onclick = null;
    };
    return false;
}

// 这个是cookie操作类
var cookie = {  
    /**
     * 获取一个cookie
     */
    get : function(name){
        let arr = document.cookie.match(new RegExp("(^| )" + name + "=([^;]*)(;|$)"));
        if (arr != null) return decodeURI(arr[2]); 
        return null;
    },

    /**
     * 删除一个cookie
     */
    del : function (name){
        var exp = new Date(),cval;
        exp.setTime(exp.getTime() - 1);
        if(cval = this.get(name) != null) 
            document.cookie = name + "=" + cval + ";expires=" + exp.toGMTString()+";path=/";
        else return false;
    }
};

// MSG:消息弹出框
var msg = {
    /**
     * 消息数量，销毁时自减
     */
    count : 0,

    /**
     * 弹出一个模态框
     */
    alert:function(){

    },

    /**
     * 弹出一个右侧消息
     */
    add : function(type,title,body,timeout = 5){
        this.count ++;
        var data = `<div class="alert" style="background-color:${type}" id="msg_${this.count}">
            <b class="btn-close" onclick="this.parentElement.remove()">${title}</b>
            ${body}
        </div>`;
        var count = this.count;
        if(false != timeout) setTimeout(function(){
            $(`#msg_${count}`).remove();
        },timeout * 1000);
        $('#msgBox').append(data);
    }
}

// 比msg.alert更高效:window
var windows = {
    i : 0, // ID计数器 
    /**
     * 让内容可被拖拽
     */
    moveable : function(e){
        var x = 0,y = 0,xOffset = 0,yOffset = 0;
        // 拖拽Window Head时的操作
        e.children('.whead').on('mousedown',function (m) {
            // 初始化位置
            x = m.clientX , y = m.clientY;
            document.onmousemove = function(m2) {
                // 读取X轴/Y轴偏移的位置
                xOffset = m2.clientX - x;
                yOffset = m2.clientY - y;
                // 刷新鼠标当前位置
                x = m2.clientX;
                y = m2.clientY;
                // 变化绝对定位
                e.css({
                    'left': `${e[0].offsetLeft + xOffset}px`,
                    'top' : `${e[0].offsetTop + yOffset}px`
                });
                // 清除默认事件
                return false;
            };
            return false;
        }).on('mouseup',function() {
            // 清除拖拽事件
            document.onmousemove = null;
        }).on('click',function(){
            // 调整至前
            $('.window').css('z-index',2);
            e.css('z-index',3);
        }).dblclick(function(){
            if(e.attr('full') == 'true') e.removeClass('fullScreen');
            else e.addClass('fullScreen').attr('full','true');
        });
    },

    /**
     * 创建一个窗口
     */
    create:function(opt){
        this.i++;
        var content = `<div class="window" style=" " id="window_${this.i}">
        <div style="background-color:${undefined == opt.color ? 'lightgray' : opt.color}" class="whead ${true == opt.move ? 'moveable' : ''}">
            ${opt.head}
            <span class="btn-close" onclick="this.parentElement.parentElement.remove()"></span>
        </div>
        ${opt.body}
    </div>`;
        $('body').append(content);
        var e = $('#window_'+this.i);
        if(undefined != opt.css) e.css(opt.css);
        if(opt.move == true) this.moveable(e);
        return e;
    },
    /**
     * 隐藏所有窗口
     */
    hideAll:function(){
        $('.window').slideToggle();
    },
    /**
     * 销毁所有窗口
     */
    destory:function(noask){
        if(noask) $('.window').remove();
        else this.create({
            head:'确定吗?',
            body:'<h1>未保存的内容也会被关闭</h1><button onclick="windows.destory(true);">知晓,执意关闭</button>',
            css:{
                width:'400px',
                height:'200px',
                top:'40%',
                left:'40%'
            }
        })
    }
};

function goChart(dataArr){
    // 声明所需变量
    var canvas,ctx;
    // 图表属性
    var cWidth, cHeight, cMargin, cSpace;
    var originX, originY;
    // 折线图属性
    var tobalDots, dotSpace, maxValue;
    var totalYNomber;
    // 运动相关变量
    var ctr, numctr, speed;

    // 获得canvas上下文
    canvas = document.getElementById("chart");
    if(canvas && canvas.getContext){
        ctx = canvas.getContext("2d");
    }
    initChart(); // 图表初始化
    drawLineLabelMarkers(); // 绘制图表轴、标签和标记
    drawLineAnimate(); // 绘制折线图的动画

    // 图表初始化
    function initChart(){
        // 图表信息
        cMargin = 60;
        cSpace = 80;
        canvas.width = Math.floor( (window.innerWidth-100)/2 ) * 2 ;
        canvas.height = 740;
        canvas.style.height = canvas.height/2 + "px";
        canvas.style.width = canvas.width/2 + "px";
        cHeight = canvas.height - cMargin - cSpace;
        cWidth = canvas.width - cMargin - cSpace;
        originX = cMargin + cSpace;
        originY = cMargin + cHeight;

        // 折线图信息
        tobalDots = dataArr.length;
        dotSpace = parseInt( cWidth/tobalDots );
        maxValue = 0;
        for(var i=0; i<dataArr.length; i++){
            var dotVal = parseInt( dataArr[i][1] );
            if( dotVal > maxValue ){
                maxValue = dotVal;
            }
        }
        maxValue += 50;
        totalYNomber = 10;
        // 运动相关
        ctr = 1;
        numctr = 100;
        speed = 6;

        ctx.translate(0.5,0.5);  // 当只绘制1像素的线的时候，坐标点需要偏移，这样才能画出1像素实线
    }

    // 绘制图表轴、标签和标记
    function drawLineLabelMarkers(){
        ctx.font = "24px Arial";
        ctx.lineWidth = 2;
        ctx.fillStyle = "#566a80";
        ctx.strokeStyle = "#566a80";
        // y轴
        drawLine(originX, originY, originX, cMargin);
        // x轴
        drawLine(originX, originY, originX+cWidth, originY);

        // 绘制标记
        drawMarkers();
    }

    // 画线的方法
    function drawLine(x, y, X, Y){
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(X, Y);
        ctx.stroke();
        ctx.closePath();
    }

    // 绘制标记
    function drawMarkers(){
        ctx.strokeStyle = "#E0E0E0";
        // 绘制 y 轴 及中间横线
        var oneVal = parseInt(maxValue/totalYNomber);
        ctx.textAlign = "right";
        for(var i=0; i<=totalYNomber; i++){
            var markerVal =  i*oneVal;
            var xMarker = originX-5;
            var yMarker = parseInt( cHeight*(1-markerVal/maxValue) ) + cMargin;
            
            ctx.fillText(markerVal, xMarker, yMarker+3, cSpace); // 文字
            if(i>0){
                drawLine(originX+2, yMarker, originX+cWidth, yMarker);
            }
        }
        // 绘制 x 轴 及中间竖线
        ctx.textAlign = "center";
        for(var i=0; i<tobalDots; i++){
            var markerVal = dataArr[i][0];
            var xMarker = originX+i*dotSpace;
            var yMarker = originY+30;
            ctx.fillText(markerVal, xMarker, yMarker, cSpace); // 文字
            if(i > 0) drawLine(xMarker, originY-2, xMarker, cMargin);
        }
        // 绘制标题 y
        ctx.save();
        ctx.rotate(-Math.PI/2);
        ctx.fillText("访问量", -canvas.height/2, cSpace-10);
        ctx.restore();
        // 绘制标题 x
        ctx.fillText("日", originX+cWidth/2, originY+cSpace/2+20);
    };

    //绘制折线图
    function drawLineAnimate(){
        ctx.strokeStyle = "#566a80";  //"#49FE79";

        //连线
        ctx.beginPath();
        for(var i=0; i<tobalDots; i++){
            var dotVal = dataArr[i][1];
            var barH = parseInt( cHeight*dotVal/maxValue* ctr/numctr );
            var y = originY - barH;
            var x = originX + dotSpace*i;
            i==0?ctx.moveTo( x, y ):ctx.lineTo( x, y );
        }
        ctx.stroke();

        //背景
        ctx.lineTo( originX+dotSpace*(tobalDots-1), originY);
        ctx.lineTo( originX, originY);
        //背景渐变色
        //柱状图渐变色
        var gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(133,171,212,0.6)');
        gradient.addColorStop(1, 'rgba(133,171,212,0.1)');
        ctx.fillStyle = gradient;
        ctx.fill();
        ctx.closePath();
        ctx.fillStyle = "#566a80";

        //绘制点
        for(var i=0; i<tobalDots; i++){
            var dotVal = dataArr[i][1];
            var barH = parseInt( cHeight*dotVal/maxValue * ctr/numctr );
            var y = originY - barH;
            var x = originX + dotSpace*i;
            drawArc( x, y );  //绘制点
            ctx.fillText(parseInt(dotVal*ctr/numctr), x+15, y-8); // 文字
        }

        if(ctr<numctr){
            ctr++;
            setTimeout(function(){
                ctx.clearRect(0,0,canvas.width, canvas.height);
                drawLineLabelMarkers();
                drawLineAnimate();
            }, speed);
        }
    }

    //绘制圆点
    function drawArc( x, y, X, Y ){
        ctx.beginPath();
        ctx.arc( x, y, 3, 0, Math.PI*2 );
        ctx.fill();
        ctx.closePath();
    }
}