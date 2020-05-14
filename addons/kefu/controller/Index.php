<?php

namespace addons\kefu\controller;

use addons\kefu\library\Common;
use fast\Random;
use think\addons\Controller;
use think\Config;
use think\Db;
use think\Cookie;

class Index extends Controller
{
    protected $noNeedLogin = ['initialize', 'loadMessagePrompt', 'upload', 'mobile'];

    protected $chat_config;

    protected $token_info = false;// 用户、游客、管理员的资料

    protected $token_list = [];// 要发送给前台的token（前台利用这些token链接websocket）

    public function index()
    {
        $this->view->assign('chat_name', $this->chat_config['chat_name']);
        return $this->view->fetch();
    }

    public function mobile()
    {
        $this->view->assign('chat_name', $this->chat_config['chat_name']);
        return $this->view->fetch();
    }

    /**
     * 获取配置、初始化AJAX请求过来的用户的身份等
     */
    public function _initialize()
    {
        $this->chat_config          = Db::name('kefu_config')->column('name,value');
        $kefu_config                = get_addon_config('kefu');
        $kefu_config['__CDN__']     = config('view_replace_str.__CDN__');
        $upload['upload']           = \app\common\model\Config::upload();
        $upload['upload']['cdnurl'] = $upload['upload']['cdnurl'] ? $upload['upload']['cdnurl'] : cdnurl('', true);
        $this->chat_config          = array_merge($this->chat_config, $kefu_config, $upload);

        // 跨域配置
        $allow_domain = explode(PHP_EOL, trim($this->chat_config['allow_domain']));
        $http_origin  = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : false;

        if ($http_origin && in_array($http_origin, $allow_domain)) {
            header('Access-Control-Allow-Origin: ' . $http_origin);
            header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
            header('Access-Control-Allow-Methods: POST, GET');
        } else {
            if (isset($_SERVER['HTTP_ORIGIN']) &&
                str_replace('http://', '', $_SERVER['HTTP_ORIGIN']) != $_SERVER['HTTP_HOST'] &&
                str_replace('https://', '', $_SERVER['HTTP_ORIGIN']) != $_SERVER['HTTP_HOST']
            ) {
                $this->result(null, 0, '您的站点未被允许调用' . $this->chat_config['chat_name'], 'json');
            }
        }

        // 配置排除
        $except_config = [
            'allow_domain',
            'wechat_app_id',
            'wechat_app_secret',
            'wechat_encodingkey',
            'wechat_token',
            'worker_process_number',
            'csr_admin',
            'csr_distribution',
            'register_port'
        ];
        foreach ($except_config as $key => $value) {
            if (in_array($value, $except_config)) {
                unset($this->chat_config[$value]);
            }
        }

        // 页面排除
        if ($this->chat_config['rule_out_url'] && isset($_SERVER['HTTP_REFERER'])) {

            $http_referer = $this->urlDealWith($_SERVER['HTTP_REFERER']);
            $rule_out_url = explode(PHP_EOL, $this->chat_config['rule_out_url']);

            foreach ($rule_out_url as $key => $value) {
                if ($this->urlDealWith($value) == $http_referer) {
                    $this->result(null, 401, $this->chat_config['chat_name'] . ' 当前页面被设置为不启动', 'json');
                }
            }
        }

        // 用户登录
        $data                                    = $this->request->only(['modulename']);
        $this->token_list['kefu_tourists_token'] = Cookie::get('kefu_user');

        if (!isset($data['modulename'])) {
            if ($this->request->action() == 'mobile' || $this->request->action() == 'index') {
                $data['modulename'] = 'index';
            } else {
                $this->result(null, 0, $this->chat_config['chat_name'] . ' 模块未知' . $this->request->action(), 'json');
            }
        }

        if ($data['modulename'] == 'admin') {

            // 验证管理员身份
            $auth = \app\admin\library\Auth::instance();
            if ($auth->isLogin()) {
                $this->token_info = Common::checkAdmin(false, $auth->id);

                if ($this->token_info) {

                    // workerman 中不支持 PHP session和cookie，所以 $auth 类失效
                    // 此处对管理员 token 加密稍作修改，供客服自动登录使用
                    $keeptime   = 864000;
                    $expiretime = time() + $keeptime;

                    // 原规则为单纯的id，若需修改附加的字符串，请将`Common::checkAdmin`方法里边的附加字符串一起修改
                    $sign = $this->token_info['id'] . 'kefu_admin_sign_additional';

                    $key                            = md5(md5($sign) . md5($keeptime) . md5($expiretime) . $this->token_info['token']);
                    $cookie_data                    = [$this->token_info['id'], $keeptime, $expiretime, $key];
                    $this->token_list['kefu_token'] = implode('|', $cookie_data);
                }
            }

            // 清理轨迹
            switch ($this->chat_config['trajectory_save_cycle']) {
                case 0:
                    $where_time = 604800; // 清理7天前的
                    break;
                case 1:
                    $where_time = 2592000; // 30天
                    break;
                case 2:
                    $where_time = 5184000; // 60天
                    break;

                default:
                    $where_time = false; // 不清理
                    break;
            }

            if ($where_time) {
                Db::name('kefu_trajectory')->where('createtime', '<', time() - $where_time)->delete();
            }

        } else if ($data['modulename'] != 'admin') {
            // 验证用户身份
            $auth  = \app\common\library\Auth::instance();
            $token = Cookie::get('token');

            if ($token) {
                $auth->init($token);
                if ($auth->isLogin()) {
                    $this->token_info = Common::checkKefuUser($this->token_list['kefu_tourists_token'], $auth->id);
                    $cookie_httponly  = config('cookie.httponly');

                    // workerman 中不支持 PHP session和cookie，所以 $auth 类失效
                    // 在开启 $cookie_httponly 时，对用户的 token 加密稍作修改，供客服自动登录使用
                    if ($this->token_info) {

                        if (!$cookie_httponly) {
                            $this->token_list['kefu_token'] = $token;
                        } else {

                            // 若需修改附加的字符串，请将`Events::onWebSocketConnect`方法里边的附加字符串一起修改
                            // 先用 user_token 数据表中的token字段同样的加密算法对token进行加密，否则workerman无法识别用户身份
                            $sign                           = Common::getEncryptedToken($token) . 'kefu_user_sign_additional';
                            $key                            = md5(md5($auth->id) . md5($sign));
                            $cookie_data                    = [$auth->id, $key];
                            $this->token_list['kefu_token'] = implode('|', $cookie_data);
                        }
                    }
                }
            }

        }

        if ($data['modulename'] != 'admin' && $this->token_list['kefu_tourists_token'] && !$this->token_info) {
            // 验证游客用户身份
            $this->token_info = Common::checkKefuUser($this->token_list['kefu_tourists_token'], 0);
        }
    }

    /**
     * 去除URL中的 index.php、https://、http://、去除参数
     * @param  [type] $url [description]
     * @return string      处理结果
     */
    private function urlDealWith($url)
    {
        $url = explode('?', $url);
        $url = isset($url[0]) ? $url[0] : ''; // 只要 ? 号前的字符串
        return str_replace(['http://', 'https://'], '', trim($url));
    }

    /**
     * 供跨站下载来信提示音文件
     */
    public function loadMessagePrompt()
    {
        $file = $this->chat_config['__CDN__'] ? $this->chat_config['__CDN__'] : ROOT_PATH . 'public' . '/assets/addons/kefu/audio/message_prompt.wav';
        header("Content-type:application/octet-stream");
        $filename = basename($file);
        header("Content-Disposition:attachment;filename = " . $filename);
        header("Accept-ranges:bytes");
        header("Accept-length:" . filesize($file));
        readfile($file);
    }

    public function initialize()
    {
        $res_data    = [];
        $data        = $this->request->only(['modulename']);
        $referrer    = Common::trajectoryAnalysis($this->request->get('referrer'));
        $current_url = $this->request->header('referer');
        $config      = $this->chat_config;

        if ($this->token_info) {

            if (isset($this->token_info['blacklist']) && $this->token_info['blacklist']) {
                $this->result(null, 401, '黑名单用户！', 'json');
            }

        } else if ($data['modulename'] != 'admin') {

            // 建立游客身份
            $tourists = Common::createTourists($referrer . ' IP:' . $this->request->ip());
            if ($tourists) {
                $this->token_info                        = Common::checkKefuUser($tourists['kefu_user_cookie'], 0);
                $this->token_list['kefu_tourists_token'] = $tourists['kefu_user_cookie'];
                Cookie::set('kefu_user', $tourists['kefu_user_cookie']);
            } else {
                $this->result(null, 401, $this->chat_config['chat_name'] . ' 游客创建失败！', 'json');
            }
        } else {
            $this->result(null, 401, $this->chat_config['chat_name'] . ' 管理员未登陆或登录管理员非客服代表', 'json');
        }

        if ($data['modulename'] == 'admin') {

            // 快捷回复
            $fast_reply = Db::name('kefu_fast_reply')
                ->where('admin_id=' . $this->token_info['id'] . ' OR admin_id=0')
                ->where('status', '1')
                ->where('deletetime', null)
                ->select();

            $fast_reply_temp = [];
            foreach ($fast_reply as $key => $value) {
                $fast_reply_temp[$value['id']] = $value;
            }
            unset($fast_reply);

            $res_data['fast_reply'] = $fast_reply_temp;
            $this->view->assign('fast_reply', $fast_reply_temp);
            $res_data['window_html'] = $this->view->fetch(ROOT_PATH . 'public/assets/addons/kefu/tpl/admin_default.html');
        } else {

            $config['invite_box_img']         = $config['invite_box_img'] ? cdnurl($config['invite_box_img'], true) : false;
            $config['auto_invitation_switch'] = ($config['invite_box_img']) ? $config['auto_invitation_switch'] : 0;

            // 只在有客服在线时弹出邀请框
            if ($config['only_csr_online_invitation'] && $config['auto_invitation_switch']) {
                $online_csr                       = Db::name('kefu_csr_config')->where('status', 3)->value('admin_id');
                $config['auto_invitation_switch'] = $online_csr ? $config['auto_invitation_switch'] : 0;
            }

            // 前台轮播图
            $config['slider_images'] = $config['slider_images'] ? explode(',', trim($config['slider_images'], ',')) : [];
            foreach ($config['slider_images'] as $key => $value) {
                $config['slider_images'][$key] = cdnurl($value, true);
            }

            $res_data['window_html'] = $this->view->fetch(ROOT_PATH . 'public/assets/addons/kefu/tpl/default.html');
        }

        // 记录轨迹
        if (isset($this->token_info['trajectory'])) {
            // 用户轨迹
            $trajectory = [
                'user_id'    => $this->token_info['id'],
                'csr_id'     => $this->token_info['trajectory']['csr_id'],
                'log_type'   => 0,
                'note'       => $this->token_info['trajectory']['note'],
                'url'        => $current_url,
                'referrer'   => $referrer,
                'createtime' => time(),
            ];

            Db::name('kefu_trajectory')->insert($trajectory);
        }

        // 窗口抖动配置处理
        $config['is_shake'] = false;
        if ($config['new_message_shake'] == 3) {
            $config['is_shake'] = true;
        } else if ($config['new_message_shake'] == 1 && $data['modulename'] != 'admin') {
            $config['is_shake'] = true;
        } else if ($config['new_message_shake'] == 2 && $data['modulename'] == 'admin') {
            $config['is_shake'] = true;
        }

        $res_data['user_info']  = $this->token_info;
        $res_data['new_msg']    = Common::getUnreadMessages($this->token_info['user_id']);
        $config['modulename']   = $data['modulename'];
        $res_data['config']     = $config;
        $res_data['token_list'] = $this->token_list;
        $this->result($res_data, 1, 'ok', 'json');
    }

    public function upload()
    {
        $file = $this->request->file('file');
        if (empty($file)) {
            $this->result(null, 0, '没有文件被上传或上传超过限制', 'json');
        }

        //判断是否已经存在附件
        $sha1     = $file->hash();
        $extparam = $this->request->post();

        $upload = Config::get('upload');

        preg_match('/(\d+)(\w+)/', $upload['maxsize'], $matches);
        $type     = strtolower($matches[2]);
        $typeDict = ['b' => 0, 'k' => 1, 'kb' => 1, 'm' => 2, 'mb' => 2, 'gb' => 3, 'g' => 3];
        $size     = (int)$upload['maxsize'] * pow(1024, isset($typeDict[$type]) ? $typeDict[$type] : 0);
        $fileInfo = $file->getInfo();
        $suffix   = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $suffix   = $suffix ? $suffix : 'file';

        $mimetypeArr = explode(',', strtolower($upload['mimetype']));
        $typeArr     = explode('/', $fileInfo['type']);

        //禁止上传PHP和HTML文件
        if (in_array($fileInfo['type'], ['text/x-php', 'text/html']) || in_array($suffix, ['php', 'html', 'htm'])) {
            $this->error(__('上传格式限制'));
        }

        //验证文件后缀
        if ($upload['mimetype'] !== '*' &&
            (
                !in_array($suffix, $mimetypeArr)
                || (stripos($typeArr[0] . '/', $upload['mimetype']) !== false && (!in_array($fileInfo['type'], $mimetypeArr) && !in_array($typeArr[0] . '/*', $mimetypeArr)))
            )
        ) {
            $this->error(__('上传格式限制'));
        }

        //验证是否为图片文件
        $imagewidth = $imageheight = 0;
        if (in_array($fileInfo['type'], ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']) || in_array($suffix, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp'])) {
            $imgInfo = getimagesize($fileInfo['tmp_name']);
            if (!$imgInfo || !isset($imgInfo[0]) || !isset($imgInfo[1])) {
                $this->error(__('上传的文件不是图片'));
            }
            $imagewidth  = isset($imgInfo[0]) ? $imgInfo[0] : $imagewidth;
            $imageheight = isset($imgInfo[1]) ? $imgInfo[1] : $imageheight;
        }

        $replaceArr = [
            '{year}'     => date("Y"),
            '{mon}'      => date("m"),
            '{day}'      => date("d"),
            '{hour}'     => date("H"),
            '{min}'      => date("i"),
            '{sec}'      => date("s"),
            '{random}'   => Random::alnum(16),
            '{random32}' => Random::alnum(32),
            '{filename}' => $suffix ? substr($fileInfo['name'], 0, strripos($fileInfo['name'], '.')) : $fileInfo['name'],
            '{suffix}'   => $suffix,
            '{.suffix}'  => $suffix ? '.' . $suffix : '',
            '{filemd5}'  => md5_file($fileInfo['tmp_name']),
        ];
        $savekey    = $upload['savekey'];
        $savekey    = str_replace(array_keys($replaceArr), array_values($replaceArr), $savekey);

        $uploadDir = substr($savekey, 0, strripos($savekey, '/') + 1);
        $fileName  = substr($savekey, strripos($savekey, '/') + 1);

        $splInfo = $file->validate(['size' => $size])->move(ROOT_PATH . '/public' . $uploadDir, $fileName);
        if ($splInfo) {
            $admin_id = 0;
            $user_id  = 0;

            if ($this->token_info) {
                $user_info = Common::userInfo($this->token_info['user_id']);

                if ($user_info['session_type'] == 0) {
                    $user_id = $user_info['id'];
                } else if ($user_info['session_type'] == 1) {
                    $admin_id = $user_info['id'];
                }
            }

            $params     = array(
                'admin_id'    => $admin_id,
                'user_id'     => $user_id,
                'filesize'    => $fileInfo['size'],
                'imagewidth'  => $imagewidth,
                'imageheight' => $imageheight,
                'imagetype'   => $suffix,
                'imageframes' => 0,
                'mimetype'    => $fileInfo['type'],
                'url'         => $uploadDir . $splInfo->getSaveName(),
                'uploadtime'  => time(),
                'storage'     => 'local',
                'sha1'        => $sha1,
                'extparam'    => json_encode($extparam),
            );
            $attachment = model("common/attachment");
            $attachment->data(array_filter($params));
            $attachment->save();
            \think\Hook::listen("upload_after", $attachment);
            $this->result(['url' => cdnurl($uploadDir . $splInfo->getSaveName(), true)], 1, null, 'json');
        } else {
            // 上传失败获取错误信息
            $this->result(null, 1, $file->getError(), 'json');
        }
    }
}
