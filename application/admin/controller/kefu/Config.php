<?php

namespace app\admin\controller\kefu;

use app\common\controller\Backend;
use think\Db;

/*
 * KeFu配置
 */

class Config extends Backend
{

    public function _initialize()
    {
        parent::_initialize();
    }

    /*
     * 查看
     */
    public function index()
    {
        $config     = Db::name('kefu_config')->column('name,value');
        $csr_config = Db::name('kefu_csr_config')
            ->alias('c')
            ->field('c.id,c.admin_id,c.ceiling,a.username')
            ->join('admin a', 'c.admin_id=a.id')
            ->select();

        $this->view->assign('config_list', $config);
        $this->view->assign('csr_config', $csr_config);
        return $this->view->fetch();
    }

    /*
     * 保存修改
     */
    public function update()
    {
        if ($this->request->isPost()) {
            $config               = Db::name('kefu_config')->column('name,value');
            $csr_config           = Db::name('kefu_csr_config')
                ->alias('c')
                ->field('c.id,c.admin_id,c.ceiling,a.username')
                ->join('admin a', 'c.admin_id=a.id')
                ->select();
            $csr_config_admin_ids = array_column($csr_config, 'admin_id');

            $params = $this->request->post("row/a");

            if ($params) {
                // 保存客服代表配置
                /*if (isset($params['csr_config']) && is_array($params['csr_config'])) {

                    foreach ($params['csr_config'] as $username => $ceiling) {

                        $ceiling  = $ceiling ? $ceiling : 1;
                        $admin_id = Db::name('admin')->where('username', $username)->value('id');
                        if (in_array($admin_id, $csr_config_admin_ids)) {
                            Db::name('kefu_csr_config')->where('admin_id', $admin_id)->update(['ceiling' => $ceiling]);
                        } else {
                            $insert_csr_config[] = [
                                'admin_id' => $admin_id,
                                'ceiling'  => $ceiling
                            ];
                        }
                    }

                    if (isset($insert_csr_config)) {
                        Db::name('kefu_csr_config')->insertAll($insert_csr_config);
                    }

                    // 删除多余的客服代表
                    foreach ($csr_config as $key => $value) {
                        if (!array_key_exists($value['username'], $params['csr_config'])) {
                            Db::name('kefu_csr_config')->where('admin_id', $value['admin_id'])->delete();
                        }
                    }
                } else {
                    // 删除所有客服代表
                    Db::name('kefu_csr_config')->where('admin_id', '>', 0)->delete();
                }

                unset($params['csr_config']);*/

                // 配置更新-只更新有修改的
                foreach ($params as $key => $value) {

                    if ($params[$key] != $config[$key]) {
                        Db::name('kefu_config')->where('name', $key)->update(['value' => $value]);
                    }
                }

                $this->success();
            }

            $this->error();
        }
        return;
    }
}