<?php

namespace app\admin\controller\kefu;

use app\common\controller\Backend;

/**
 * 客服黑名单管理
 *
 * @icon fa fa-circle-o
 */
class Blacklist extends Backend
{

    /**
     * KeFuBlacklist模型对象
     * @var \app\admin\model\KeFuBlacklist
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\KeFuBlacklist;

    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $total = $this->model
                ->with(['admin', 'kefuuser'])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['admin', 'kefuuser'])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {

                $row->getRelation('admin')->visible(['nickname']);
                if ($row->kefuuser->user_id) {
                    $row->fu_user_nickname = \think\Db::name('user')->where('id', $row->kefuuser->user_id)->value('nickname');
                }
            }
            $list   = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }
}
