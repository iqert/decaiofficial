<?php

namespace addons\kefu\library;

use GatewayWorker\Lib\Gateway;
use think\Db;

/**
 *
 */
class Common
{

    public function __construct()
    {

    }

    /**
     * 发送钉钉通知
     * @param  [type] $robot 机器人列表，一行一个
     * @param  [type] $title 通知标题
     * @param  [type] $content markdown通知内容
     * @param  [type] $isAtAll 是否at所有人
     * @return bool
     */
    public static function dingNotice($robot, $title, $content, $isAtAll)
    {
        $dinghorn   = get_addon_info('dinghorn');
        $is_success = false;

        if ($dinghorn && $dinghorn['state'] == 1) {

            $robot = explode(PHP_EOL, $robot);
            foreach ($robot as $key => $value) {

                $value = (int)trim($value);
                if ($value) {
                    $robot[$key] = $value;
                } else {
                    unset($robot[$key]);
                }
            }

            $robot = implode(',', $robot);
            $robot = Db::name('dinghorn_robot')->whereIn('id', $robot)->select();

            $dataObj = array(
                'msgtype'  => 'markdown',
                'markdown' => [
                    'title' => $title,
                    'text'  => $content,
                ],
                'at'       => [
                    'atMobiles' => [],
                    'isAtAll'   => $isAtAll,
                ]
            );

            $dinghorn = new \addons\dinghorn\library\DinghornLib();
            foreach ($robot as $key => $value) {
                $sign = isset($value['sign']) ? $value['sign'] : false;
                $res  = $dinghorn->msgSend($value['access_token'], $dataObj, $sign);
                if ($res['errcode'] != 0) {
                    $is_success = false;
                }
            }

        }

        return $is_success;
    }

    /**
     * 从其他模块推送消息到会话服务-比如小程序的客服消息推到客服处
     * @param int $session_id 会话ID
     * @param string $message 消息内容
     * @param int $message_type 消息类型
     * @param string $sender 小程序用户带标识的用户ID
     * @return bool
     */
    public static function socketMessage($session_id, $message, $message_type, $sender)
    {
        $kefu_config = get_addon_config('kefu');

        $connection = @stream_socket_client('tcp://127.0.0.1:' . ($kefu_config['register_port'] + 100));

        if (!$connection) {
            return false;
        }

        $http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : false;

        $send_text = [
            'c'    => 'Message',
            'a'    => 'pushMessage',
            'data' => [
                'session_id'   => $session_id,
                'message'      => $message,
                'message_type' => $message_type,
                'sender'       => $sender,
                'origin'       => $http_origin,
            ],
        ];

        $send_text = json_encode($send_text) . "\n";

        if (fwrite($connection, $send_text) !== false) {
            fclose($connection);

            // \think\Log::record('发送的消息内容' . $send_text,'notice');
            return true;
        }

        return false;
    }

    /**
     * 用户token加密
     * @param string $token 待加密的token
     */
    public static function getEncryptedToken($token)
    {
        $token_config = \think\Config::get('token');

        $config = array(
            // 缓存前缀
            'key'      => $token_config['key'],
            // 加密方式
            'hashalgo' => $token_config['hashalgo'],
        );

        return hash_hmac($config['hashalgo'], $token, $config['key']);
    }

    /**
     * 检查管理员身份
     * @param string admin_token 管理员cookie信息
     * @param string admin_id  管理员ID直接登录
     * @return array
     */
    public static function checkAdmin($admin_token, $admin_id = 0)
    {

        if ($admin_id) {

            $admin = Db::name('admin')->field(['password', 'salt'], true)->where('id', $admin_id)->find();
            if (!$admin) {
                return false;
            }
        } else {
            list($id, $keeptime, $expiretime, $key) = explode('|', $admin_token);

            if ($id && $keeptime && $expiretime && $key && $expiretime > time()) {

                $admin = Db::name('admin')->field(['password', 'salt'], true)->where('id', $id)->find();

                if (!$admin || !$admin['token']) {
                    return false;
                }

                // 检查token是否有变更
                $sign = $id . 'kefu_admin_sign_additional';
                if ($key != md5(md5($sign) . md5($keeptime) . md5($expiretime) . $admin['token'])) {
                    return false;
                }

            } else {
                return false;
            }
        }

        // 检查是否是客服账号
        $csr_config = Db::name('kefu_csr_config')
            ->where('admin_id', $admin['id'])
            ->find();

        if ($csr_config) {

            if ($csr_config['status'] == 0) {
                // 修改状态为在线
                Db::name('kefu_csr_config')
                    ->where('admin_id', $admin['id'])
                    ->update(['status' => 3]);

                $admin['status'] = 3;
            } else {
                $admin['status'] = $csr_config['status'];
            }

            $admin['user_id'] = $admin['id'] . '||csr';
            return $admin;
        }

        return false;
    }

    /**
     * 检查FastAdmin用户token
     * @param string token 用户的token信息
     * @return int 用户ID
     */
    public static function checkFaUser($token)
    {
        $cookie_httponly = config('cookie.httponly');
        if (!$cookie_httponly) {
            $user_id = Db::name('user_token')->where('token', Common::getEncryptedToken($token))->value('user_id');
        } else {
            list($id, $key) = explode('|', $token);
            $user_token_list = Db::name('user_token')
                ->where('user_id', $id)
                ->where('expiretime', '>', time())
                ->select();
            foreach ($user_token_list as $user_token) {
                $sign     = $user_token['token'] . 'kefu_user_sign_additional';
                $user_key = md5(md5($id) . md5($sign));
                if ($user_key == $key) {
                    $user_id = $id;
                    break;
                } else {
                    $user_id = false;
                }
            }

        }

        return $user_id;
    }

    /**
     * 检查游客，获取、绑定用户
     * @param string kefu_user_cookie 用户的cookie信息
     * @param int user_id 用户ID
     * @return array
     */
    public static function checkKefuUser($kefu_user_cookie, $user_id = 0)
    {
        // 用户轨迹
        $trajectory = [
            'csr_id' => 0,
            'note'   => '',
        ];

        if ($user_id > 0) {

            $kefu_user = Db::name('kefu_user')
                ->alias('u')
                ->field('u.*,fu.avatar as fu_avatar,fu.nickname as fu_nickname')
                ->join('user fu', 'u.user_id=fu.id', 'LEFT')
                ->where('u.user_id', $user_id)
                ->find();

            // 轨迹数据
            $csr_id = Db::name('kefu_session')
                ->alias('s')
                ->field('s.*,a.id as admin_id,a.nickname')
                ->join('admin a', 's.csr_id=a.id')
                ->where('s.user_id', $user_id)
                ->where('s.deletetime', null)
                ->value('csr_id');

            $trajectory['csr_id'] = $csr_id ? $csr_id : 0;

        } else {

            $_SESSION['is_tourists'] = true;
        }

        if ($kefu_user_cookie && (!isset($kefu_user) || !$kefu_user)) {

            list($id, $keeptime, $expiretime, $key) = explode('|', $kefu_user_cookie);
            if ($id && $keeptime && $expiretime && $key && $expiretime > time()) {

                $kefu_user = Db::name('kefu_user')
                    ->alias('u')
                    ->field('u.*,fu.id as fu_id,fu.avatar as fu_avatar,fu.nickname as fu_nickname')
                    ->join('user fu', 'u.user_id=fu.id', 'LEFT')
                    ->where('u.id', $id)
                    ->find();

                if (!$kefu_user || !$kefu_user['token']) {
                    return false;
                }

                //token有变更
                if ($key != md5(md5($id) . md5($keeptime) . md5($expiretime) . $kefu_user['token'])) {
                    return false;
                }

                // 轨迹数据
                $csr_id = Db::name('kefu_session')
                    ->alias('s')
                    ->field('s.*,a.id as admin_id,a.nickname')
                    ->join('admin a', 's.csr_id=a.id')
                    ->where('s.user_id', $id)
                    ->where('s.deletetime', null)
                    ->value('csr_id');

                $trajectory['csr_id'] = $csr_id ? $csr_id : 0;

                if ($user_id > 0) {

                    // 绑定用户
                    if (!$kefu_user['fu_id']) {
                        $user_info = Db::name('user')->where('id', $user_id)->find();
                        if ($user_info) {
                            Db::name('kefu_user')->where('id', $id)->update([
                                'user_id' => $user_id,
                                // 'avatar'  => $user_info['avatar'], 保留游客的原始数据
                                // 'nickname'=> $user_info['nickname']
                            ]);

                            $kefu_user['avatar']   = $user_info['avatar'];
                            $kefu_user['nickname'] = $user_info['nickname'];

                            $trajectory['note'] = '登录为会员:' . $user_info['nickname'] . '(ID:' . $user_id . ')';
                        }
                    } else {

                        // 当前的游客用户已经绑定了会员
                        // 已有会员登陆，但无对应客服系统用户
                        // 可能是同一设备换号登录(建立新的游客用户并绑定给他)
                        $tourists = self::createTourists();
                        if ($tourists) {

                            Db::name('kefu_user')->where('id', $tourists['kefu_user_id'])->update(['user_id' => $user_id]);
                            self::checkKefuUser($tourists['kefu_user_cookie'], $user_id);
                        } else {
                            return false;
                        }
                    }
                }

            } else {
                return false;
            }
        }

        if (!isset($kefu_user)) {
            return false;
        }

        // 检查黑名单
        $kefu_user['blacklist'] = Db::name('kefu_blacklist')
            ->where('user_id', $kefu_user['id'])
            ->value('id');

        $kefu_user['trajectory'] = $trajectory;

        $kefu_user['user_id'] = $kefu_user['id'] . '||user';
        unset($kefu_user['token']);
        return $kefu_user;
    }

    /**
     * 创建一个游客
     * @return [type] [description]
     */
    public static function createTourists($referrer = '')
    {
        $tourists_max_id = Db::name('kefu_user')->max('id');
        $token           = \fast\Random::uuid();
        $kefu_user       = [
            'avatar'     => '', // 随机头像->算了算了
            'nickname'   => '游客 ' . $tourists_max_id,
            'referrer'   => $referrer,
            'token'      => $token,
            'createtime' => time(),
        ];

        if (Db::name('kefu_user')->insert($kefu_user)) {

            $kefu_user_id     = Db::name('kefu_user')->getLastInsID();
            $keeptime         = 864000;
            $expiretime       = time() + $keeptime;
            $key              = md5(md5($kefu_user_id) . md5($keeptime) . md5($expiretime) . $token);
            $kefu_user_cookie = [$kefu_user_id, $keeptime, $expiretime, $key];

            return [
                'kefu_user_cookie' => implode('|', $kefu_user_cookie),
                'kefu_user_id'     => $kefu_user_id,
            ];
        } else {
            return false;
        }
    }

    /**
     * 分配/转移客服
     * @param int csr 客服代表带标识符ID
     * @param int user 带标识符用户ID
     * @return array 新的会话信息
     */
    public static function distributionCsr($csr, $user = false)
    {
        if (!$user) {
            $user = $_SESSION['user_id'];
        }

        $user_info = self::userInfo($user);
        if ($user_info['source'] == 'csr') {
            return false;
        }

        $csr_info = self::userInfo($csr);
        if ($csr_info['source'] != 'csr') {
            return false;
        }

        // 检查是否已有客服
        // 插入一条会话
        // 修改客服的最后接待时间
        // 修改客服当前接待量
        // 记录会话日志

        $session = Db::name('kefu_session')
            ->alias('s')
            ->field('s.*,a.id as admin_id,a.nickname')
            ->join('admin a', 's.csr_id=a.id')
            ->where('s.user_id', $user_info['id'])
            ->where('s.deletetime', null)
            ->find();

        if ($session) {
            // 记录会话日志-并修改会话客服人员
            Db::startTrans();
            try {

                $note = '客服代表已由 ' . $session['nickname'] . ' 转为 ' . $csr_info['nickname'];

                // 记录轨迹
                $trajectory = [
                    'user_id'    => $user_info['id'],
                    'csr_id'     => $csr_info['id'],
                    'log_type'   => 8,
                    'note'       => $note,
                    'url'        => '',
                    'referrer'   => '',
                    'createtime' => time(),
                ];
                Db::name('kefu_trajectory')->insert($trajectory);

                self::chatRecord($session['id'], $note, 3);

                Db::name('kefu_session')->where('id', $session['id'])->update([
                    'csr_id' => $csr_info['id'],
                ]);

                // 插入接待记录,用于数据统计
                $reception_log = [
                    'csr_id'     => $csr_info['id'],
                    'user_id'    => $user_info['id'],
                    'createtime' => time(),
                ];
                Db::name('kefu_reception_log')->insert($reception_log);

                Db::name('kefu_csr_config')->where('admin_id', $csr_info['id'])
                    ->inc('reception_count')
                    ->update([
                        'last_reception_time' => time(),
                    ]);

                $reception_count = Db::name('kefu_csr_config')
                    ->where('admin_id', $session['admin_id'])
                    ->value('reception_count');

                if ($reception_count) {
                    Db::name('kefu_csr_config')
                        ->where('admin_id', $session['admin_id'])
                        ->setDec('reception_count');
                }

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                return false;
            }

            $session['csr_id']   = $session['admin_id'] = $csr_info['id'];
            $session['nickname'] = $csr_info['nickname'];
            return $session;
        } else {
            $kefu_session = [
                'user_id'    => $user_info['id'],
                'csr_id'     => $csr_info['id'],
                'createtime' => time(),
            ];

            Db::startTrans();
            try {

                $trajectory = [
                    'user_id'    => $user_info['id'],
                    'csr_id'     => $csr_info['id'],
                    'log_type'   => 2,
                    'note'       => '客服代表 ' . $csr_info['nickname'],
                    'url'        => '',
                    'referrer'   => '',
                    'createtime' => time(),
                ];
                Db::name('kefu_trajectory')->insert($trajectory);

                Db::name('kefu_session')->insert($kefu_session);
                $session_id = Db::name('kefu_session')->getLastInsID();

                // 插入接待记录,用于数据统计
                $reception_log = [
                    'csr_id'     => $csr_info['id'],
                    'user_id'    => $user_info['id'],
                    'createtime' => time(),
                ];
                Db::name('kefu_reception_log')->insert($reception_log);

                // 发送欢迎消息
                $new_user_msg = Db::name('kefu_config')->where('name', 'new_user_msg')->value('value');
                if ($new_user_msg) {
                    self::chatRecord($session_id, $new_user_msg, 0, $csr);
                }

                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                return false;
            }

            $session = Db::name('kefu_session')
                ->alias('s')
                ->field('s.*,a.id as admin_id,a.nickname')
                ->join('admin a', 's.csr_id=a.id')
                ->where('s.user_id', $user_info['id'])
                ->where('s.deletetime', null)
                ->find();

            return $session;
        }
    }

    /**
     * 获取用户信息
     * @param string user 带标识符的用户id
     * @return array
     */
    public static function userInfo($user)
    {
        $user = explode('||', $user);

        if (isset($user[1])) {

            if ($user[1] == 'user') {

                $user_info = Db::name('kefu_user')
                    ->alias('u')
                    ->field('u.*,fu.avatar as fu_avatar,fu.nickname as fu_nickname')
                    ->join('user fu', 'u.user_id=fu.id', 'LEFT')
                    ->where('u.id', $user[0])
                    ->find();

                if ($user_info) {

                    $user_info['avatar'] = $user_info['fu_avatar'] ? $user_info['fu_avatar'] : $user_info['avatar'];

                    if ($user_info['fu_nickname']) {
                        $user_info['nickname_origin'] = $user_info['nickname'];
                        $user_info['nickname']        = $user_info['fu_nickname'] . '(' . $user_info['nickname'] . ')';
                    } else {
                        $user_info['nickname_origin'] = $user_info['nickname'];
                    }
                }

                $user_info['session_type'] = 1;
                $user_info['source']       = 'user';
            } elseif ($user[1] == 'csr') {
                $user_info                 = Db::name('admin')->where('id', $user[0])->find();
                $user_info['session_type'] = 2;
                $user_info['source']       = 'csr';

                $user_info['status'] = Db::name('kefu_csr_config')
                    ->where('admin_id', $user_info['id'])
                    ->value('status');
            }

            if (!$user_info || !isset($user_info['id'])) {
                $user_info['id']           = $user[0];
                $user_info['source']       = 'unknown';
                $user_info['nickname']     = '未知用户' . $user[0];
                $user_info['avatar']       = false;
                $user_info['session_type'] = 0;
            }

            $user_info['avatar'] = self::imgSrcFill($user_info['avatar']);
        } else {
            $user_info['id']           = $user[0];
            $user_info['source']       = 'unknown';
            $user_info['nickname']     = '未知用户' . $user[0];
            $user_info['avatar']       = self::imgSrcFill(false);
            $user_info['session_type'] = 0;
        }

        return $user_info;
    }

    /**
     * 获取图片的完整地址
     * @param string src 待处理的图片
     * @return string
     */
    public static function imgSrcFill($src)
    {
        $__CDN__ = config('view_replace_str.__CDN__');

        // 如果$_SESSION['origin']有值，则代表是 workerman 环境
        $domain = isset($_SESSION['origin']) ? $_SESSION['origin'] : request()->domain();
        $domain = $__CDN__ ? $__CDN__ : $domain;
        return $src ? cdnurl($src, $domain) : $domain . '/assets/img/avatar.png';
    }

    /**
     * 写入聊天记录/系统消息
     * @param int session_id 会话ID
     * @param string message 消息内容
     * @param string message_type 消息类型
     * @param string sender 带标识的发送人
     * @return bool
     */
    public static function chatRecord($session_id, $message, $message_type = 0, $sender = false)
    {
        $session = Db::name('kefu_session')->where('id', $session_id)->find();

        if (!$session) {
            return ['msgtype' => 'send_message', 'code' => 0, 'msg' => '发送失败,会话找不到啦！'];
        }

        if (!$sender) {
            $sender       = $_SESSION['user_id'];
            $session_user = self::sessionUser($session);
        } else {
            $user = explode('||', $sender);

            if ($user[1] == 'csr' && $user[0] == $session['csr_id']) {
                $session_user = $session['user_id'] . '||user';
            } else if ($user[1] == 'user' && $user[0] == $session['user_id']) {
                $session_user = $session['csr_id'] . '||csr';
            } else {
                return ['msgtype' => 'send_message', 'code' => 0, 'msg' => '发送失败,无法确定收信人！'];
            }
        }

        // 发信人
        $sender_info = self::userInfo($sender);

        // 收信人信息
        $session_user_info = self::userInfo($session_user);

        $sender_identity = ($sender_info['source'] == 'csr') ? 0 : 1;

        $message_html = htmlspecialchars_decode($message);

        $message = [
            'session_id'      => $session_id,
            'sender_identity' => $sender_identity,
            'sender_id'       => $sender_info['id'],
            'message'         => $message,// 入库的消息内容不解码
            'message_type'    => ($message_type == 'auto_reply') ? 0 : $message_type,
            'status'          => 0,
            'createtime'      => time(),
        ];

        // 为小程序用户推送消息
        if (isset($session_user_info['wechat_openid']) && $session_user_info['wechat_openid']) {

            $preg = '/<img.*?src="(.*?)".*?>/is';
            preg_match_all($preg, $message_html, $result, PREG_PATTERN_ORDER); // 匹配img的src

            $message_text = isset($result[1][0]) ? $result[1][0] : $message['message'];// 消息中的图片
            $message_text = strip_tags($message_text);// 小程序内不能渲染标签

            $wxBizMsg = new \addons\kefu\library\WechatCrypto\wxBizMsgCrypt();
            $wxBizMsg->sendMessage($session_user_info['wechat_openid'], "text", $message_text);
        }

        if (Db::name('kefu_record')->insert($message)) {
            $message['record_id'] = Db::name('kefu_record')->getLastInsID(); //消息记录ID

            // 确定会话状态
            Db::name('kefu_session')->where('id', $session['id'])->update(['deletetime' => null, 'createtime' => time()]);

            if (class_exists('\GatewayWorker\Lib\Gateway') && $sender != $session_user && Gateway::isUidOnline($session_user)) {

                // 加上发信人的信息
                $message['id']           = $message['session_id'];
                $message['avatar']       = $sender_info['avatar'];
                $message['nickname']     = $sender_info['nickname'];
                $message['session_user'] = $sender;
                $message['online']       = 1;
                $message['last_message'] = self::formatMessage($message);
                $message['last_time']    = self::formatTime(null);
                $message['message']      = $message_html;
                $message['sender']       = 'you';

                // 查询当前用户发送的未读消息条数
                $message['unread_msg_count'] = Db::name('kefu_record')
                    ->where('session_id', $message['session_id'])
                    ->where('sender_identity', $sender_identity)
                    ->where('sender_id', $sender_info['id'])
                    ->where('status', 0)
                    ->count('id');

                Gateway::sendToUid($session_user, json_encode(['msgtype' => 'new_message', 'data' => $message]));

                if ($message_type == 'auto_reply') {
                    // 通知客服端：如果客服端口刚好打开的此用户的窗口->重载消息列表以显示自动回复
                    Gateway::sendToUid($sender, json_encode(['msgtype' => 'reload_record', 'data' => [
                        'session_id' => $message['session_id']
                    ]]));
                }
            }

            // 用户给客服发送消息，检查知识库自动回复
            $message_text = trim(strip_tags($message_html));// 去除消息中的标签
            $kbs_switch   = Db::name('kefu_config')->where('name', 'kbs_switch')->value('value');
            if ($sender_identity == 1 && $message_text && $kbs_switch) {
                // 读取知识库
                $kbs = Db::name('kefu_kbs')
                    ->where('status', '1')
                    ->where('deletetime', null)
                    ->where("admin_id like :csr_id OR admin_id=''")// id初步like筛选
                    ->bind(['csr_id' => '%' . $session_user_info['id'] . '%'])
                    ->order('weigh desc')
                    ->select();

                // 计算匹配度
                $last_kb_match = 0;
                $best_kb       = [];// 最佳匹配
                $StrComparison = new \addons\kefu\library\StrComparison();
                foreach ($kbs as $key => $kb) {

                    // 去除限定外的知识点
                    if ($kb['admin_id']) {
                        $kb['admin_id'] = explode(',', $kb['admin_id']);
                        if (!in_array($session_user_info['id'], $kb['admin_id'])) {
                            // unset($kbs[$key]);
                            continue;
                        }
                    }

                    $kb_questions = explode(PHP_EOL, $kb['questions']);
                    foreach ($kb_questions as $kb_question) {
                        $kb_question = trim($kb_question);
                        if ($kb_question) {
                            $match_temp = $StrComparison->getSimilar($kb_question, $message_text);
                            if ($match_temp > 0 && $match_temp > $last_kb_match && $match_temp >= $kb['match']) {
                                $last_kb_match = $match_temp;
                                $best_kb       = $kbs[$key];
                            }
                        }
                    }
                }

                // 发送
                if ($best_kb) {
                    self::chatRecord($session_id, $best_kb['answer'], 'auto_reply', $session_user);
                } else {

                    // 读取万能知识
                    $kbs = Db::name('kefu_kbs')
                        ->where('status', '2')
                        ->where('deletetime', null)
                        ->where("admin_id like :csr_id OR admin_id=''")// id初步like筛选
                        ->bind(['csr_id' => '%' . $session_user_info['id'] . '%'])
                        ->order('weigh desc')
                        ->select();
                    foreach ($kbs as $key => $kb) {
                        // 去除限定外的知识点
                        if ($kb['admin_id']) {
                            $kb['admin_id'] = explode(',', $kb['admin_id']);
                            if (!in_array($session_user_info['id'], $kb['admin_id'])) {
                                continue;
                            }
                        }

                        self::chatRecord($session_id, $kbs[$key]['answer'], 'auto_reply', $session_user);
                        break;
                    }
                }
            }

            return ['msgtype' => 'send_message', 'code' => 1];
        } else {
            return ['msgtype' => 'send_message', 'code' => 0, 'msg' => '发送失败,请重试！'];
        }
    }

    /**
     * 获取一个会话的会话对象
     * @param array session 会话详细信息
     * @param string user_id 带标识符的当前用户id
     * @return string session_user_id 带标识符的会话对象id
     */
    public static function sessionUser($session, $user_id = false)
    {
        if (!$user_id) {
            $user_id = $_SESSION['user_id'];
        }

        $user = explode('||', $user_id);

        if (isset($user[1])) {
            if ($user[1] == 'csr' && $user[0] == $session['csr_id']) {
                return $session['user_id'] . '||user';
            } else if ($user[1] == 'user' && $user[0] == $session['user_id']) {
                return $session['csr_id'] . '||csr';
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 格式化消息-将图片和连接用文字代替
     * @param array message 消息内容
     * @return string
     */
    public static function formatMessage($message)
    {
        if (!$message) {
            return '';
        }
        if ($message['message_type'] == 0 || $message['message_type'] == 3) {
            $message_text = htmlspecialchars_decode($message['message']);

            // 匹配所有的img标签
            $preg = '/<img.*?src=(.*?)>/is';
            preg_match_all($preg, $message_text, $result, PREG_PATTERN_ORDER);
            $message_text = str_replace($result[0], '[图片]', $message_text);

        } else if ($message['message_type'] == 1) {
            $message_text = '[图片]';
        } else if ($message['message_type'] == 2) {
            $message_text = '[链接]';
        }

        return $message_text;
    }

    /**
     * 格式化时间
     * @param int time 时间戳
     * @return string
     */
    public static function formatTime($time = null)
    {

        $text     = '';
        $now_time = time();
        $time     = ($time === null || $time > $now_time || $time == $now_time) ? $now_time + 1 : intval($time);

        $t = (int)($now_time - $time); //时间差 （秒）
        $y = date('Y', $time) - date('Y', $now_time); //是否跨年

        switch ($t) {
            case $t <= 0:
                $text = '刚刚';
                break;
            case $t < 60:
                $text = $t . '秒前'; // 一分钟内
                break;
            case $t < 60 * 60:
                $text = floor($t / 60) . '分钟前'; //一小时内
                break;
            case $t < 60 * 60 * 24:
                $text = floor($t / (60 * 60)) . '小时前'; // 一天内
                break;
            case $t < 60 * 60 * 24 * 3:
                $text = floor($time / (60 * 60 * 24)) == 1 ? '昨天' . date('H:i', $time) : '前天' . date('H:i', $time); //昨天和前天
                break;
            case $t < 60 * 60 * 24 * 30:
                $text = date('m-d H:i', $time); //一个月内
                break;
            case $t < 60 * 60 * 24 * 365 && $y == 0:
                $text = date('m-d', $time); //一年内
                break;
            default:
                $text = date('Y-m-d', $time); //一年以前
                break;
        }

        return $text;
    }

    /**
     * 发送消息
     * @param string client_id 链接ID
     * @param string msg 消息内容
     */
    public static function showMsg($client_id, $msg = '')
    {
        Gateway::sendToClient($client_id, json_encode([
            'code'    => 0,
            'msgtype' => 'show_msg',
            'msg'     => $msg,
        ]));
    }

    /**
     * 获取或改变客服代表状态
     * @param int $status 状态标识符
     * @param string $csr 带标识符的客服代表ID,或者客服数据数组
     * @return string 状态
     */
    public static function csrStatus($status = null, $csr = false)
    {
        if (!$csr) {
            $csr = $_SESSION['user_id'];
        }

        if (!is_array($csr)) {
            $csr = self::userInfo($csr);
        }

        if ($csr['source'] == 'csr') {

            if ($status !== null) {
                Db::name('kefu_csr_config')->where('admin_id', $csr['id'])->update(['status' => $status]);
            } else {
                $status = $csr['status'];
            }

            return $status;
        } else {
            return 'none';
        }
    }

    /**
     * 获取用户的未读消息->获取他的会话->获取会话中的非他自己发送的未读消息
     * @param string user_id 带标识符的用户id
     * @param bool is_latest 是否只获取用户已进入网站但未链接websocket期间的消息
     * @return string
     */
    public static function getUnreadMessages($user_id, $is_latest = false)
    {
        $new_msg = '';

        $user = self::userInfo($user_id);

        if ($user['source'] == 'csr') {

            $session_list = Db::name('kefu_session')
                ->where('csr_id', $user['id'])
                ->order('createtime desc')
                ->select();
        } else {
            $session_list = Db::name('kefu_session')
                ->where('user_id', $user['id'])
                ->order('createtime desc')
                ->select();
        }

        foreach ($session_list as $key => $value) {

            $session_user = self::sessionUser($value, $user_id);

            $where['session_id']      = $value['id'];
            $where['sender_identity'] = ($user['source'] == 'csr') ? 1 : 0;
            $where['status']          = 0;

            if ($is_latest) {
                $where['createtime'] = ['>', time() - 10];
            }

            $new_msg = Db::name('kefu_record')
                ->where($where)
                ->order('createtime desc')
                ->find();

            if ($new_msg) {
                $session_user_info = self::userInfo($session_user);
                $new_msg           = $session_user_info['nickname'] . ':' . self::formatMessage($new_msg);
                break;
            }
        }

        return $new_msg;
    }

    /*
     * 轨迹分析
     */
    public static function trajectoryAnalysis($url)
    {
        if (!$url) {
            return '';
        }

        $parse_url = parse_url($url);
        if (!$parse_url) {
            return $url;
        }

        $parse_url['query'] = isset($parse_url['query']) ? self::convertUrlQuery($parse_url['query']) : false;
        $data['host_name']  = false;
        $data['search_key'] = false;

        if (isset($parse_url['host'])) {

            if ($parse_url['host'] == 'www.baidu.com') {
                $data['host_name']  = '百度';
                $data['search_key'] = isset($parse_url['query']['wd']) ? $parse_url['query']['wd'] : '';
            }

            if ($parse_url['host'] == 'www.so.com') {
                $data['host_name']  = '360搜索';
                $data['search_key'] = isset($parse_url['query']['q']) ? $parse_url['query']['q'] : '';
            }
        }

        if ($data['host_name']) {
            $res = $data['host_name'];
        } else {
            return $url;
        }

        if ($data['search_key']) {
            $res .= '搜索 ' . $data['search_key'];
        }

        return $res;
    }

    /**
     * 解析一个url的参数
     *
     * @param string    query
     * @return    array    params
     */
    public static function convertUrlQuery($query)
    {
        $queryParts = explode('&', $query);

        $params = array();
        foreach ($queryParts as $param) {
            $item             = explode('=', $param);
            $params[$item[0]] = $item[1];
        }

        return $params;
    }

    /*
     * 检查/过滤变量
     */
    public static function checkVariable(&$variable)
    {
        $variable = htmlspecialchars($variable);
        $variable = stripslashes($variable); // 删除反斜杠
        $variable = addslashes($variable); // 转义特殊符号
        $variable = trim($variable); // 去除字符两边的空格
    }
}
