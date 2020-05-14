<?php

namespace app\admin\controller\kefu;

use GatewayWorker\Gateway;
use Workerman\Worker;

// 自动加载类
require_once __DIR__ . '/../../../../addons/kefu/library/GatewayWorker/vendor/autoload.php';

/**
 * Win下启动 gateway服务 专用类
 */
class Sgateway
{
    
    function __construct()
    {
        
        // gateway 进程
        $context = array(/*'ssl' => array(
                // 使用绝对路径
                'local_cert'                 => '/www/wwwroot/soket.cn/vendor/workerman/cert/soket.cn.pem', // 也可以是crt文件
                'local_pk'                   => '/www/wwwroot/soket.cn/vendor/workerman/cert/soket.cn.key',
                'verify_peer'               => false,
                // 'allow_self_signed' => true, //如果是自签名证书开启此选项
            )*/
        );

        $kefu_config = get_addon_config('kefu');
        $gateway        = new Gateway("websocket://0.0.0.0:" . $kefu_config['websocket_port'], $context);

        // 开始SSL
        // $gateway->transport = 'ssl';

        // gateway名称，status方便查看
        $gateway->name = 'KeFuGateway';

        // gateway进程数
        $gateway->count = $kefu_config['gateway_process_number'];

        // 本机ip，分布式部署时使用内网ip
        $gateway->lanIp = '127.0.0.1';

        // 内部通讯起始端口，假如$gateway->count=4，起始端口为4000
        // 则一般会使用4000 4001 4002 4003 4个端口作为内部通讯端口 
        $gateway->startPort = $kefu_config['internal_start_port'];

        // 服务注册地址
        $gateway->registerAddress = '127.0.0.1:' . $kefu_config['register_port'];

        // 心跳间隔
        $gateway->pingInterval = 30;

        $gateway->pingNotResponseLimit = 1;

        // 心跳数据
        $gateway->pingData = '';

        // 如果不是在根目录启动，则运行runAll方法
        if (!defined('GLOBAL_START')) {
            Worker::runAll();
        }
    }
}