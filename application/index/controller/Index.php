<?php

namespace app\index\controller;

use app\common\controller\Frontend;

class Index extends Frontend
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';
    protected $layout = '';

    public function index()
    {
        $footpage = \addons\cms\model\Page::all(['type'=>'底部栏目']);
        $footba = \addons\cms\model\Page::all(['type'=>'底部备案信息']);
        $this->view->assign('footlist', $footpage);
        $this->view->assign('footba', $footba);
        //引入CMS区块用于设置首页轮播图
        $this->view->engine->config('taglib_pre_load', 'addons\cms\taglib\Cms');
        return $this->view->fetch();
    }
    public function proserver()
    {
        $drop = $this->request->param('drop');
        $footpage = \addons\cms\model\Page::all(['type'=>'底部栏目']);
        $footba = \addons\cms\model\Page::all(['type'=>'底部备案信息']);
        $this->view->assign('footba', $footba);
        $this->view->assign('footlist', $footpage);
        $this->view->assign('drops', $drop);
        $this->view->engine->config('taglib_pre_load', 'addons\cms\taglib\Cms');
        return $this->view->fetch();
    }
    public function  aboutus()
    {
        $page = \addons\cms\model\Page::all(['type'=>'关于我们_公司介绍']);
        $footpage = \addons\cms\model\Page::all(['type'=>'底部栏目']);
        $footba = \addons\cms\model\Page::all(['type'=>'底部备案信息']);
        $this->view->assign('footba', $footba);
        $this->view->assign('footlist', $footpage);
        $this->view->assign('abous', $page);
        $this->view->engine->config('taglib_pre_load', 'addons\cms\taglib\Cms');
        return $this->view->fetch();
    }
    public function  joinus(){
        $page = \addons\cms\model\Page::all(['type'=>'招聘岗位']);
        $footpage = \addons\cms\model\Page::all(['type'=>'底部栏目']);
        $footba = \addons\cms\model\Page::all(['type'=>'底部备案信息']);
        $this->view->assign('footba', $footba);
        $this->view->assign('footlist', $footpage);
        $this->view->assign('takejobList', $page);
        return $this->view->fetch();
    }
}
