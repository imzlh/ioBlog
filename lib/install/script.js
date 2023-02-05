/**
 * ioBlog 安装器
 * 2023 ioGroup copyright
 * @document http://imzlh.top/project/doc/#/install
 */

window.onload = function(){
    location.href = '#/hello';
    page.change();
    audio.play();
    plug.list();
    document.getElementById('_hello').remove();
}

var ssl_file = 0,page = {
    change:function(){
        var page = location.hash.substring(2,),e = document.getElementsByClassName('page');
        if(page == undefined) return;
        for(var i=0 ; i<e.length ; i++) e[i].style.display = 'none';
        var p = document.getElementById(`page_${page}`);
        if(p == undefined) msg.alert('页面不存在!');
        else p.style.display = 'block';
    }
},audio = {
    e:{
        play  : document.getElementById('_play'),
        pause : document.getElementById('_stop'),
        audio : document.getElementById('_audio')
    },
    play : function(){
        try{
            this.e.audio.play();
        }catch(e){
            return msg.alert('您的浏览器不支持audio');
        }
        this.e.pause.style.display = this.e.audio.paused === true?'none':'block',
        this.e.play.style.display  = this.e.audio.paused === true?'block':'none';
    },
    pause:function(){
        try{
            this.e.audio.pause();
        }catch(e){
            return msg.alert('您的浏览器不支持audio');
        }
        this.e.pause.style.display = 'none',
        this.e.play.style.display = 'block';
    }
},upload={
    e : {
        cer : document.getElementById('_config_cer'),
        key : document.getElementById('_config_key')
    },
    join:function(){
        var formData = new FormData();
        formData.append("cer", this.e.cer.files);
        formData.append("key", this.e.key.files);
        return formData;
    }
    ,run:function(){
        if(ssl_file <= 2) return msg.alert('失败:请正确选择文件!');
        ajax = new XMLHttpRequest();
        ajax.onreadystatechange=function(){
            if (ajax.readyState !=4 ) return;
            if (ajax.status != 200) msg.alert('上传失败!');
            msg.succ('SSL上传成功!!!');
            ssl = ajax.responseText;
        }
        ajax.open('POST','/upload');
        ajax.send(this.join());
    }
},msg = {
    e : document.getElementById('_msg'),
    succ:function(msg){
        this.show(msg,'lightgreen');
    },
    alert:function(msg){
        this.show(msg,'LightCoral');
    },
    show:function(msg,color){
        this.e.innerHTML = msg;
        this.e.style.backgroundColor = color;
        this.e.style.display = 'block';
        setTimeout(function(){
            document.getElementById('_msg').style.display = 'none';
        },5000);
    }
}
// ,plug = {
//     e:document.getElementById('page_plugin')
//     ,list:function(){
//             var ajax = new XMLHttpRequest();
//             ajax.onreadystatechange = function(){
//                 if(ajax.readyState != 4) return;
//                 if(ajax.status != 200) {
//                     document.getElementById('page_plugin').innerHTML = '<h1>失败!</h1>';
//                 }else{
//                     document.getElementById('page_plugin').innerHTML = '<h1>加载中...</h1>';
//                     var tmp = '',key,val,name;
//                     JSON.parse(ajax.responseText).forEach(e => {
//                         tmp += `<h3><a href="${e.info.link}"><x> # </x> ${e.info.name} </a></h3>
// <p>描述: ${e.info.desc}</p>`;
//                         for(key in e.config){
//                             val = e.config[key];
//                             switch(val.type){
//                                 case 'input':
//                                     tmp += `<p>${val.name}<input type="${val.itype}" value="${val.default}" onchange="text.check${val.itype}(this);" name="${key}.${val.name}"></p>`
//                                     break;
//                                 case 'select':
//                                     tmp += `<p>${val.name}:<select name="${key}">`
//                                     for(key in val.item){
//                                         name = val.item[key];
//                                         tmp += `<option value="${name}" ${val.default == name?'selected':''}>${key}</option>`;
//                                     } 
//                                     tmp += `</select></p>`;
//                                     break;
//                                 default:
//                                     tmp += `<h1>该插件含有无效配置项${val.type}！</h1>`;
//                                     break;
//                             }
//                             if(val.desc != undefined) tmp += `<p><b>提示</b> : ${val.desc}</p>`;
//                         }
//                     });
//                 }
//                 document.getElementById('page_plugin').innerHTML = tmp+'<button onclick="window.location.href=\'#/configure\';" type="button">完毕,返回</button>';
//             }
//             ajax.open('GET','/plugin');
//             ajax.send();
//         }
// },
select = {},text={
    mail:/^(\w-*\.*)+@(\w-?)+(\.\w{2,})+$/,
    checkmail:function(self){
        var val = self.value;
        if(null == val.match(this.mail)) self.style.color = 'red';
        else self.style.color = 'green';
    },
    checktext:function(){
        return;
    },
    checkurl:function(){
        return;
    }
},ssl = {
    status:false,
    cer:'',
    key:''
}

function send(){
    var ajax = new XMLHttpRequest(),param = `desc=${document.getElementById('desc').value}&param=${document.getElementById('param').value}&url=${document.getElementById('url').value}&ext=${document.getElementById('ext').value}&port=${document.getElementById('port').value}&max=${document.getElementById('max').value}&task=${document.getElementById('task').value}&name=${document.getElementById('name').value}&mtl=${document.getElementById('counts').value}&ssl=${ssl}&view=${document.getElementById('view').value}&source=${document.getElementById('source').value}`
    ajax.open('POST','/submit');
    ajax.onerror = function(){
        // alert('失败！');
        window.location.href = '#/error';
    }
    ajax.onload = function(){
        location.href = '#/success';
    }
    ajax.setRequestHeader('Content-Type','application/x-www-form-urlencoded')
    ajax.send(param);
}