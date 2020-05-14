<?php

namespace app\api\controller;

use addons\kefu\library\Common;
use app\common\controller\Api;
use think\Db;

/**
 * KeFu 接口
 */
class Kefu extends Api
{
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['acceptWxMsg'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['acceptWxMsg'];

    protected $wxBizMsg; // 消息加解密和辅助类实例

    /*
     * 接受/处理来自微信的消息
     */
    public function acceptWxMsg()
    {
        $echostr = $this->request->get('echostr');

        if ($echostr) {
            if ($this->checkSignature()) {
                echo $echostr;
                return;
            }
        }

        $data = $this->request->only(['msg_signature', 'timestamp', 'nonce', 'Encrypt']);
        $msg  = '';

        $this->wxBizMsg = new \addons\kefu\library\WechatCrypto\wxBizMsgCrypt();
        $errCode        = $this->wxBizMsg->decryptMsg($data['msg_signature'], $data['timestamp'], $data['nonce'], $data['Encrypt'], $msg);

        if ($errCode == 0) {
            $msg = json_decode($msg, true);

            if (!$msg) {
                \think\Log::record('微信客服消息解析出错，消息内容:' . $msg, 'notice');
                echo "success";
                return false;
            }

            if (!empty($msg['MsgType']) && in_array($msg['MsgType'], array("text", "image"))) {

                if ($msg['MsgType'] == "image") {

                    $dlImg        = $this->wxBizMsg->saveImg($msg['MediaId']); // 保存图片
                    $content      = cdnurl($dlImg['img_url'], true);
                    $message_type = 1;
                } else {

                    $content      = $msg['Content'];
                    $message_type = 0;
                }

                $session = $this->wxBizMsg->userInitialize($msg['FromUserName']);

                if ($session['code'] == 1 || $session['code'] == 2) {

                    if ($session['session']) {
                        // 通知客服新消息
                        $res = Common::socketMessage($session['session']['id'], $content, $message_type, $session['session']['user_id'] . '||user');
                    } else {

                        $user_info = Common::userInfo($session['kefu_user']['id'] . '||user');

                        $last_leave_message_time = Db::name('kefu_leave_message')
                            ->where('user_id', $user_info['id'])
                            ->order('createtime desc')
                            ->value('createtime');

                        if ($last_leave_message_time && ($last_leave_message_time + 20) > time()) {
                            $this->wxBizMsg
                                ->sendMessage($msg['FromUserName'], 'text', '由于当前无客服代表在线，请不要频繁发送消息，感谢您的支持！');
                            return;
                        }

                        $leave_message = [
                            'user_id'    => $user_info['id'],
                            'name'       => $user_info['nickname'],
                            'message'    => $content,
                            'createtime' => time(),
                        ];

                        if (Db::name('kefu_leave_message')->insert($leave_message)) {

                            $leave_message_id = Db::name('kefu_leave_message')->getLastInsID();

                            // 记录轨迹
                            $trajectory = [
                                'user_id'    => $user_info['id'],
                                'csr_id'     => 0,
                                'log_type'   => 6,
                                'note'       => $leave_message_id,
                                'url'        => '',
                                'referrer'   => '',
                                'createtime' => time(),
                            ];

                            Db::name('kefu_trajectory')->insert($trajectory);
                            $this->wxBizMsg->sendMessage($msg['FromUserName'], "text", '留言成功！');
                        }
                    }

                } else if ($session['code'] == 0) {
                    $this->wxBizMsg->sendMessage($msg['FromUserName'], "text", $session['msg']);
                }

            } else {

                $session = $this->wxBizMsg->userInitialize($msg['FromUserName']);

                if ($session['code'] == 1 || $session['code'] == 0) {

                    $this->wxBizMsg->sendMessage($msg['FromUserName'], "text", $session['msg']);
                }
            }
        } else {
            \think\Log::record('微信客服消息解析出错，消息内容 errCode:' . $errCode, 'notice');
        }

        echo "success";
        return;
    }

    /*是否是验证消息*/
    private function checkSignature()
    {
        $wechat_token = Db::name('kefu_config')->where('name', 'wechat_token')->value('value');

        $signature = $this->request->get('signature');
        $timestamp = $this->request->get('timestamp');
        $nonce     = $this->request->get('nonce');

        $tmpArr = array($wechat_token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }
}
