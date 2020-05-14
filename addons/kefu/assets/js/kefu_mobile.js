/**
 * Kefu v1.0.2
 * FastAdmin在线客服系统-手机调用客服单页
 * https://www.fastadmin.net/store/kefu.html
 *
 * Copyright 2019 白衣素袖
 *
 * FastAdmin在线客服系统不是开源产品，所有文字、图片、样式、风格等版权归在线客服作者所有，如有复制、仿冒、抄袭、盗用，FastAdmin和在线客服作者将追究法律责任
 *
 * Released on: October 15, 2019
 */

// 音频播放器
window.AudioContext = window.AudioContext || window.webkitAudioContext || window.mozAudioContext || window.msAudioContext;

var KeFu = {
    ws: {
        SocketTask: null,
        Timer: null,// 计时器
        ErrorMsg: [],// 发送失败的消息
        MaxRetryCount: 3,// 最大重连次数
        CurrentRetryCount: 0,// 当前重试次数
        url: null
    },
    audio: {
        context: new window.AudioContext(),
        source: null,
        buffer: null
    },
    cookie_prefix: "",// 同config.php里边的cookie前缀设置，未修改过请忽略
    config: null, // 初始化后,包含所有的客服插件配置
    url: null,
    fixed_csr: 0,
    token_list: [],
    csr: "",// 当前用户的客服代表ID
    session_id: 0,// 会话ID
    session_user: "",// 带身份标识的用户ID
    show_send: false, // 是否显示发送按钮
    emoji_config:[
        {'title':'[zy]', 'src':'/emoji/1.png'},
        {'title':'[zm]', 'src':'/emoji/2.png'},
        {'title':'[jy]', 'src':'/emoji/3.png'},
        {'title':'[jyb]', 'src':'/emoji/4.png'},
        {'title':'[bx]', 'src':'/emoji/5.png'},
        {'title':'[kzn]', 'src':'/emoji/6.png'},
        {'title':'[gg]', 'src':'/emoji/7.png'},
        {'title':'[ll]', 'src':'/emoji/8.png'},
        {'title':'[jyll]', 'src':'/emoji/9.png'},
        {'title':'[o]', 'src':'/emoji/10.png'},
        {'title':'[yz]', 'src':'/emoji/11.png'},
        {'title':'[wx]', 'src':'/emoji/12.png'},
        {'title':'[zyb]', 'src':'/emoji/13.png'},
        {'title':'[tp]', 'src':'/emoji/14.png'},
        {'title':'[wxb]', 'src':'/emoji/15.png'},
        {'title':'[zyc]', 'src':'/emoji/16.png'},
        {'title':'[llb]', 'src':'/emoji/17.png'},
        {'title':'[xm]', 'src':'/emoji/18.png'},
        {'title':'[qz]', 'src':'/emoji/19.png'},
        {'title':'[zmb]', 'src':'/emoji/20.png'},
        {'title':'[kx]', 'src':'/emoji/21.png'},
        {'title':'[mm]', 'src':'/emoji/22.png'},
        {'title':'[bz]', 'src':'/emoji/23.png'},
        {'title':'[bkx]', 'src':'/emoji/24.png'},
        {'title':'[mg]', 'src':'/emoji/25.png'},
        {'title':'[pz]', 'src':'/emoji/26.png'},
        {'title':'[pzb]', 'src':'/emoji/27.png'},
        {'title':'[wxc]', 'src':'/emoji/28.png'},
        {'title':'[jyc]', 'src':'/emoji/29.png'},
        {'title':'[jyd]', 'src':'/emoji/30.png'},
        {'title':'[dm]', 'src':'/emoji/31.png'},
        {'title':'[tpb]', 'src':'/emoji/32.png'},
        {'title':'[tpc]', 'src':'/emoji/33.png'},
        {'title':'[tpd]', 'src':'/emoji/34.png'},
        {'title':'[ly]', 'src':'/emoji/35.png'},
        {'title':'[zyd]', 'src':'/emoji/36.png'},
    ],
    initialize: function (url = document.domain, modulename = 'index', fixed_csr = 0) {

        KeFu.url = url;
        let url_fixed_csr = KeFu.getQueryVariable('fixed_csr');
        KeFu.fixed_csr = url_fixed_csr ? url_fixed_csr : fixed_csr;
        var initialize_url = KeFu.buildUrl(url, modulename, 'initialize');

        $.ajax({
            url: initialize_url,
            success: function (data) {

                if (data.code == 401) {
                    layer.msg(data.msg);
                    return;
                } else if (data.code != 1) {
                    layer.msg(data.msg);
                    return;
                }

                KeFu.config = data.data.config;
                KeFu.token_list = data.data.token_list;

                if (data.data.new_msg) {
                    KeFu.new_message_prompt();
                } else if (!KeFu.getCookie('kefu_new_user')) {
                    KeFu.new_message_prompt();
                }

                // 构建ws和上传文件的url
                KeFu.ws.url = KeFu.buildUrl(url, modulename, 'ws', KeFu.config.websocket_port);
                KeFu.config.upload.uploadurl = KeFu.buildUrl(url, modulename, "upload");

                // 初始化表情
                if (!$('#kefu_emoji').html()) {

                    for (var i in KeFu.emoji_config){
                        $('#kefu_emoji').append(KeFu.buildChatImg(KeFu.emoji_config[i].src, KeFu.emoji_config[i].title, 'emoji'));
                    }

                }

                // 立即链接 Websocket
                KeFu.ConnectSocket();
            }
        });

        KeFu.eventReg();
    },
    ConnectSocket: function(){
        if ("WebSocket" in window) {
            var ws = new WebSocket(KeFu.ws.url);
            KeFu.ws.SocketTask = ws;

            ws.onopen = function () {

                // 重新发送所有出错的消息
                if (KeFu.ws.ErrorMsg.length > 0) {

                    for (let i in KeFu.ws.ErrorMsg) {
                        KeFu.ws_send(KeFu.ws.ErrorMsg[i]);
                    }

                    KeFu.ws.ErrorMsg = [];
                }

                if (KeFu.ws.Timer != null) {
                    clearInterval(KeFu.ws.Timer);
                }

                KeFu.ws.Timer = setInterval(KeFu.ws_send, 28000);//定时发送心跳
            };

            ws.onmessage = function (evt) {
                var msg = $.parseJSON(evt.data);
                KeFu.domsg(msg);
            };

            ws.onclose = function (e) {

                if (KeFu.ws.Timer != null) {
                    clearInterval(KeFu.ws.Timer);
                }

                KeFu.ws.ws_error = true;
                $('#kefu_error').html('网络链接已断开');
                if (KeFu.ws.MaxRetryCount) {
                    KeFu.ws.Timer = setInterval(KeFu.retry_webSocket, 3000);//每3秒重新连接一次
                }
            };

            ws.onerror = function (e) {
                // 错误
                KeFu.ws.ws_error = true;
                $('#kefu_error').html('WebSocket 发生错误');
                layer.msg('WebSocket 发生错误');
            };
        } else {
            KeFu.ws.ws_error = true;
            layer.msg(KeFu.config.chat_name + '：您的浏览器不支持 WebSocket!');
        }
    },
    retry_webSocket: function () {
        if (KeFu.ws.CurrentRetryCount < KeFu.ws.MaxRetryCount) {
            KeFu.ws.CurrentRetryCount++;
            KeFu.ConnectSocket();
            console.log('重连 WebSocket 第' + KeFu.ws.CurrentRetryCount + '次');
        } else {
            if (KeFu.ws.Timer != null) {
                clearInterval(KeFu.ws.Timer);
            }

            console.log('每隔10秒将再次尝试重连 WebSocket')
            KeFu.ws.Timer = setInterval(KeFu.ConnectSocket, 10000);//每10秒重新连接一次
        }
    },
    domsg: function (msg){
        if (msg.msgtype == 'initialize') {

            $('#kefu_error').html('');

            if (KeFu.ws.ws_error) {
                KeFu.ws.CurrentRetryCount = 0;
                KeFu.ws.ws_error = false;
            }

            if (msg.data.new_msg) {
                KeFu.new_message_prompt();
            }

            // 分配/获取客服->获取聊天记录
            var user_initialize = {
                c: 'Message',
                a: 'userInitialize',
                data: {
                    fixed_csr: KeFu.fixed_csr
                }
            };
            KeFu.ws_send(user_initialize);

        } else if (msg.msgtype == 'user_initialize') {
            // 用户客服分配结束
            if (msg.code == 1) {

                if (msg.data.session.user_tourists) {
                    KeFu.sendMessage = function () {
                        layer.msg('为保护您的隐私请,请登录后发送消息~');
                    }
                }

                KeFu.csr = msg.data.session.csr;
                KeFu.session_id = msg.data.session.id;
                KeFu.toggleWindowView('chat_wrapper');
                $('#header_title').html('客服 ' + msg.data.session.nickname + ' 为您服务');
                KeFu.change_csr_status(msg.data.session.csr_status);
            } else if (msg.code == 302) {

                if (!KeFu.csr) {

                    // 打开留言板
                    KeFu.csr = 'none';
                    KeFu.toggleWindowView('kefu_leave_message');
                    $('#header_title').html('当前无客服在线哦~');
                } else {
                    layer.msg('当前客服暂时离开,您可以直接发送离线消息~');
                }

            }
        } else if (msg.msgtype == 'leave_message') {

            layer.msg(msg.msg);
            $('#kefu_leave_message form')[0].reset()
        } else if (msg.msgtype == 'show_msg') {

            layer.msg(msg.msg);
        } else if (msg.msgtype == 'clear') {

            if (msg.msg) {
                layer.msg(msg.msg);
            }

            var clear = {
                c: 'Message',
                a: 'clear'
            };
            KeFu.ws_send(clear);

            KeFu.retry_webSocket = function () {clearInterval(KeFu.ws.Timer)};

        } else if (msg.msgtype == 'offline') {

            if (msg.user_id == KeFu.csr) {
                KeFu.change_csr_status(0);
            }

        } else if (msg.msgtype == 'online') {
            // 来自 admin 的用户上线了
            if (msg.modulename == 'admin') {

                if (msg.user_id == KeFu.csr) {
                    KeFu.change_csr_status(3);
                } else if (KeFu.csr == 'none') {
                    // 重新为用户分配客服代表
                    var user_initialize = {
                        c: 'Message',
                        a: 'userInitialize'
                    };
                    KeFu.ws_send(user_initialize);
                }
            }
        } else if (msg.msgtype == 'chat_record'){
            if (msg.data.page == 1) {
                $('.chat_wrapper').html('');
            }

            var chat_record = msg.data.chat_record;
            KeFu.chat_record_page = msg.data.next_page;

            for (let i in chat_record) {

                if (msg.data.page == 1) {
                    KeFu.buildPrompt(chat_record[i].datetime, msg.data.page);
                }

                for (let y in chat_record[i].data) {
                    KeFu.buildRecord(chat_record[i].data[y], msg.data.page)
                }

                if (msg.data.page != 1) {
                    KeFu.buildPrompt(chat_record[i].datetime, msg.data.page);
                }
            }

            if (msg.data.page == 1){
                setTimeout(function () {
                    $('.chat_wrapper').scrollTop($('.chat_wrapper')[0].scrollHeight);
                },150)
            }

        } else if (msg.msgtype == 'csr_change_status') {

            if (KeFu.csr == msg.data.csr) {
                KeFu.change_csr_status(msg.data.csr_status);
            }
        } else if (msg.msgtype == 'transfer_done') {

            KeFu.csr = msg.data.csr;
            $('#header_title').html('客服 ' + msg.data.nickname + ' 为您服务');

        } else if (msg.msgtype == 'new_message') {

            if ($('.chat_wrapper').children('.status').children('span').eq(0).html() == '还没有消息') {
                $('.chat_wrapper').children('.status').children('span').eq(0).html('刚刚');
            }

            KeFu.new_message_prompt();

            KeFu.buildRecord(msg.data, 1);

            if (msg.data.session_id == KeFu.session_id) {

                $('.chat_wrapper').scrollTop($('.chat_wrapper')[0].scrollHeight);

                var load_message = {
                    c: 'Message',
                    a: 'readMessage',
                    data: {
                        record_id: msg.data.record_id
                    }
                };

                KeFu.ws_send(load_message);
                return;
            }
        }
    },
    ws_send: function (message) {

        if (!message) {
            message = {c: 'Message', a: 'ping'};
        }

        if (KeFu.ws.SocketTask && KeFu.ws.SocketTask.readyState == 1) {
            KeFu.ws.SocketTask.send(JSON.stringify(message));
        } else {
            // console.log('消息发送出错', message)
            KeFu.ws.ErrorMsg.push(message);
        }

    },
    toggleWindowView: function(show_view_id){
        // 显示留言板
        if (show_view_id == 'kefu_leave_message') {
            $('#kefu_leave_message').show(100);
            $('.write').hide();
            $('.chat_wrapper').css('bottom', '0px');
        } else {
            $('#kefu_leave_message').hide();
            $('.write').show();
            $('.chat_wrapper').css('bottom', '52px');
        }
    },
    buildUrl: function (url, modulename, type = 'ws', wsport = 1818) {


        var protocol = window.location.protocol + '//';
        var port = window.location.port;
        port = port ? ':' + port:'';

        if (type == 'ws') {

            var token = '&token=' + (KeFu.token_list.kefu_token ? KeFu.token_list.kefu_token : '');
            var kefu_user = '&kefu_user=' + (KeFu.token_list.kefu_tourists_token ? KeFu.token_list.kefu_tourists_token : '');
            protocol = parseInt(KeFu.config.wss_switch) == 1 ? 'wss://':'ws://';

            return protocol + url + ':' + wsport + '?modulename=' +
                modulename + token + kefu_user;
        } else if (type == 'initialize') {

            return protocol + url + port + '/addons/kefu/index/initialize?modulename=' +
                modulename + '&referrer=' + document.referrer;
        } else if (type == 'upload') {

            return protocol + url + port + '/addons/kefu/index/upload?modulename=' +
                modulename;
        } else if (type == 'load_message_prompt') {

            return protocol + url + port + '/addons/kefu/index/loadMessagePrompt?modulename=' +
                modulename;
        }

    },
    kefu_message_change: function () {
        if (!KeFu.show_send) {
            KeFu.toggle_send_btn('show');
        }

        var el = $('#kefu_message'),el_height,chat_wrapper_bottom;
        if (el.val() == ''){
            el_height = 35;
            KeFu.toggle_send_btn('hide');
        } else {
            el_height = el[0].scrollHeight;
        }

        el.css('height', el_height + 'px');

        chat_wrapper_bottom = (el_height > 35) ? 70 : 52;

        $('.chat_wrapper').css('bottom', chat_wrapper_bottom + 'px');
        $('#kefu_emoji').css('bottom', chat_wrapper_bottom + 'px');
    },
    eventReg: function () {

        // 聊天图片预览
        $(document).on('click', '.bubble img', function (e) {

            var img_obj = $(e.target);
            if (img_obj.hasClass('emoji')){
                return ;
            }
            img_obj = img_obj[0];

            layer.photos({
                photos: {
                    "title":"聊天图片预览",
                    "id":"record",
                    data:[
                        {
                            "src":img_obj.src
                        }
                    ]
                },anim: 5 //0-6的选择，指定弹出图片动画类型，默认随机
            });

        });

        // 显示表情选择面板
        $(document).on('click', '.smiley', function (e) {
            $('#kefu_emoji').toggle(200);
            // 获取焦点
            $('#kefu_message').focus();
        });

        // 选择表情
        $(document).on('click', '#kefu_emoji img', function (e) {
            $('#kefu_message').val($('#kefu_message').val() + $(e.target).data('title'));
            KeFu.kefu_message_change();
            $('#kefu_emoji').hide();
            // 获取焦点
            $('#kefu_message').focus();
        });

        // 用户点击聊天记录窗口，隐藏表情面板
        $(document).on('click', '.chat_wrapper,#chatfile', function () {
            $('#kefu_emoji').hide();
        });

        // 用户选择了文件
        $(document).on('change', '#chatfile', function (e) {

            if (!$('#chatfile')[0].files[0]) {
                return;
            }

            // 上传文件
            var formData = new FormData();
            formData.append("file", $('#chatfile')[0].files[0]);

            $.ajax({
                url: KeFu.config.upload.uploadurl, /*接口域名地址*/
                type: 'post',
                data: formData,
                contentType: false,
                processData: false,
                success: function (res) {
                    if (res.code == 1) {
                        var file_name = res.data.url.split('.');
                        var file_suffix = file_name[file_name.length - 1];

                        if (file_suffix == 'png' ||
                            file_suffix == 'jpg' ||
                            file_suffix == 'gif' ||
                            file_suffix == 'jpeg') {

                            KeFu.buildChatImg(res.data.url,'聊天图片','record');
                            KeFu.sendMessage(res.data.url,1);
                        } else {
                            var file_name = res.data.url.split('.');
                            var file_suffix = file_name[file_name.length - 1];

                            KeFu.buildChatA(res.data.url,file_suffix,'record');
                            KeFu.sendMessage(res.data.url,2);
                        }
                    } else {
                        layer.msg(res.msg);
                    }
                },
                error: function (e) {
                    layer.msg('文件上传失败,请重试！');
                },
                complete: function () {
                    $('#chatfile').val('');
                }
            })
        });

        // 加载更多聊天记录
        document.addEventListener('scroll', function (event) {

            if ($(event.target).scrollTop() == 0 && KeFu.chat_record_page != 'done') {
                if (event.target.className == 'chat_wrapper') {

                    if (!KeFu.session_id) {
                        return ;
                    }

                    // 加载历史聊天记录
                    var load_message = {
                        c: 'Message',
                        a: 'chatRecord',
                        data: {
                            session_id: KeFu.session_id,
                            page: KeFu.chat_record_page
                        }
                    };

                    KeFu.ws_send(load_message);
                }
            }

        }, true);

        // 消息输入框换行和输入监听
        $('#kefu_message').on('input propertychange', function () {
            KeFu.kefu_message_change();
        });

        // 发送消息监听
        $('.send_btn').on('click', function () {
            var message = $('#kefu_message').val();
            KeFu.sendMessage(message,0);
        })

        // 提交留言
        $(document).on('click', '#kefu_leave_message form button', function (event) {

            var form_data = {};
            var t = $('#kefu_leave_message form').serializeArray();
            $.each(t, function() {
                form_data[this.name] = this.value;
            });

            if (!form_data['contact']) {
                layer.msg('联系方式不能为空哦~');
                return false;
            }

            var leave_message = {
                c: 'Message',
                a: 'leaveMessage',
                data: form_data
            };
            KeFu.ws_send(leave_message);
            return false;
        });

    },
    buildPrompt: function (data, page) {
        if (page == 1) {
            $('.chat_wrapper').append('<div class="status"><span>' + data + '</span></div>');
        } else {
            $('.chat_wrapper').prepend('<div class="status"><span>' + data + '</span></div>');
        }
    },
    buildChatImg: function (filename, facename, class_name = 'emoji') {

        var protocol = window.location.protocol + '//';

        if (class_name == 'emoji') {
            return '<img class="emoji" data-title="' + facename + '" src="' + KeFu.config.__CDN__ + '/assets/addons/kefu/img/' + filename + '" />';
        } else {
            return '<img class="' + class_name + '" title="' + facename + '" src="' + filename + '" />';
        }
    },
    buildChatA: function (filepath, file_suffix, class_name) {
        if (class_name == 'record') {
            return '<a target="_blank" class="' + class_name + '" href="' + filepath + '">点击下载：' + file_suffix + ' 文件</a>';
        }
        else
        {
            return '<a target="_blank" class="' + class_name + '" href="' + filepath + '">点击下载：' + file_suffix + ' 文件</a>';
        }
    },
    buildRecord: function (data, page) {

        var message = '';

        if (data.message_type == 1) {
            message = KeFu.buildChatImg(data.message,'聊天图片','record');
        } else if (data.message_type == 2) {
            var file_name = data.message.split('.');
            var file_suffix = file_name[file_name.length - 1];
            message = KeFu.buildChatA(data.message,file_suffix,'record');
        } else if (data.message_type == 3) {
            KeFu.buildPrompt(data.message,page);
            return ;
        } else {
            message = data.message;
        }

        if (page == 1) {
            $('.chat_wrapper').append('<div class="bubble ' + data.sender + '">' + message + '</div>');
        } else {
            $('.chat_wrapper').prepend('<div class="bubble ' + data.sender + '">' + message + '</div>');
        }
    },
    sendMessage: function (message,message_type) {

        if (message == ''){
            layer.msg('请输入消息内容~');
            return ;
        }

        // 检查 websocket 是否连接
        if (!KeFu.ws.SocketTask || KeFu.ws.SocketTask.readyState != 1) {
            layer.msg('网络链接异常，请刷新重试~');
            return ;
        }

        if (!KeFu.session_id) {
            layer.msg('请选择一个会话~');
            return ;
        }

        if (message_type == 0) {

            // 处理表情
            var reg = /\[(.+?)\]/g;  // [] 中括号
            var reg_match = message.match(reg);

            if (reg_match){
                for (var i in KeFu.emoji_config){
                    if (reg_match.includes(KeFu.emoji_config[i].title)){
                        message = message.replace(KeFu.emoji_config[i].title, KeFu.buildChatImg(KeFu.emoji_config[i].src, KeFu.emoji_config[i].title, 'emoji'))
                    }
                }
            }

            $('#kefu_message').val('');
            KeFu.kefu_message_change();
        }

        var load_message = {
            c: 'Message',
            a: 'sendMessage',
            data: {
                message: message,
                message_type: message_type,
                session_id: KeFu.session_id,
                modulename: KeFu.config.modulename
            }
        };

        KeFu.ws_send(load_message);

        var data = {
            sender: 'me',
            message: message,
            message_type: message_type
        }

        KeFu.buildRecord(data, 1);

        setTimeout(function () {
            $('.chat_wrapper').scrollTop($('.chat_wrapper')[0].scrollHeight);
        }, 150)
    },
    onUploadResponse: function(response){
        try {
            var ret = typeof response === 'object' ? response : JSON.parse(response);
            if (!ret.hasOwnProperty('code')) {
                $.extend(ret, {code: -2, msg: response, data: null});
            }
        } catch (e) {
            var ret = {code: -1, msg: e.message, data: null};
        }
        return ret;
    },
    new_message_prompt: function () {

        if (KeFu.audio.buffer) {
            KeFu.playSound();
        } else {
            let url = KeFu.buildUrl(KeFu.url, 'index', 'load_message_prompt');

            KeFu.loadAudioFile(url);
        }
    },
    getCookie: function (cname) {
        var name = KeFu.cookie_prefix + cname + "=";
        var decodedCookie = decodeURIComponent(document.cookie);
        var ca = decodedCookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) == 0) {
                return c.substring(name.length, c.length);
            }
        }
        return '';
    },
    setCookie: function (cname, cvalue, exdays) {
        var d = new Date();
        d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
        var expires = "expires=" + d.toUTCString();
        document.cookie = KeFu.cookie_prefix + cname + "=" + cvalue + ";" + expires + ";path=/";
    },
    playSound: function () {
        KeFu.audio.source = KeFu.audio.context.createBufferSource();
        KeFu.audio.source.buffer = KeFu.audio.buffer;
        KeFu.audio.source.loop = false;
        KeFu.audio.source.connect(KeFu.audio.context.destination);
        KeFu.audio.source.start(0); //立即播放
    },
    loadAudioFile: function (url) {
        var xhr = new XMLHttpRequest(); //通过XHR下载音频文件
        xhr.open('GET', url, true);
        xhr.responseType = 'arraybuffer';
        xhr.onload = function (e) { //下载完成

            KeFu.audio.context.decodeAudioData(this.response,
                function (buffer) { //解码成功时的回调函数
                    KeFu.audio.buffer = buffer;
                    KeFu.playSound();
                },
                function (e) { //解码出错时的回调函数
                    console.log('音频解码失败', e);
                });
        };
        xhr.send();
    },
    toggle_send_btn: function (toggle) {
        if (toggle == 'show'){
            $('.widget_textarea').css('flex', '7');
            $('.write_right').css('flex', '3');
            $('.send_btn').show(200);
            $('.select_file').hide();
            KeFu.show_send = true;
        } else {
            $('.widget_textarea').css('flex', '8');
            $('.write_right').css('flex', '2');
            $('.select_file').show(200);
            $('.send_btn').hide();
            KeFu.show_send = false;
        }
    },
    change_csr_status: function (status) {

        var text_color = '#777';

        status = parseInt(status);

        switch (status) {
            case 0:
                status = '离线';
                break;
            case 1:
                status = '繁忙';
                text_color = '#8a6d3b';
                break;
            case 2:
                status = '离开';
                text_color = '#a94442';
                break;
            case 3:
                status = '在线';
                text_color = '#3c763d';
                break;

            default:
                status = '未知';
                break;
        }

        $('#csr_status').html(' • ' + status);
        $('#csr_status').css('color',text_color);
    },
    getQueryVariable: function (variable)
    {
        var query = window.location.search.substring(1);
        var vars = query.split("&");
        for (var i=0;i<vars.length;i++) {
            var pair = vars[i].split("=");
            if(pair[0] == variable){
                return pair[1];
            }
        }
        return(false);
    }
};