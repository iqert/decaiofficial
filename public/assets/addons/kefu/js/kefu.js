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
// 音频播放初始化
window.AudioContext = window.AudioContext || window.webkitAudioContext || window.mozAudioContext || window.msAudioContext;

var KeFu = {
	ws: {
        SocketTask: null,
        Timer: null,
        ErrorMsg: [],
        MaxRetryCount: 3,// 最大重连次数
        CurrentRetryCount: 0,
        url: null
    },
    audio: {
        context: new window.AudioContext(),
        source: null,
        buffer: null
    },
    cookie_prefix: "",// 同config.php里边的cookie前缀设置，未修改过请忽略
    config: null,
    url: null,
    fixed_csr: 0,
    window_is_show: false,
    fast_move: false,
    csr: "",// 当前用户的客服代表ID
    session_id: 0,
    last_sender: null,
    token_list: [],
    fast_reply: [],// 快捷回复富文本支持
    session_user: "",
    select_session_user: "",// 右键菜单选中的 session_user
    slider: null,// 幻灯片对象
    clickkefu_button: false,// 防止浮动按钮被拖动时触发点击事件
    allowed_close_window: true,// 是否允许ecs关闭窗口（当预览图片时，不允许关闭）
    group_show: {
        'dialogue': false,
        'invitation': false,
        'recently': false
    }, // 分组是否展开,未展开时添加红点
    initialize: function (url = document.domain, modulename = 'index', initSuccess = null, fixed_csr = 0) {
        
    	KeFu.url = url;
    	KeFu.fixed_csr = fixed_csr;
    	var initialize_url = KeFu.buildUrl(url, modulename, 'initialize');

    	$.ajax({
            url: initialize_url,
            success: function (data) {

                if (data.code == 401) {
                    console.warn(data.msg);
                    return;
                } else if (data.code != 1) {
                    layer.msg(data.msg);
                    return;
                }

                KeFu.config = data.data.config;
                KeFu.config.user_id = data.data.user_info.user_id;
                KeFu.token_list = data.data.token_list;
                KeFu.fast_reply = data.data.fast_reply;
                KeFu.bulidChat(data.data.window_html);

                if (parseInt(KeFu.config.auto_invitation_switch) > 0 && KeFu.config.modulename != 'admin') {

                    let only_first_invitation = parseInt(KeFu.config.only_first_invitation);

                    // cookie:kefu_auto_invitation 在窗口成功弹出后创建
                    if ((only_first_invitation == 1 && !KeFu.getCookie('kefu_auto_invitation')) || only_first_invitation == 0) {

                        setTimeout(function () {
                            KeFu.bulidInviteBox();
                        },(KeFu.config.auto_invitation_timing * 1000));
                    }
                    
                }

                // 构建ws的url
                KeFu.ws.url = KeFu.buildUrl(url, modulename, 'ws', KeFu.config.websocket_port);
                KeFu.config.upload.uploadurl = KeFu.buildUrl(url, modulename, "upload");

                if (modulename == 'admin') {
                    // 立即链接 Websocket
                    KeFu.ConnectSocket();
                } else {
                    // 若用户 10 秒后任在此页面，链接Socket
                    setTimeout(function () {
                        if (!KeFu.ws.SocketTask || KeFu.ws.SocketTask.readyState == 3 || KeFu.ws.SocketTask.readyState == 2) {
                            KeFu.ConnectSocket();
                        }
                    }, 10000);
                }

                if (typeof initSuccess == 'function') {
                    initSuccess();
                }

                // 读取按钮位置
                var kefu_button_coordinate = localStorage.getItem("kefu_button_coordinate");
                if (kefu_button_coordinate){
                    kefu_button_coordinate = kefu_button_coordinate.split(',');
                    if (kefu_button_coordinate[0] && kefu_button_coordinate[1]){
                        $("#kefu_button").css({"top": Number(kefu_button_coordinate[0]), "left": Number(kefu_button_coordinate[1])});
                    }
                }

                // 新消息
                if (data.data.new_msg) {
                    KeFu.toggle_popover('show',data.data.new_msg);
                    KeFu.new_message_prompt('#kefu_button');
                } else if (!KeFu.getCookie('kefu_new_user')) {
                    KeFu.toggle_popover('show',KeFu.config.new_user_tip);
                    KeFu.new_message_prompt('#kefu_button');
                }
            }
        });

        KeFu.eventReg();
    },
    ConnectSocket: function () {

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
                KeFu.toggle_popover('show','WebSocket 链接已断开');
                if (KeFu.ws.MaxRetryCount) {
                    KeFu.ws.Timer = setInterval(KeFu.retry_webSocket, 3000);//每3秒重新连接一次
                }
            };

            ws.onerror = function (e) {
                // 错误
                KeFu.ws.ws_error = true;
                console.error('websocket 错误:', e);
                $('#kefu_error').html('WebSocket 发生错误');
                KeFu.toggle_popover('show','WebSocket 发生错误');
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

        if (KeFu.config.modulename == 'admin') {
            $('#kefu_csr_status button .kefu_status').html(status);
            $('#kefu_csr_status button').css('color',text_color);
        } else {
            $('.modal-title #csr_status').html(' • ' + status);
            $('.modal-title #csr_status').css('color',text_color);
        }
    },
    // 会话分组的红点管理
    session_group_red_dot: function (group,red_dot) {
        var element_id = '#heading_' + group;
        if (red_dot) {
            $('.KeFu .modal-body .kefu-left ' + element_id + ' .panel-title .red_dot').show(200);
        } else {
            $('.KeFu .modal-body .kefu-left ' + element_id + ' .panel-title .red_dot').hide();
        }
    },
    domsg: function (msg) {
        if (msg.msgtype == 'initialize') {

            $('#kefu_error').html('');

            if (KeFu.ws.ws_error) {
                KeFu.ws.CurrentRetryCount = 0;
                KeFu.toggle_popover('hide');
                KeFu.ws.ws_error = false;
            }

            if (msg.data.modulename == 'admin') {

                KeFu.change_csr_status(msg.data.user_info.status_text);
                $('#modal-title').html(msg.data.chat_name + '-' + msg.data.user_info.nickname);

                // 渲染聊天列表
                if (msg.data.session.dialogue && msg.data.session.dialogue.length) {

                    for (let i in msg.data.session.dialogue) {
                        KeFu.buildSession(msg.data.session.dialogue[i],'dialogue');
                    }

                    KeFu.session_id = msg.data.session.dialogue[msg.data.session.dialogue.length - 1].id;
                    $('#heading_dialogue a').click();
                }

                if (msg.data.session.invitation && msg.data.session.invitation.length) {

                    for (let i in msg.data.session.invitation) {
                        KeFu.buildSession(msg.data.session.invitation[i],'invitation');
                    }

                }

                if (msg.data.session.recently && msg.data.session.recently.length) {

                    for (let i in msg.data.session.recently) {
                        KeFu.buildSession(msg.data.session.recently[i],'recently');
                    }

                    if (!KeFu.session_id) {
                        KeFu.session_id = msg.data.session.recently[msg.data.session.recently.length - 1].id;
                        $('#heading_recently a').click();
                    }
                }

                KeFu.changeSession(KeFu.session_id);
            } else {
                if (msg.data.new_msg) {
                    KeFu.toggle_popover('show',msg.data.new_msg);
                    KeFu.new_message_prompt('#kefu_button');
                }
            }
            
        } else if (msg.msgtype == 'user_card') {
            
            if (msg.data) {
                $('#card-user').val(msg.data.nickname + ' ID:' + msg.data.id + (msg.data.user_id ? ' 会员ID:' + msg.data.user_id : ''));
                $('#card-nickname').val(msg.data.nickname_origin);
                $('#card-referrer').val(msg.data.referrer);
                $('#card-contact').val(msg.data.contact);
                $('#card-note').val(msg.data.note);
            }
            
        } else if (msg.msgtype == 'search_user') {

            // 渲染搜索结果列表
            var element_id = '#session_list_search';
            KeFu.search_primary = -1;
            KeFu.search_select_id = '';
            $(element_id).html('');
            $(element_id).show(200);

            if (msg.data.length) {

                $.each(msg.data, function (index, item) {

                    $(element_id).append(
                        '<li class="person" data-session="' + item.id + '" data-session_user="' + item.session_user + '" data-group="search" data-nickname="'+item.nickname+'">' +
                            '<img class="person_avatar" src="' + item.avatar + '" alt="" />' +
                            '<div class="session_info_item">' +
                                '<span class="name">' + item.nickname + '</span>' +
                                '<span class="time">' + item.last_time + '</span>' +
                            '</div>\
                            <div class="session_info_item">' +
                                '<span class="preview">' + item.last_message + '</span>' +
                            '</div>' +
                        '</li>'
                    );
                });
            } else {
                $(element_id).append('<div class="none_session">找不到用户~</div>');
            }

        } else if (msg.msgtype == 'user_initialize') {

            // 用户客服分配结束
            if (msg.code == 1) {

                if (msg.data.session.user_tourists) {
                    KeFu.sendMessage = function () {
                        layer.msg('请登录后发送消息~');
                    }
                    $('#send_tis').html('为保护您的隐私请 <a href="' + msg.data.session.user_login_url + '">登录</a> 后发送消息');
                }

                KeFu.csr = msg.data.session.csr;
                KeFu.session_id = msg.data.session.id;
                $('#modal-title').html('客服 ' + msg.data.session.nickname + ' 为您服务');
                KeFu.toggle_window_view('kefu_scroll');
                KeFu.change_csr_status(msg.data.session.csr_status);
            } else if (msg.code == 302) {

                if (!KeFu.csr) {

                    // 打开留言板
                    KeFu.csr = 'none';
                    $('#modal-title').html('当前无客服在线哦~');
                    KeFu.toggle_window_view('kefu_leave_message');
                } else {
                    $('#send_tis').html('当前客服暂时离开,您可以直接在此发送离线消息');
                }
                
            }
            
        } else if (msg.msgtype == 'action_session') {
            if (msg.data.action == 'received_invitation' && !KeFu.window_is_show) {

                // 显示邀请框
                var kefu_invite_box = $('.kefu_invite_box');
                if (kefu_invite_box.length) {
                    kefu_invite_box.show(200);
                } else {
                    KeFu.bulidInviteBox();
                }
            } else if (msg.data.action == 'send_success' && KeFu.session_user == msg.data.session_user) {
                
                // 重新加载轨迹
                var session = $("#session_panel [data-session_user='" + msg.data.session_user + "']");
                KeFu.changeSession(session.data('session'), 'trajectory');
            } else if (msg.data.action == 'transfer') {

                // 显示转接操作面板
                var tpl = '\
                <div class="kefu_transfer_session" data-transfer_user="' + msg.data.session_user + '">\
                    <div class="form-group">\
                        <label>转接给</label>\
                        <select id="transfer_session_select" class="form-control">\
                        </select>\
                        <div class="transfer_session_buttons">\
                            <button type="button" id="transfer_session_cancel" class="btn btn-default btn-sm">取消</button>\
                            <button type="button" id="transfer_session_ok" class="btn btn-success btn-sm">确定</button>\
                        </div>\
                    </div>\
                </div>';

                var session = $("#session_panel [data-session_user='" + msg.data.session_user + "']");
                session.after(tpl);

                $.each(msg.data.csr_list,function (index, item) {
                    let html_text = item.nickname + '(ID:' + item.admin_id + ')';
                    $("#transfer_session_select").append("<option value='" + item.admin_id + "'>" + html_text + "</option>");
                });

                $(document).on('click', '#transfer_session_cancel', function (e) {
                    $('.kefu_transfer_session').remove();
                });

                $(document).on('click', '#transfer_session_ok', function (e) {
                    
                    var transfer_user = $('.kefu_transfer_session').data('transfer_user');
                    var csr = $('#transfer_session_select').val();

                    if (transfer_user && csr) {
                        var action_session = {
                            c: 'Message',
                            a: 'actionSession',
                            data: {
                                action: 'transfer_done',
                                session_user: transfer_user,
                                csr: csr
                            }
                        };
                        KeFu.ws_send(action_session);
                    }

                    $('.kefu_transfer_session').remove();
                });

            } else if (msg.data.action == 'transfer_done' && msg.data.res){

                // 转移成功
                layer.msg('会话已转移给客服 ' + msg.data.res);
                $("#session_panel [data-session_user='" + msg.data.session_user + "']").remove();
                KeFu.group_session_is_none('dialogue');
                KeFu.group_session_is_none('recently');
            } else if (msg.data.action == 'edit_nickname') {
                var session = $("#session_panel [data-session_user='" + msg.data.session_user + "']");
                session.children(".session_info_item").children(".name").eq(0).html(msg.data.list_nickname);

                if (KeFu.session_user == msg.data.session_user) {
                    $('#session_user_name').html(msg.data.new_nickname);
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

            if (KeFu.config.modulename == 'admin') {
                $("#session_panel [data-transfer_user='" + msg.user_id + "']").remove();
                KeFu.edit_online_status(msg.user_id,false);
            } else {
                if (msg.user_id == KeFu.csr) {
                    $('#send_tis').html('当前客服暂时离开,您可以直接在此发送离线消息');
                    KeFu.change_csr_status(0);
                }
            }
            
        } else if (msg.msgtype == 'online') {

            var element = $("#session_panel [data-session_user='" + msg.user_id + "']");

            if (msg.tourists != 'not' && !element.length) {
                // 添加到邀请中
                KeFu.buildSession(msg.tourists,'invitation');

                if (!KeFu.group_show.invitation) {
                    KeFu.session_group_red_dot('invitation',true);
                }
            }

            if(KeFu.window_is_show && KeFu.session_user == msg.user_id) {
                $('#session_user_name').html(msg.user_name);
            }

            // 修改用户在线状态
            KeFu.edit_online_status(msg.user_id,true);

            // 来自 admin 的用户上线了
            if (msg.modulename == 'admin') {

                if (msg.user_id == KeFu.csr) {
                    let send_tis_key = parseInt(KeFu.config.send_message_key) == 1 ? 'Enter' : 'Ctrl+Enter';
                    $('.KeFu .modal-body #send_tis').html('按下' + send_tis_key + '发送消息');
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
        } else if (msg.msgtype == 'trajectory') {

            var trajectory = msg.data.trajectory;
            KeFu.chat_record_page = msg.data.next_page;

            if (msg.data.page == 1) {
                $('#kefu_trajectory_log').html('');

                // 修改会话的最后消息
                let session = $("#session_panel [data-session_user='" + msg.data.user_info.session_user + "']");
                session.children(".session_info_item").children(".time").eq(0).html(msg.data.last_message.last_time);
                session.children(".session_info_item").find(".last_message").eq(0).html(msg.data.last_message.last_message);
            }

            $('#session_user_name').html(msg.data.user_info.nickname);

            // 构建轨迹
            var x = 'left';
            for(let i in trajectory){
                if (msg.data.page == 1) {
                    KeFu.build_trajectory(i, msg.data.page,'date');
                }

                for (let y in trajectory[i]) {
                    KeFu.build_trajectory(trajectory[i][y], msg.data.page,'log',x);
                    x = (x == 'left' ) ? 'right':'left';
                }

                if (msg.data.page != 1) {
                    KeFu.build_trajectory(i, msg.data.page,'date');
                }
            }

            KeFu.kefu_blacklist(3, msg.data.user_info.session_user, msg.data.user_info.blacklist);

            if (msg.data.page == 1) {
                if (KeFu.window_is_show) {
                    $('#kefu_trajectory').scrollTop($('#kefu_trajectory')[0].scrollHeight);
                } else {
                    KeFu.window_show_event = function () {
                        $('#kefu_trajectory').scrollTop($('#kefu_trajectory')[0].scrollHeight);
                    }
                }
            }

        } else if (msg.msgtype == 'chat_record') {

            if (msg.data.page == 1) {
                $('#kefu_scroll').html('');
            }

            if (KeFu.config.modulename == 'admin') {
                $('#session_user_name').html(msg.data.session_info.nickname);
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

            KeFu.kefu_blacklist(3, msg.data.session_info.session_user, msg.data.session_info.blacklist);
            
            if (msg.data.page == 1) {
                if (KeFu.window_is_show) {
                    setTimeout(function () {
                        $('#kefu_scroll').scrollTop($('#kefu_scroll')[0].scrollHeight);
                    },100)
                } else {
                    KeFu.window_show_event = function () {
                        $('#kefu_scroll').scrollTop($('#kefu_scroll')[0].scrollHeight);
                    }
                }
            }

            // 消息输入框聚焦
            setTimeout(function () {
                $('#kefu_message').focus();
            },500)
            
        } else if (msg.msgtype == 'csr_change_status') {
            
            if (KeFu.config.modulename == 'admin' && KeFu.config.user_id == msg.data.csr) {
                KeFu.change_csr_status(msg.data.csr_status);
            } else if (KeFu.csr == msg.data.csr) {
                KeFu.change_csr_status(msg.data.csr_status);
            }
        } else if (msg.msgtype == 'blacklist') {

            if (msg.data.action == 'del') {
                KeFu.kefu_blacklist(2,msg.data.session_user);
            } else if (msg.data.action == 'add') {
                KeFu.kefu_blacklist(1,msg.data.session_user);
            }
            
        } else if (msg.msgtype == 'transfer_done') {

            KeFu.csr = msg.data.csr;
            $('#modal-title').html('客服 ' + msg.data.nickname + ' 为您服务');

        } else if (msg.msgtype == 'reload_record') {
            // 重载聊天记录
            if (KeFu.session_id == msg.data.session_id) {
                var load_record = {
                    c: 'Message',
                    a: 'chatRecord',
                    data: {
                        session_id: msg.data.session_id,
                        page: 1
                    }
                };
                KeFu.ws_send(load_record)
            }
        } else if (msg.msgtype == 'new_message') {

            var message_content = msg.data.nickname + ':' + msg.data.last_message;

            if (KeFu.window_is_show) {
                // 窗口打开状态
                KeFu.new_message_prompt('#KeFuModal');
            } else {
                KeFu.toggle_popover('show',message_content);
                KeFu.new_message_prompt('#kefu_button');
            }

            if ($('#kefu_scroll').children('.status').children('span').eq(0).html() == '还没有消息') {
                $('#kefu_scroll').children('.status').children('span').eq(0).html('刚刚');
            }

            if (KeFu.config.modulename == 'admin') {

                // 为会话中添加一个红点
                if (!KeFu.group_show.dialogue) {
                    KeFu.session_group_red_dot('dialogue',true);
                }

                // 检查是否有该会话
                let session = $("#session_panel [data-session_user='" + msg.data.session_user + "']");

                // 记录最后发信人
                KeFu.last_sender = msg.data.session_id;

                if (session.length == 0) {
                    KeFu.buildSession(msg.data,'dialogue');
                    KeFu.group_session_is_none('dialogue');
                } else {

                    // 会话将被移动->取消会话转移
                    $("#session_panel [data-transfer_user='" + msg.data.session_user + "']").remove();

                    // 确保 data 正确
                    session.data('session',msg.data.session_id);
                    session.attr("data-session",msg.data.session_id);
                    session.data('group','dialogue');
                    session.attr("data-group",'dialogue');

                    // 修改该会话的最后消息
                    session.children(".session_info_item").children(".time").eq(0).html(msg.data.last_time);
                    session.children(".session_info_item").find(".last_message").eq(0).html(msg.data.last_message);

                    // 将会话移动到“会话中”的第一位
                    let first_session = $('#session_list_dialogue li');
                    
                    if (first_session.length) {
                        // 会话中已有对话
                        first_session.eq(0).before(session);
                    } else {
                        // 会话中没有对话
                        $('#session_list_dialogue').prepend(session);                        
                    }

                    KeFu.group_session_is_none('dialogue');
                    KeFu.group_session_is_none('recently');
                    KeFu.group_session_is_none('invitation');

                    if (msg.data.session_user == KeFu.session_user && KeFu.window_is_show) {

                        if (session.data('group') != 'dialogue') {

                            // 会话被移动到“会话中”->取消该会话的选择状态
                            session.removeClass("active");
                        } else {

                            if ($('#kefu_trajectory').css('display') != 'block') {
                                // 必要时，去除该会话的红点
                                KeFu.buildRecord(msg.data, 1);
                                $('#kefu_scroll').scrollTop($('#kefu_scroll')[0].scrollHeight);

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
                    }

                    if (msg.data.unread_msg_count > 0) {
                        session.children(".session_info_item").children(".unread_msg_count").eq(0).html(msg.data.unread_msg_count).show(200);
                    } else {
                        session.children(".session_info_item").children(".unread_msg_count").eq(0).hide(200);
                    }
                }

            } else {

                KeFu.buildRecord(msg.data, 1);

                if (msg.data.session_id == KeFu.session_id && KeFu.window_is_show) {
                    
                    $('#kefu_scroll').scrollTop($('#kefu_scroll')[0].scrollHeight);

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
    toggle_popover: function (toggle,content) {

        if (!KeFu.window_is_show && content && toggle == 'show') {
            $('#kefu_button').attr("data-content", content);
            $('#kefu_button').popover('show');
        } else {
            $('#kefu_button').popover('hide');
        }
    },
    // 切换显示的视图-聊天视图、轨迹视图、留言视图、用户名片视图
    toggle_window_view: function (show_view_id) {

        // 隐藏所有视图
        $('.kefu_window_view').hide();
        // 显示指定的视图
        $('#' + show_view_id).show(200);

        // 聊天
        if (show_view_id == 'kefu_scroll') {
            
            $('.modal-body .write').show();
            if (KeFu.config.modulename == 'admin') {
                $('.KeFu .modal-body .kefu-right .chat').height('calc(100% - 158px)');
            }

        } else {

            if (KeFu.config.modulename == 'admin') {
                $('#kefu_emoji').hide();// 表情面板
                $('#kefu_fast_reply_list').fadeOut();// 快速回复面板
                $('.KeFu .modal-body .kefu-right .chat').height('calc(100% - 58px)');
            }
            $('.modal-body .write').hide();
        }

        // 轨迹
        if (show_view_id == 'kefu_trajectory') {
            $('#kefu_view_trajectory').hide();
        } else {
            $('#kefu_view_trajectory').show(200);
        }

        // 名片
        if (show_view_id == 'kefu_user_card') {
            $('#kefu_view_user_card').hide();
        } else {
            $('#kefu_view_user_card').show(200);
        }
        
    },
    toggle_window: function (toggle) {

        if (toggle == 'show') {

            if (!KeFu.isPc() && KeFu.config.modulename != 'admin'){
                // 跳转到手机单页
                window.location.href = KeFu.buildUrl(KeFu.url, KeFu.config.modulename, 'mobile');
                return ;
            }

            // 隐藏悬浮按钮
            KeFu.toggle_popover('hide');
            $('#kefu_button').hide();

            // 检查 websocket 是否连接
            if (!KeFu.ws.SocketTask || KeFu.ws.SocketTask.readyState != 1) {
                KeFu.ConnectSocket();
            }

            // 隐藏邀请框
            $('.kefu_invite_box').hide(200);

            $('#KeFuModal').modal({
                // keyboard: (KeFu.config.ecs_exit == '1') ? true:false,
                keyboard:false,
                show: true
            });

            if (KeFu.config.modulename != 'admin') {

                // 分配/获取客服->获取聊天记录
                var user_initialize = {
                    c: 'Message',
                    a: 'userInitialize',
                    data: {
                        fixed_csr: KeFu.fixed_csr
                    }
                };
                KeFu.ws_send(user_initialize);

                // 绑定窗口中的动态数据
                if (KeFu.config.slider_images.length > 0) {

                    var SlideLi = '<div id="kefu_chat_slide" class="carousel slide" data-ride="carousel">' +
                    KeFu.buildSlideLi(KeFu.config.slider_images.length);

                    var slider_inner = '<div class="carousel-inner">';
                    for (var i = 0; i < KeFu.config.slider_images.length; i++) {
                        slider_inner += KeFu.buildSlide(i,KeFu.config.slider_images[i]);
                    }
                    slider_inner += '</div></div>';

                    $('#kefu_chat_slide_f').html(SlideLi + slider_inner);
                    $('#kefu_chat_slide_f').show();

                    $('#kefu_chat_slide').carousel({
                        interval: 3000
                    })
                }

                if (KeFu.config.chat_introduces) {
                    $('.KeFu .modal-body .kefu-right .chat_introduces').html(KeFu.config.chat_introduces);
                } else {
                    $('.KeFu .modal-body .kefu-right .chat_introduces').hide();
                }

                if (KeFu.config.announcement) {
                    $('.KeFu .modal-body .kefu-left .announcement #announcement').html(KeFu.config.announcement);
                } else {
                    $('.KeFu .modal-body .kefu-left .announcement').hide();
                    $('.KeFu .modal-body .kefu-left .chat').height('calc(100% - 110px)');
                }
            } else {

                // 找到当前会话，去掉红点标记(直接重载当前会话)
                if (KeFu.session_id) {
                    // 聊天记录的请求
                    var load_message = {
                        c: 'Message',
                        a: 'chatRecord',
                        data: {
                            session_id: KeFu.session_id,
                            page: 1
                        }
                    };

                    KeFu.ws_send(load_message);
                    // 清理红点
                    var session = $("#session_panel [data-session='" + KeFu.session_id + "']");
                    session.children(".session_info_item").children(".unread_msg_count").eq(0).hide(200);
                }

            }

            let send_tis_key = parseInt(KeFu.config.send_message_key) == 1 ? 'Enter' : 'Ctrl+Enter';

            $('.KeFu .modal-body #send_tis').html('按下' + send_tis_key + '发送消息');

            // 初始化表情
            /*if (!$('#kefu_emoji').html()) {
                for (var i = 1; i <= 36; i++) {
                    $('#kefu_emoji').append(KeFu.buildChatImg(i + '.png', '', 'emoji'));
                }
            }*/
            
        } else {
            $('#KeFuModal').modal('hide');
        }
    },
    bulidInviteBox: function () {
        if (!KeFu.window_is_show && !KeFu.csr) {
            var el = '<div class="kefu_invite_box" style="background: url('+ KeFu.config.invite_box_img +') no-repeat;background-size: 100% 100%;">\
                <div class="invite_box_close">&times;</div>\
                <div class="bottom_button">\
                <div class="later">稍后再说</div>\
                <div class="consulting">现在咨询</div>\
                </div>\
            </div>';
            $("body").append(el);
        }

        if (parseInt(KeFu.config.only_first_invitation) == 1) {
            KeFu.setCookie('kefu_auto_invitation', true, 365);
        }

        $(document).on('click','.invite_box_close,.kefu_invite_box .later',function () {
            $('.kefu_invite_box').hide(200);
        });
    },
    bulidChat: function (window_html) {

        $("body").append(window_html);

        // 初始化 popover
        $('#kefu_button').popover({
            trigger:'manual',
            template:'<div class="popover kefu_popover" role="tooltip"><div class="arrow"></div><h3 class="popover-title"></h3><div class="popover-content"></div></div>',
            container:'.KeFu'
        });

        if (KeFu.browserType() == "FF") {
            $('#kefu_message').attr('contenteditable',true);
        }

        // 初始化图片粘贴/拖拽上传
        $.getScript(KeFu.buildUrl(KeFu.url, KeFu.config.modulename, 'pasteupload.js'), function () {

            var upload_msg_index = 0;

            $.fn.pasteText = function (text) {
                // $('#kefu_message').append(text);
                upload_msg_index = layer.msg('正在上传图片...', {'time':0});
            };

            $.fn.insertToTextArea = function (filename, url) {
                var options = $(this).data("pu-options") || $.fn.pasteUploadImage.defaults;

                var img_node = document.createElement('img');
                img_node.src = url;
                img_node.className = 'record';
                img_node.title = '聊天图片';
                KeFu.insertToMessage(img_node);

                layer.close(upload_msg_index)

                /*return $(this).html(function (index, val) {
                    return val.replace("{{" + filename + "(" + options.uploadingText + ")}}", KeFu.buildChatImg(url,'聊天图片','record'));
                });*/
            };

            //粘贴上传图片
            $.fn.pasteUploadImage.defaults = $.extend(true, $.fn.pasteUploadImage.defaults, {
                fileName: "file",
                appendMimetype: false,
                uploadingText: '上传中...',
                ajaxOptions: {
                    url: KeFu.config.upload.uploadurl,
                    beforeSend: function (jqXHR, settings) {
                        $.each(KeFu.config.upload.multipart, function (i, j) {
                            settings.data.append(i, j);
                        });
                        return true;
                    }
                },
                success: function (data, filename, file) {
                    var ret = KeFu.onUploadResponse(data);
                    $(this).insertToTextArea(filename, data.data.url);
                    // KeFu.po_last();
                    return false;
                },
                error: function (data, filename, file) {
                    console.log(data, filename, file);
                    layer.close(upload_msg_index)
                    layer.msg('图片上传失败，请重试~');
                }
            });

            $('#kefu_message').pasteUploadImage();
        });
    },
    buildPrompt: function (data, page) {
        if (page == 1) {
            $('#kefu_scroll').append('<div class="status"><span>' + data + '</span></div>');
        } else {
            $('#kefu_scroll').prepend('<div class="status"><span>' + data + '</span></div>');
        }
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
    changeSession:function (session_id, why = 'session') {

        if (KeFu.config.modulename != 'admin') {
            return ;
        }

        // 找到这个会话
        var element = $("#session_panel [data-session='" + session_id + "']");

        if (element.length == 0) {
            KeFu.session_id = 0;
            $('#session_user_name').html('无会话');
            return ;
        }

        // 取消所有选择
        $('.person').removeClass("active");
        element.addClass("active");

        // 隐藏表情和快捷回复面板
        $('#kefu_emoji').hide();
        $('#kefu_fast_reply_list').fadeOut();
        
        if ($('.KeFu .modal-body .kefu-left').css('display') != 'none') {
            $('#kefu_message').focus();
        }

        // 该对话所在分组是否展开
        let group = element.data('group');
        if (group == 'invitation' && !KeFu.group_show.invitation) {
            $('#heading_invitation a').click();
        } else if (group == 'dialogue' && !KeFu.group_show.dialogue) {
            $('#heading_dialogue a').click();
        } else if (group == 'recently' && !KeFu.group_show.recently) {
            $('#heading_recently a').click();
        }

        // 记录会话用户
        KeFu.session_user = element.data('session_user');
        KeFu.session_id = session_id;

        if (element.data('group') == 'invitation' || why == 'trajectory') {

            // 加载轨迹记录
            var load_log = {
                c: 'Message',
                a: 'trajectory',
                data: {
                    session_user: KeFu.session_user,
                    page: 1
                }
            };

            // 打开轨迹视图
            KeFu.toggle_window_view('kefu_trajectory');
        } else {

            // 打开聊天记录视图
            KeFu.toggle_window_view('kefu_scroll');

            // 清理红点
            element.children(".session_info_item").children(".unread_msg_count").eq(0).hide(200);

            // 聊天记录的请求
            var load_log = {
                c: 'Message',
                a: 'chatRecord',
                data: {
                    session_id: session_id,
                    page: 1
                }
            };
        }

        if (KeFu.window_is_show) {
            KeFu.ws_send(load_log);
        } else {
            KeFu.window_show_event = function () {
                KeFu.ws_send(load_log);
            }
        }
    },
    buildSession: function (item,group) {

        var element_id = '#session_list_' + group;

        if ($("#session_panel [data-session='" + item.id + "']").length) {
            // 去掉该会话再添加最新的
            $("#session_panel [data-session='" + item.id + "']").remove();
        }

        $(element_id).prepend(
            '<li class="person" data-session="' + item.id + '" data-session_user="' + item.session_user + '" data-group="' + group + '">' +
                '<img class="person_avatar' + (item.online ? '':' person_head_gray') + '" src="' + item.avatar + '" alt="" />' +
                '<div class="session_info_item">' +
                    '<span class="name">' + item.nickname + '</span>' +
                    '<span class="time">' + item.last_time + '</span>' +
                '</div>\
                <div class="session_info_item">' +
                '<span class="preview"><span style="' + (item.online ? 'display:none;':'') + '" class="session_user_status">[离线]</span><span class="last_message">' + item.last_message + '</span></span>' +
                (item.unread_msg_count ? '<span class="unread_msg_count">' + item.unread_msg_count + '</span>' : '<span class="unread_msg_count count_hide"></span>') +
                '</div>' +
            '</li>'
        );

        KeFu.group_session_is_none(group);
    },
    build_trajectory: function (data, page, build_way, pos = 'left') {

        if (build_way == 'log') {

            var log_type;
            var log_text = data.note;
            switch(data.log_type){
                case 0:
                    log_type = '访问';
                    log_text = data.note + '访问页面：' + data.url + '<br />来路：' + data.referrer;
                    break;
                case 1:
                    log_type = '被邀请';
                    break;
                case 2:
                    log_type = '开始对话';
                    break;
                case 3:
                    log_type = '拒绝对话';
                    break;
                case 4:
                    log_type = '客服分配';
                    break;
                case 5:
                    log_type = '用户离开';
                    log_text = '用户的workerman链接已断开';
                    break;
                case 6:
                    log_type = '留言';
                    log_text = '<a href="index.php?id=' + data.note + '">点击查看留言</a>'
                    break;
                case 7:
                    log_type = '其他';
                    break;
                case 8:
                    log_type = '转移会话';
                    break;
                default:
                    log_type = '未知';
                    break;
            }

            var element = '\
            <dd class="pos-' + pos + ' clearfix">\
                <div class="circ"></div>\
                <div class="time">' + data.createtime + '</div>\
                <div class="events">\
                    <div class="events-header">' + log_type + '</div>\
                        <div class="events-body">' + log_text + '</div>\
                    </div>\
                </div>\
            </dd>\
            ';
        } else {
            var element = '<dt> ' + data + ' </dt>';
        }

        if (page == 1) {
            $('#kefu_trajectory_log').append(element);
        } else {
            $('#kefu_trajectory_log').prepend(element);
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
            $('#kefu_scroll').append('<div class="bubble ' + data.sender + '">' + message + '</div>');
        } else {
            $('#kefu_scroll').prepend('<div class="bubble ' + data.sender + '">' + message + '</div>');
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
    buildChatImg: function (filename, facename, class_name = 'emoji') {

        var protocol = window.location.protocol + '//';

        if (class_name == 'emoji') {
            return '<img class="emoji" title="' + facename + '" src="' + KeFu.config.__CDN__ + '/assets/addons/kefu/img/emoji/' + filename + '" />';
        } else {
            return '<img class="' + class_name + '" title="' + facename + '" src="' + filename + '" />';
        }
    },
    buildSlideLi: function (count) {

        var slider_li_el = '<ol class="carousel-indicators" style="margin-bottom:0;bottom:10px !important;">';
        for (var i = 0; i < count; i++) {
            slider_li_el += '<li data-target="#kefu_chat_slide" data-slide-to=' + i + ' class="' + (i == 0 ? 'active':'') + '"></li>';
        }
        return slider_li_el += '</ol>';
    },
    buildSlide: function (index,img_scr) {
        return '\
            <div class="item ' + (index == 0 ? 'active':'') + '">\
                <img src="' + img_scr + '" alt="" />\
            </div>';
    },
    buildUrl: function (url, modulename, type = 'ws', wsport = 1818) {
        
        var protocol = window.location.protocol + '//';
        var port = window.location.port;
        port = port ? ':' + port:'';

        if (type == 'ws') {

            // 用户的身份通过 KeFu.config.kefu_token 来识别
            // 游客的身份通过 KeFu.config.kefu_tourists_token 来识别
            let token = '&token=' + (KeFu.token_list.kefu_token ? KeFu.token_list.kefu_token : '');
            let kefu_user = '&kefu_user=' + (KeFu.token_list.kefu_tourists_token ? KeFu.token_list.kefu_tourists_token : '');
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
            
            if (KeFu.config.__CDN__) {
                return KeFu.config.__CDN__ + '/assets/addons/kefu/audio/message_prompt.wav';
            }

            return protocol + url + port + '/addons/kefu/index/loadMessagePrompt?modulename=' +
                modulename;
        } else if (type == 'pasteupload.js') {

            return KeFu.config.__CDN__ + '/assets/addons/kefu/js/jquery.pasteupload.js';
        } else if (type == 'mobile') {
            return protocol + url + port + '/addons/kefu/index/mobile?fixed_csr=' + KeFu.fixed_csr;
        }


    },
    edit_online_status: function (user_id, status) {

        var element = $("#session_panel [data-session_user='" + user_id + "']");

        if (status) {
            $(element).children(".person_avatar").removeClass("person_head_gray");
            element.children(".session_info_item").find(".session_user_status").eq(0).hide();
        } else {
            $(element).children(".person_avatar").addClass("person_head_gray");
            element.children(".session_info_item").find(".session_user_status").eq(0).show(200);
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
    disableSelection: function (target) {

        if (typeof target.onselectstart != "undefined"){
            target.onselectstart = function(){
                return false
            };
        } else if (typeof target.style.MozUserSelect != "undefined"){
            target.style.MozUserSelect = "none";
        } else {
            target.οnmοusedοwn = function(){
                return false;
            }
            target.style.cursor = "default";
        }
        
    },
    sendMessage: function (message,message_type) {

        // 检查 websocket 是否连接
        if (!KeFu.ws.SocketTask || KeFu.ws.SocketTask.readyState != 1) {
            layer.msg('网络链接异常，请刷新重试~');
            return ;
        }

        if (typeof KeFu.session_id == 'string' && KeFu.session_id.indexOf('invitation') !== -1) {
            layer.msg('该访客还未接受会话请求哦~');
            return ;
        }

        if (!KeFu.session_id) {
            layer.msg('请选择一个会话~');
            return ;
        }

        var load_message = {
            c: 'Message',
            a: 'sendMessage',
            data: {
                message: message,
                message_type: message_type,
                session_id: KeFu.session_id,
                token: KeFu.token_list.kefu_token ? KeFu.token_list.kefu_token : '', // 发消息时检测用户登录态是否过期
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

        if (message_type == 1) {
            message = '[图片]';
        } else if (message_type == 2) {
            message = '[链接]';
        } else {
            message = message.replace(/<img(.*?)src=(.*?)>/g,"[图片]");
            $('#kefu_message').html('');
        }

        if (KeFu.config.modulename == 'admin') {

            // 修改该会话的最后消息
            let session = $("#session_panel [data-session='" + KeFu.session_id + "']");
            session.children(".session_info_item").children(".time").eq(0).html('刚刚');
            session.children(".session_info_item").find(".last_message").eq(0).html(message);
            
            // 移动会话到会话中的第一位
            let first_session = $('#session_list_dialogue li'); 
            if (first_session.length) {
                // 分组中已有对话
                first_session.eq(0).before(session);
            }  else {
                // 分组中没有对话
                $('#session_list_dialogue').prepend(session);
                
                // 清理没有会话的提示文字
                KeFu.group_session_is_none('dialogue');
            }

            // 展开分组
            if (!KeFu.group_show.dialogue) {
                $('#heading_dialogue a').click();
            }

            if ($('#kefu_scroll').children('.status').children('span').eq(0).html() == '还没有消息') {
                $('#kefu_scroll').children('.status').children('span').eq(0).html('刚刚');
            }
        }

        $('#kefu_scroll').scrollTop($('#kefu_scroll')[0].scrollHeight);
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
    new_message_prompt: function (event) {

        if (KeFu.config.is_shake) {

            $(event).addClass('kefu-shake-horizontal');
            setTimeout(function () {
                $(event).removeClass('kefu-shake-horizontal');
            }, 400);
        }

        if (KeFu.audio.buffer) {
            KeFu.playSound();
        } else {
            let url = KeFu.buildUrl(KeFu.url, 'index', 'load_message_prompt');

            KeFu.loadAudioFile(url);
        }
    },
    kefu_blacklist: function (code, session_user, blacklist = null) {
        // 修改用户黑名单状态
        // code:1=屏蔽成功,2=解除屏蔽成功,3=通过 blacklist 判断屏蔽状态
        
        if (code == 3) {

            if (blacklist) {
                $('#kefu_blacklist').html('取消拉黑');
            } else {
                $('#kefu_blacklist').html('拉黑名单');
            }
            return;
        }

        if (session_user == KeFu.session_user) {
            if (code == 1) {
                $('#kefu_blacklist').html('取消拉黑');
            } else if (code == 2) {
                $('#kefu_blacklist').html('拉黑名单');
            }
        }

    },
    // 处理分组的没有会话提示
    group_session_is_none: function (group) {
        var element_id = '#session_list_' + group;

        if ($(element_id + ' li').length) {
            $(element_id).children('.none_session').hide();
        } else {
            $(element_id).children('.none_session').show();
            KeFu.session_group_red_dot(group,false);
        }
    },
    // 移动光标到最未
    po_last:function () {
        var obj = $('#kefu_message')[0];
        if (window.getSelection) {//ie11 10 9 ff safari
            obj.focus(); //解决ff不获取焦点无法定位问题
            var range = window.getSelection();//创建range
            range.selectAllChildren(obj);//range 选择obj下所有子内容
            range.collapseToEnd();//光标移至最后
        } else if (document.selection) {//ie10 9 8 7 6 5
            var range = document.selection.createRange();//创建选择对象
            // var range = document.body.createTextRange();
            range.moveToElementText(obj);//range定位到obj
            range.collapse(false);//光标移至最后
            range.select();
        }
    },
    // 光标位置插入元素
    insertToMessage: function (obj){
        // 获取编辑框对象
        var Message = $('#kefu_message')[0]
        // 编辑框设置焦点
        Message.focus();
        // 获取选定对象
        var selection = getSelection()
        // 判断是否有最后光标对象存在

        // 获取包含当前节点的文档片段
        var range = selection.getRangeAt(0)

        // 将创建的文档片段插入到光标处
        range.insertNode(obj)
        // 光标对象的范围界定为新建的节点
        // range.selectNodeContents(obj)
        // 光标位置定位在节点的最大长度
        // range.setStart(obj, obj.length||1)
        // 使光标开始和光标结束重叠
        range.collapse(false)
        // 清除选定对象的所有光标对象
        // selection.removeAllRanges()
        // 插入新的光标对象
        // selection.addRange(range)
    },
    eventReg: function () {

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

        // 窗口大小变化时，隐藏按钮提示
        $(window).on('resize',function (e) {
            if (KeFu.resize_load) {
                KeFu.toggle_popover('hide');
            }
            
            KeFu.resize_load = true;
        });

        // 用户名片保存按钮
        $(document).on('click', '#kefu_user_card form button', function (event) {

            if (!KeFu.session_user) {
                layer.msg('用户找不到啦~');
                return ;
            }
        
            var form_data = {};
            var t = $('#kefu_user_card form').serializeArray();
                $.each(t, function() {
                form_data[this.name] = this.value;
            });

            let user_card_done = {
                c: 'Message',
                a: 'userCard',
                data: {
                    session_user: KeFu.session_user,
                    form_data: form_data,
                    action: 'done'
                }
            };
            KeFu.ws_send(user_card_done);
            return false;
        });

        // 点击轻提示和KeFu悬浮球的事件
        $(document).on('click', '#kefu_button,.kefu_invite_box .consulting,.kefu_popover', function () {

            if (KeFu.config.modulename == 'admin' && $(this).hasClass('kefu_popover')) {
                // 打开最后发送消息的用户的会话窗口
                if (KeFu.last_sender && parseInt(KeFu.last_sender) !== KeFu.session_id) {
                    KeFu.changeSession(KeFu.last_sender);
                }
            }

            if (!KeFu.clickkefu_button) {
                KeFu.toggle_window('show');
            }
        });

        $(document).on('click', '.bubble img', function (e) {

            var img_obj = $(e.target);
            if (img_obj.hasClass('emoji')){
                return ;
            }
            img_obj = img_obj[0];

            KeFu.allowed_close_window  = false;// 按下ecs不允许关闭会话窗口

            layer.photos({
                photos: {
                    "title":"聊天图片预览",
                    "id":"record",
                    data:[
                        {
                            "src":img_obj.src
                        }
                    ]
                },
                end:function () {
                    // 图片预览已关闭
                    KeFu.allowed_close_window  = true;
                },anim: 5 //0-6的选择，指定弹出图片动画类型，默认随机（请注意，3.0之前的版本用shift参数）
            });

        });

        // 按键监听
        $(document).on('keyup', function (event) {

            // console.log('当前按钮的code:', event.keyCode);

            // 对打开会话窗口的监听
            // 打开会话窗口快捷键[ctrl + /],若需修改，请拿到对应键的keyCode替换下一行的191即可，191代表[/]键的keyCode
            if (event.keyCode == 191 && event.ctrlKey) {

                if (KeFu.last_sender) {
                    if (parseInt(KeFu.last_sender) === KeFu.session_id) {
                        // 展开分组
                        if (!KeFu.group_show.dialogue) {
                            $('#heading_dialogue a').click();
                        }
                    }

                    KeFu.changeSession(KeFu.last_sender.toString());
                    KeFu.last_sender = null;
                } else if (KeFu.window_is_show) {
                    KeFu.toggle_window('hide');
                }

                // 需先切换会话再打开窗口
                if (!KeFu.window_is_show) {
                    KeFu.toggle_window('show');
                }
                return ;
            }
            // 对ecs键的监听
            else if (event.keyCode == 27 && KeFu.window_is_show) {
                if (parseInt(KeFu.config.ecs_exit) == 1 && KeFu.allowed_close_window) {
                    $('#KeFuModal').modal('hide');
                } else {
                    layer.closeAll();
                }
            }

        });

        // 拖动悬浮球的事件
        $(document).on('mousedown', '#kefu_button', function (e) {

            KeFu.toggle_popover('hide');
            KeFu.clickkefu_button = false;
            document.onselectstart = function() { return false; };//解决拖动会选中文字的问题
            document.ondragstart = function() { return false; };
            $(document).find("iframe").css("pointer-events", "none");
            KeFu.fast_move = true;
            KeFu.fast_x = e.pageX - parseInt($("#kefu_button").css("left"));
            KeFu.fast_y = e.pageY - parseInt($("#kefu_button").css("top"));

            $(document).on('mousemove', function (e) {

                if (KeFu.fast_move) {

                    var x = e.pageX - KeFu.fast_x;
                    var y = e.pageY - KeFu.fast_y;

                    if (Math.abs(parseInt($("#kefu_button").css("left")) - x) + Math.abs(parseInt($("#kefu_button").css("top")) - y) > 7) {
                        KeFu.clickkefu_button = true;
                    }

                    // 保存按钮位置
                    var kefu_button_coordinate = [y, x];
                    localStorage.setItem("kefu_button_coordinate", kefu_button_coordinate);

                    $("#kefu_button").css({"top": y, "left": x});
                }

            }).mouseup(function () {
                document.onselectstart = null;
                document.ondragstart = null;
                $(document).find("iframe").css("pointer-events", "auto");
                KeFu.fast_move = false;
            });

        });

        // 展开分组
        $(document).on('show.bs.collapse', '.dialogue_panel,.invitation_panel,.recently_panel',function (e) {

            if ($(e.currentTarget).hasClass('dialogue_panel')) {

                KeFu.group_show.dialogue = true;
                KeFu.session_group_red_dot('dialogue',false);

                $('.dialogue_panel').on('hide.bs.collapse',function () {
                    KeFu.group_show.dialogue = false;
                });
            } else if ($(e.currentTarget).hasClass('invitation_panel')) {

                KeFu.group_show.invitation = true;
                KeFu.session_group_red_dot('invitation',false);

                $('.invitation_panel').on('hide.bs.collapse',function () {
                    KeFu.group_show.invitation = false;
                });
            } else if ($(e.currentTarget).hasClass('recently_panel')) {
                KeFu.group_show.recently = true;
                KeFu.session_group_red_dot('recently',false);

                $('.recently_panel').on('hide.bs.collapse',function () {
                    KeFu.group_show.recently = false;
                });
            }
            
        });

        // 客服代表改变状态
        $(document).on('click', '#kefu_csr_status ul li', function (e){

            var load_message = {
                c: 'Message',
                a: 'csrChangeStatus',
                data: {
                    status: $(e.target).data('status')
                }
            };
            KeFu.ws_send(load_message);

        });

        // 隐藏窗口时
        $(document).on('hidden.bs.modal', '#KeFuModal', function (e) {
            $('#kefu_button').show(200);
            KeFu.window_is_show = false;
        });

        // 显示窗口时
        $(document).on('shown.bs.modal', '#KeFuModal', function (e){
            KeFu.window_is_show = true;

            if (!KeFu.getCookie('kefu_new_user')) {
                KeFu.setCookie('kefu_new_user', true, 365);
            }

            if (typeof KeFu.window_show_event == 'function') {
                KeFu.window_show_event();
                KeFu.window_show_event = null;
            }
            
        });

        // 显示表情选择面板
        $(document).on('click', '.smiley', function (e) {
            $('#kefu_emoji').toggle(200);
            $('#kefu_fast_reply_list').fadeOut();
            // 获取焦点
            KeFu.po_last();
        });

        // 选择表情
        $(document).on('click', '#kefu_emoji img', function (e) {
            $('#kefu_message').append(e.target);
            $('#kefu_emoji').hide();
            KeFu.po_last()
        });

        // 用户点击聊天记录窗口，隐藏表情面板
        $(document).on('click', '#kefu_scroll', function () {
            $('#kefu_emoji').hide();
            $('#kefu_fast_reply_list').fadeOut();
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

        // 禁止回车键换行
        $(document).on('keypress', '#kefu_message', function (event) {
            if (parseInt(KeFu.config.send_message_key) == 1 && event.keyCode == "13" && !event.ctrlKey) {
                event.preventDefault()
            }
        });

        if (KeFu.browserType() == "FF") {

            // 过滤输入框中的html标签
            $(document).on('paste', '#kefu_message', function (e) {
                setTimeout(function () {
                    var message = $(e.target).html();
                    $(e.target).html(message.replace("/<(?!img|div).*?>/g", ''));
                    KeFu.po_last();
                },100);
            });
        }

        // 按键发送消息监听
        $(document).on('keyup', '#kefu_message', function (event) {

            var message = $(event.currentTarget).html();

            if (parseInt(KeFu.config.send_message_key) == 1 && event.keyCode == "13" && !event.ctrlKey) {
                if (message) {
                    KeFu.sendMessage(message,0);
                }
            } else if (parseInt(KeFu.config.send_message_key) == 0 && event.keyCode == "13" && event.ctrlKey) {
                if (message) {
                    KeFu.sendMessage(message,0);
                }
            } else if (parseInt(KeFu.config.send_message_key) == 1 && event.keyCode == "13" && event.ctrlKey) {

                // Enter发送消息时，用户按下了ctrl+Enter
                if (KeFu.browserType() == "IE" || KeFu.browserType() == "Edge") {
                    $(event.currentTarget).html(message + "<div></div>");
                } else if (KeFu.browserType() == "FF") {
                    $(event.currentTarget).html(message + "<br/><br/>");
                } else {
                    $(event.currentTarget).html(message + "<div><br/></div>");
                }

                // 获得焦点-光标挪到最后
                KeFu.po_last()
            }

            event.preventDefault()
        });

        // 切换会话
        $(document).on('click', '#session_panel li,#session_list_search li', function (e) {
            
            if ($('.KeFu .modal-body .kefu-right').css('display') == 'none') {
                $('.KeFu .modal-body .kefu-right').show();
                $('.KeFu .modal-body .kefu-left').hide();
            }

            KeFu.changeSession($(e.currentTarget).data('session'));

            if ($(e.currentTarget).data('group') == 'search') {
                $('#session_list_search').hide(200);
            }
        });

        // 手机版聊天窗口兼容
        $(document).on('hide.bs.modal', '#KeFuModal', function (e) {
            if ($('.KeFu .modal-body .kefu-left').css('display') == 'none') {
                $('.KeFu .modal-body .kefu-left').show(200);
                $('.KeFu .modal-body .kefu-right').hide();
                return false;
            }
        });

        // 显示轨迹
        $(document).on('click', '#kefu_view_trajectory', function (e) {
            if (!KeFu.session_id) {
                layer.msg('请选择会话~');
                return ;
            }
            KeFu.changeSession(KeFu.session_id, 'trajectory');
        });

        // 显示用户名片
        $(document).on('click', '#kefu_view_user_card', function (e) {

            if (!KeFu.session_user) {
                layer.msg('请选择会话~');
                return ;
            }

            // 加载用户名片
            KeFu.toggle_window_view('kefu_user_card');

            var kefu_blacklist = {
                c: 'Message',
                a: 'userCard',
                data: {
                    session_user: KeFu.session_user
                }
            };
            KeFu.ws_send(kefu_blacklist);

        });

        // 拉黑名单
        $(document).on('click', '#kefu_blacklist', function () {
            if (!KeFu.session_user) {
                layer.msg('请选择会话~');
                return ;
            }
            var kefu_blacklist = {
                c: 'Message',
                a: 'blacklist',
                data: {
                    session_user: KeFu.session_user
                }
            };
            KeFu.ws_send(kefu_blacklist);
        });

        // 加载更多聊天记录和轨迹
        document.addEventListener('scroll', function (event) {

            if ($(event.target).scrollTop() == 0 && KeFu.chat_record_page != 'done') {
                if (event.target.id == 'kefu_scroll') {

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
                } else if (event.target.id == 'kefu_trajectory') {

                    if (!KeFu.session_user) {
                        return ;
                    }

                    // 加载轨迹记录
                    var load_log = {
                        c: 'Message',
                        a: 'trajectory',
                        data: {
                            session_user: KeFu.session_user,
                            page: KeFu.chat_record_page
                        }
                    };

                    KeFu.ws_send(load_log);
                }
            }

        }, true);

        // 右键菜单
        $(document).on('contextmenu', '.person', function (e) {

            KeFu.select_session_user = $(e.currentTarget).data('session_user');

            var popupmenu = $('#kefu_menu');
            popupmenu.hide();
            $('.kefu_edit_user_nickname').remove();
            $('.kefu_transfer_session').remove();

            let l = ($(document).width() - e.clientX) < popupmenu.width() ? (e.clientX - popupmenu.width()) : e.clientX;
            let t = ($(document).height() - e.clientY) < popupmenu.height() ? (e.clientY - popupmenu.height()) : e.clientY;
            popupmenu.css({left: l, top: t}).show(200);

            if ($(e.currentTarget).data('group') == 'invitation') {
                $('#kefu_menu .invitation').show();
                $('#kefu_menu .transfer').hide();
            } else {
                $('#kefu_menu .invitation').hide();
                $('#kefu_menu .transfer').show();
            }

            e.preventDefault();
        }).click(function () {
            // 菜单的隐藏
            $('#kefu_menu').hide();
        });

        // 会话右键菜单 删除会话 & 邀请对话 & 转接会话 & 修改昵称
        $(document).on('click', '.kefu_menu_item', function (e) {
            var action = $(e.currentTarget).data('action');

            if (KeFu.select_session_user) {

                if (action == 'del') {
                    $("#session_panel [data-session_user='" + KeFu.select_session_user + "']").remove();
                }

                if (action == 'edit_nickname') {

                    var session = $("#session_panel [data-session_user='" + KeFu.select_session_user + "']");
                    var nickname = session.children(".session_info_item").children(".name").eq(0).html();

                    // 显示修改昵称的操作面板
                    var tpl = '\
                    <div class="kefu_edit_user_nickname" data-edit_nickname_user="' + KeFu.select_session_user + '">\
                        <div class="form-group">\
                            <label>修改游客昵称</label>\
                            <input type="text" id="new_nickname" class="form-control" value="' + nickname + '" />\
                            <div class="transfer_session_buttons">\
                                <button type="button" id="edit_user_nickname_cancel" class="btn btn-default btn-sm">取消</button>\
                                <button type="button" id="edit_user_nickname_ok" class="btn btn-success btn-sm">确定</button>\
                            </div>\
                        </div>\
                    </div>';

                    session.after(tpl);

                    $(document).on('click', '#edit_user_nickname_cancel', function (e) {
                        $('.kefu_edit_user_nickname').remove();
                    });

                    $(document).on('click', '#edit_user_nickname_ok', function (e) {
                        
                        var edit_nickname_user = $('.kefu_edit_user_nickname').data('edit_nickname_user');
                        var new_nickname = $('#new_nickname').val();

                        if (edit_nickname_user && new_nickname) {
                            var action_session = {
                                c: 'Message',
                                a: 'actionSession',
                                data: {
                                    action: 'edit_nickname',
                                    session_user: edit_nickname_user,
                                    new_nickname: new_nickname
                                }
                            };
                            KeFu.ws_send(action_session);
                        }

                        $('.kefu_edit_user_nickname').remove();
                    });
                    return ;
                }

                var action_session = {
                    c: 'Message',
                    a: 'actionSession',
                    data: {
                        action: action,
                        session_user: KeFu.select_session_user
                    }
                };
                KeFu.ws_send(action_session);

            } else {
                layer.msg('会话找不到啦~');
            }
        });

        // 搜索会话
        $(document).on('keyup', '#kefu_search_input', function (event) {

            if (KeFu.config.modulename != 'admin') {
                return ;
            }

            if (event.keyCode == "13") {

                var search_key = $('#kefu_search_input').val();

                if (search_key.length <= 0) {

                    // 无搜索词，且预选框已显示，隐藏预选框
                    if ($('#session_list_search').css('display') != 'none') {
                        $('#session_list_search').hide(200);
                        KeFu.search_select_id = '';
                    } else {
                        layer.msg('请输入要查找的用户！');
                    }

                    return ;
                } else {

                    // 有预选人,选中该会话
                    if ($('#session_list_search').css('display') != 'none' && KeFu.search_select_id) {
                        KeFu.changeSession(KeFu.search_select_id);
                        $('#session_list_search').hide(200);
                        KeFu.search_select_id = '';
                        return;
                    }
                }

                var load_message = {
                    c: 'Message',
                    a: 'searchUser',
                    data: search_key
                };

                KeFu.ws_send(load_message);
            } else if (event.keyCode == '38') {
                KeFu.search_primary--;
                KeFu.setSelectedItem();
            } else if (event.keyCode == '40') {
                KeFu.search_primary++;
                KeFu.setSelectedItem();
            }

            event.preventDefault();

            // 搜索框失去焦点
            $(document).on('blur', '#kefu_search_input', function () {
                setTimeout(function () {
                    // 等待点击事件冒泡-防止搜索结果中的蓝色聊天文字不能被点击
                    $('#session_list_search').hide(200);
                    KeFu.search_select_id = 0;
                }, 250);
            });
        });

        /*快捷回复面板*/
        $(document).on('click', '.kefu_fast_reply', function () {
            $('#kefu_fast_reply_list').fadeToggle();
            $('#kefu_emoji').hide();
        });

        /*选择快捷回复*/
        $(document).on('click', '.kefu_fast_reply_list tr', function (e) {
            var reply_id = $(e.currentTarget).data('id');
            var content = KeFu.fast_reply[reply_id].content;
            KeFu.sendMessage(content,0);
            KeFu.po_last();
            $('#kefu_fast_reply_list').fadeOut();
        });
    },
    setSelectedItem: function () {

        if (KeFu.search_primary < 0) {
            KeFu.search_primary = $('#session_list_search').find('li').length - 1;
        } else if (KeFu.search_primary > $('#session_list_search').find('li').length - 1) {
            KeFu.search_primary = 0;
        }

        $('#session_list_search').find('li').removeClass('select_item')
            .eq(KeFu.search_primary).addClass('select_item');

        // 将预选词放入输入框
        $('#kefu_search_input').val($('#session_list_search').find('li').eq(KeFu.search_primary).data('nickname'));
        KeFu.search_select_id = $('#session_list_search').find('li').eq(KeFu.search_primary).data('session');
    },
    browserType: function () {
        var userAgent = navigator.userAgent; //取得浏览器的userAgent字符串
        var isOpera = false;
        if (userAgent.indexOf('Edge') > -1) {
            return "Edge";
        }
        if (userAgent.indexOf('.NET') > -1) {
            return "IE";
        }
        if (userAgent.indexOf("Opera") > -1 || userAgent.indexOf("OPR") > -1) {
            isOpera = true;
            return "Opera"
        }
        ; //判断是否Opera浏览器
        if (userAgent.indexOf("Firefox") > -1) {
            return "FF";
        } //判断是否Firefox浏览器
        if (userAgent.indexOf("Chrome") > -1) {
            return "Chrome";
        }
        if (userAgent.indexOf("Safari") > -1) {
            return "Safari";
        } //判断是否Safari浏览器
        if (userAgent.indexOf("compatible") > -1 && userAgent.indexOf("MSIE") > -1 && !isOpera) {
            return "IE";
        }
        ; //判断是否IE浏览器
    },
    isPc: function () {
        var is_touch = !!("ontouchstart" in window);
        if (!is_touch){
            return true;
        }
        var userAgentInfo = navigator.userAgent;
        var Agents = [
            "Android",
            "iPhone",
            "SymbianOS",
            "Windows Phone",
            "iPad",
            "iPod"
            ];
        var flag = true;
        for (var v = 0; v < Agents.length; v++) {
            if (userAgentInfo.indexOf(Agents[v]) > 0) {
                flag = false;
                break;
            }
        }
        return flag;
    }
}