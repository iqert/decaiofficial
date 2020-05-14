<?php

/**
 * 对公众平台发送给公众账号的消息加解密代码.
 * 白衣素袖：增加消息处理辅助方法
 * @copyright Copyright (c) 1998-2014 Tencent Inc.
 */

namespace addons\kefu\library\WechatCrypto;

use addons\kefu\library\Common;
use think\Db;

include_once "sha1.php";
include_once "xmlparse.php";
include_once "pkcs7Encoder.php";
include_once "errorCode.php";

/**
 * 1.第三方回复加密消息给公众平台；
 * 2.第三方收到公众平台发送的消息，验证消息的安全性，并对消息进行解密。
 */
class WXBizMsgCrypt
{
    // 微信配置
    private $wechat = [];

    /**
     * 构造函数
     */
    public function __construct()
    {

        $wechat_temp = Db::name('kefu_config')
            ->whereIn('name', 'wechat_app_id,wechat_app_secret,wechat_token,wechat_encodingkey')
            ->select();

        foreach ($wechat_temp as $key => $value) {
            $this->wechat[$value['name']] = $value['value'];
        }
    }

    /**
     * 将公众平台回复用户的消息加密打包.
     * <ol>
     *    <li>对要发送的消息进行AES-CBC加密</li>
     *    <li>生成安全签名</li>
     *    <li>将消息密文和安全签名打包成xml格式</li>
     * </ol>
     *
     * @param $replyMsg string 公众平台待回复用户的消息，xml格式的字符串
     * @param $timeStamp string 时间戳，可以自己生成，也可以用URL参数的timestamp
     * @param $nonce string 随机串，可以自己生成，也可以用URL参数的nonce
     * @param &$encryptMsg string 加密后的可以直接回复用户的密文，包括msg_signature, timestamp, nonce, encrypt的xml格式的字符串,
     *                      当return返回0时有效
     *
     * @return int 成功0，失败返回对应的错误码
     */
    public function encryptMsg($replyMsg, $timeStamp, $nonce, &$encryptMsg)
    {
        $pc = new \Prpcrypt($this->wechat['wechat_encodingkey']);

        //加密
        $array = $pc->encrypt($replyMsg, $this->wechat['wechat_app_id']);
        $ret   = $array[0];
        if ($ret != 0) {
            return $ret;
        }

        if ($timeStamp == null) {
            $timeStamp = time();
        }
        $encrypt = $array[1];

        //生成安全签名
        $sha1  = new \SHA1;
        $array = $sha1->getSHA1($this->wechat['wechat_token'], $timeStamp, $nonce, $encrypt);
        $ret   = $array[0];
        if ($ret != 0) {
            return $ret;
        }
        $signature = $array[1];

        //生成发送的xml
        $xmlparse   = new \XMLParse;
        $encryptMsg = $xmlparse->generate($encrypt, $signature, $timeStamp, $nonce);
        return \ErrorCode::$OK;
    }

    /**
     * 检验消息的真实性，并且获取解密后的明文.
     * <ol>
     *    <li>利用收到的密文生成安全签名，进行签名验证</li>
     *    <li>若验证通过，则提取xml中的加密消息</li>
     *    <li>对消息进行解密</li>
     * </ol>
     *
     * @param $msgSignature string 签名串，对应URL参数的msg_signature
     * @param $timestamp string 时间戳 对应URL参数的timestamp
     * @param $nonce string 随机串，对应URL参数的nonce
     * @param $postData string 密文，对应POST请求的数据
     * @param &$msg string 解密后的原文，当return返回0时有效
     *
     * @return int 成功0，失败返回对应的错误码
     */
    public function decryptMsg($msgSignature, $timestamp = null, $nonce, $encrypt, &$msg)
    {
        if (strlen($this->wechat['wechat_encodingkey']) != 43) {
            return \ErrorCode::$IllegalAesKey;
        }

        $pc = new \Prpcrypt($this->wechat['wechat_encodingkey']);

        if ($timestamp == null) {
            $timestamp = time();
        }

        //验证安全签名
        $sha1  = new \SHA1;
        $array = $sha1->getSHA1($this->wechat['wechat_token'], $timestamp, $nonce, $encrypt);
        $ret   = $array[0];

        if ($ret != 0) {
            return $ret;
        }

        $signature = $array[1];
        if ($signature != $msgSignature) {
            return \ErrorCode::$ValidateSignatureError;
        }

        $result = $pc->decrypt($encrypt, $this->wechat['wechat_app_id']);
        if ($result[0] != 0) {
            return $result[0];
        }
        $msg = $result[1];

        return \ErrorCode::$OK;
    }

    public function saveImg($media_id)
    {

        $access_token = $this->getAccessToken();
        $url          = "https://api.weixin.qq.com/cgi-bin/media/get?access_token=$access_token&media_id=$media_id";
        $arr          = $this->curlFile($url);
        $dlres        = $this->saveFile($arr);
        return $dlres;
    }

    public function getAccessToken()
    {

        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' .
            $this->wechat['wechat_app_id'] . '&secret=' . $this->wechat['wechat_app_secret'];

        $result      = file_get_contents($url);
        $result      = json_decode($result, true);
        $accesstoken = $result['access_token'];
        return $accesstoken;
    }

    public function curlFile($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOBODY, 0); //只取body头
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //curl_exec执行成功后返回执行的结果；不设置的话，curl_exec执行成功则返回true
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    /**
     * 保存图片
     * @param  string $filecontent 图片二进制内容
     * @param  string $save_dir 保存目录
     * @param  string $filename 保存名称
     * @return array               保存位置
     */
    public function saveFile($filecontent, $save_dir = './uploads/', $filename = '')
    {

        $save_dir = $save_dir . date('Ymd') . '/';

        if (!is_dir($save_dir)) {
            if (!mkdir($save_dir, 0777, true)) {
                return array('code' => 0, 'img_url' => '创建图片目录失败！');
            }
        }

        //保存文件名
        if (!$filename || trim($filename) == '') {

            $filename = $save_dir . md5(time() . \fast\Random::alnum(10)) . '.png';
        }

        $local_file = fopen($filename, 'w');
        if (false !== $local_file) {
            if (false !== fwrite($local_file, $filecontent)) {
                fclose($local_file);
            } else {
                return array('code' => 0, 'msg' => '图片保存时写入失败！');
            }
        } else {
            return array('code' => 0, 'msg' => '图片保存失败！');
        }

        return array('code' => 1, 'img_url' => $filename);
    }

    public function userInitialize($open_id)
    {
        $http_origin        = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : false;

        $kefu_user = Db::name('kefu_user')->where('wechat_openid', $open_id)->find();

        if (!$kefu_user) {

            // 建立用户
            $tourists_max_id = Db::name('kefu_user')->max('id');

            $kefu_user = [
                'avatar'        => '', // 随机头像->算了算了
                'nickname'      => '小程序用户 ' . $tourists_max_id,
                'wechat_openid' => $open_id,
                'createtime'    => time(),
            ];

            if (Db::name('kefu_user')->insert($kefu_user)) {
                $kefu_user['id'] = Db::name('kefu_user')->getLastInsID();
            }
        }

        // 查询之前的客服代表
        $session = Db::name('kefu_session')
            ->alias('s')
            ->field('s.*,a.id as admin_id,a.nickname')
            ->join('admin a', 's.csr_id=a.id')
            ->where('s.user_id', $kefu_user['id'])
            ->where('s.deletetime', null)
            ->find();

        // 有客服代表，但客服代表不在线，重新分配
        $is_csr_distribution = false;
        if ($session) {
            $csr_status = Db::name('kefu_csr_config')
                ->where('admin_id', $session['admin_id'])
                ->value('status');

            if ($csr_status != 3) {
                $is_csr_distribution = true;
            }
        }

        if (!$session || $is_csr_distribution) {

            $online_csr = Db::name('kefu_csr_config')->where('status', 3)->value('admin_id');
            if (!$online_csr) {

                $data = [
                    'session' => false,
                    'kefu_user' => $kefu_user,
                    'code'    => 1,
                    'msg'     => '非常抱歉，当前无在线客服，您可以直接在此留言，谢谢您的支持！',
                ];
                return $data;
            }

            // 客服分配
            $csr              = null;
            $csr_distribution = Db::name('kefu_config')->where('name', 'csr_distribution')->value('value');

            if ($csr_distribution == 0) {
                // 拿到当前接待量最少的客服
                $reception_count = Db::name('kefu_csr_config')->where('status', 3)->min('reception_count');

                $csr_list = Db::name('kefu_csr_config')
                    ->where('status', 3)
                    ->where('reception_count', $reception_count)
                    ->select();

                if ($csr_list && count($csr_list) == 1) {
                    $csr = $csr_list[0];
                }
            } else if ($csr_distribution == 1) {
                // 根据接待上限和当前接待量，分配给最能接待的客服

                $csr = Db::name('kefu_csr_config')
                    ->field('id,admin_id,CAST(ceiling as signed) - CAST(reception_count as signed) as weight')
                    ->where('status', 3)
                    ->order('weight desc')
                    ->find();
            }

            if ($csr_distribution == 2 || !$csr) {
                // 分配给最久未进行接待的客服
                $csr = Db::name('kefu_csr_config')
                    ->where('status', 3)
                    ->order('last_reception_time asc')
                    ->find();
            }

            if ($csr) {
                $session = Common::distributionCsr($csr['admin_id'] . '||csr', $kefu_user['id'] . '||user');

                /*$new_user_msg = Db::name('kefu_config')->where('name', 'new_user_msg')->value('value');
                if ($new_user_msg) {
                    $this->sendMessage($open_id, "text", $new_user_msg);
                }*/
            }
        }

        if ($session) {

            // 记录客服接待人数
            Db::name('kefu_csr_config')
                ->where('admin_id', $session['admin_id'])
                ->inc('reception_count')
                ->update([
                    'last_reception_time' => time(),
                ]);

            $data = [
                'session' => $session,
                'code'    => 2,
            ];
            return $data;
        } else {

            $data = [
                'session' => false,
                'code'    => 0,
                'msg'     => '分配客服代表失败！',
            ];
            return $data;
        }

    }

    /*建立用户->分配客服*/

    /**
     * 向微信客服发送消息
     * @param  [type] $fromUsername 接收人
     * @param  [type] $msgType      消息类型text or image
     * @param  [type] $content      消息内容
     * @return [type]               curl执行结果
     */
    public function sendMessage($fromUsername, $msgType, $content)
    {
        $data = array(
            "touser"  => $fromUsername,
            "msgtype" => $msgType,
            "text"    => array("content" => $content),
        );

        $data         = json_encode($data, JSON_UNESCAPED_UNICODE); //兼容php5.4以下json格式处理
        $access_token = $this->getAccessToken();

        /*
         * POST发送https请求客服接口api
         */
        $url  = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $access_token;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($curl, CURLOPT_HTTPHEADER, $headers );
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

}
