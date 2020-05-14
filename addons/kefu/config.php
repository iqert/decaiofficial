<?php

return array(
    0 =>
        array(
            'name'    => 'wss_switch',
            'title'   => 'wss协议链接',
            'type'    => 'radio',
            'content' =>
                array(
                    0 => '不开启',
                    1 => '开启',
                ),
            'value'   => '0',
            'rule'    => '',
            'tip'     => '请先参考常见问题配置好wss服务再开启，否则将无法链接',
            'ok'      => '',
            'extend'  => '',
        ),
    1 =>
        array(
            'name'    => 'websocket_port',
            'title'   => 'WebSocket端口',
            'type'    => 'string',
            'content' =>
                array(),
            'value'   => '1818',
            'rule'    => '',
            'tip'     => '安全组和防火墙开放一个端口,并填写至此处',
            'ok'      => '',
            'extend'  => '',
        ),
    2 =>
        array(
            'name'    => 'register_port',
            'title'   => '服务注册端口',
            'type'    => 'string',
            'content' =>
                array(),
            'value'   => '1260',
            'rule'    => '',
            'tip'     => '无需对外开放,属未被占用的端口即可',
            'ok'      => '',
            'extend'  => '',
        ),
    3 =>
        array(
            'name'    => 'internal_start_port',
            'title'   => '内部通讯起始端口',
            'type'    => 'string',
            'content' =>
                array(),
            'value'   => '3200',
            'rule'    => '',
            'tip'     => '无需对外开放,属未被占用的端口即可',
            'ok'      => '',
            'extend'  => '',
        ),
    4 =>
        array(
            'name'    => 'gateway_process_number',
            'title'   => 'Gateway进程数',
            'type'    => 'string',
            'content' =>
                array(),
            'value'   => '2',
            'rule'    => '',
            'tip'     => '设置为CPU核数相等的数量性能最好',
            'ok'      => '',
            'extend'  => '',
        ),
    5 =>
        array(
            'name'    => 'worker_process_number',
            'title'   => 'BusinessWorker进程数',
            'type'    => 'string',
            'content' =>
                array(),
            'value'   => '4',
            'rule'    => '',
            'tip'     => '根据业务有无阻塞式IO,设为CPU核数的1-3倍',
            'ok'      => '',
            'extend'  => '',
        ),
    6 =>
        array(
            'name'    => 'allow_domain',
            'title'   => '跨站调用允许域名',
            'type'    => 'text',
            'content' =>
                array(),
            'value'   => 'http://baidu.com',
            'rule'    => '',
            'tip'     => '一行一个,未在列表内的外站无法引用插件',
            'ok'      => '',
            'extend'  => '',
        ),
    7 =>
        array(
            'name'    => 'rule_out_url',
            'title'   => '以下页面不启动(一行一个)',
            'type'    => 'text',
            'content' =>
                array(),
            'value'   => 'http://kefu_local.com/index/user/index.html?tip=自动排除对应https地址、带参数地址，此处的URL带参数无效',
            'rule'    => '',
            'tip'     => '这些前台页面将不启动在线客服',
            'ok'      => '',
            'extend'  => '',
        ),
);
