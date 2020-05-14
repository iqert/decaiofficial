<?php

namespace addons\kefu;

use app\common\library\Menu;
use think\Addons;
use think\Db;

/**
 * 插件
 */
class Kefu extends Addons
{

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        // 创建菜单
        $menu = [
            [
                'name'    => 'kefu',
                'title'   => '客服管理',
                'icon'    => 'fa fa-comment',
                'sublist' => [
                    [
                        'name'    => 'kefu/config',
                        'title'   => '客服配置',
                        'icon'    => 'fa fa-circle-o',
                        'sublist' => [
                            ['name' => 'kefu/config/index', 'title' => '客服配置']
                        ]
                    ],
                    [
                        'name'    => 'kefu/user',
                        'title'   => '用户管理',
                        'icon'    => 'fa fa-circle-o',
                        'sublist' => [
                            ['name' => 'kefu/user/index', 'title' => '查看'],
                            ['name' => 'kefu/user/edit', 'title' => '编辑'],
                            ['name' => 'kefu/user/del', 'title' => '删除'],
                        ]
                    ],
                    [
                        'name'    => 'kefu/session',
                        'title'   => '会话管理',
                        'icon'    => 'fa fa-circle-o',
                        'sublist' => [
                            ['name' => 'kefu/session/index', 'title' => '查看'],
                            ['name' => 'kefu/session/del', 'title' => '删除'],
                            ['name' => 'kefu/session/recyclebin', 'title' => '回收站'],
                            ['name' => 'kefu/session/destroy', 'title' => '真实删除'],
                            ['name' => 'kefu/session/restore', 'title' => '还原'],
                        ]
                    ],
                    [
                        'name'    => 'kefu/kbs',
                        'title'   => '知识库管理',
                        'icon'    => 'fa fa-circle-o',
                        'sublist' => [
                            ['name' => 'kefu/kbs/index', 'title' => '查看'],
                            ['name' => 'kefu/kbs/add', 'title' => '增加'],
                            ['name' => 'kefu/kbs/edit', 'title' => '编辑'],
                            ['name' => 'kefu/kbs/del', 'title' => '删除'],
                            ['name' => 'kefu/kbs/multi', 'title' => '批量更新'],
                            ['name' => 'kefu/kbs/recyclebin', 'title' => '回收站'],
                            ['name' => 'kefu/kbs/destroy', 'title' => '真实删除'],
                            ['name' => 'kefu/kbs/restore', 'title' => '还原'],
                        ]
                    ],
                    [
                        'name'    => 'kefu/csrkpi',
                        'title'   => '客服代表管理',
                        'icon'    => 'fa fa-circle-o',
                        'sublist' => [
                            ['name' => 'kefu/csrkpi/index', 'title' => '查看'],
                            ['name' => 'kefu/csrkpi/add', 'title' => '添加'],
                            ['name' => 'kefu/csrkpi/edit', 'title' => '编辑'],
                            ['name' => 'kefu/csrkpi/del', 'title' => '删除'],
                            ['name' => 'kefu/csrkpi/multi', 'title' => '批量更新']
                        ]
                    ],
                    [
                        'name'    => 'kefu/leavemessage',
                        'title'   => '用户留言管理',
                        'icon'    => 'fa fa-circle-o',
                        'sublist' => [
                            ['name' => 'kefu/leavemessage/index', 'title' => '查看'],
                            ['name' => 'kefu/leavemessage/edit', 'title' => '编辑'],
                            ['name' => 'kefu/leavemessage/del', 'title' => '删除']
                        ]
                    ],
                    [
                        'name'    => 'kefu/record',
                        'title'   => '聊天记录汇总',
                        'icon'    => 'fa fa-circle-o',
                        'sublist' => [
                            ['name' => 'kefu/record/index', 'title' => '查看'],
                            ['name' => 'kefu/record/edit', 'title' => '编辑'],
                            ['name' => 'kefu/record/del', 'title' => '删除'],
                            ['name' => 'kefu/record/multi', 'title' => '批量更新']
                        ]
                    ],
                    [
                        'name'    => 'kefu/fastreply',
                        'title'   => '快捷回复管理',
                        'icon'    => 'fa fa-circle-o',
                        'sublist' => [
                            ['name' => 'kefu/fastreply/index', 'title' => '查看'],
                            ['name' => 'kefu/fastreply/add', 'title' => '增加'],
                            ['name' => 'kefu/fastreply/edit', 'title' => '编辑'],
                            ['name' => 'kefu/fastreply/del', 'title' => '删除'],
                            ['name' => 'kefu/fastreply/multi', 'title' => '批量更新'],
                            ['name' => 'kefu/fastreply/recyclebin', 'title' => '回收站'],
                            ['name' => 'kefu/fastreply/destroy', 'title' => '真实删除'],
                            ['name' => 'kefu/fastreply/restore', 'title' => '还原'],
                        ]
                    ],
                    [
                        'name'    => 'kefu/blacklist',
                        'title'   => '用户黑名单管理',
                        'icon'    => 'fa fa-circle-o',
                        'sublist' => [
                            ['name' => 'kefu/blacklist/index', 'title' => '查看'],
                            ['name' => 'kefu/blacklist/add', 'title' => '增加'],
                            ['name' => 'kefu/blacklist/edit', 'title' => '编辑'],
                            ['name' => 'kefu/blacklist/del', 'title' => '删除'],
                            ['name' => 'kefu/blacklist/recyclebin', 'title' => '回收站'],
                            ['name' => 'kefu/blacklist/destroy', 'title' => '真实删除'],
                            ['name' => 'kefu/blacklist/restore', 'title' => '还原'],
                        ]
                    ],
                ]
            ]
        ];
        Menu::create($menu);
        return true;
    }

    /**
     * 插件更新方法
     * @return bool
     */
    public function upgrade()
    {
        // v1.0.2 审查聊天记录
        if (!\think\Db::name('auth_rule')->where('name', 'kefu/record/sessionRecord')->value('id')) {
            $menu = [
                ['name' => 'kefu/record/sessionRecord', 'title' => '审查聊天记录']
            ];
            Menu::create($menu, 'kefu/record');
        }

        // v1.0.3 知识库
        if (!\think\Db::name('auth_rule')->where('name', 'kefu/kbs')->value('id')) {
            $menu = [
                [
                    'name'    => 'kefu/kbs',
                    'title'   => '知识库管理',
                    'icon'    => 'fa fa-circle-o',
                    'sublist' => [
                        ['name' => 'kefu/kbs/index', 'title' => '查看'],
                        ['name' => 'kefu/kbs/add', 'title' => '增加'],
                        ['name' => 'kefu/kbs/edit', 'title' => '编辑'],
                        ['name' => 'kefu/kbs/del', 'title' => '删除'],
                        ['name' => 'kefu/kbs/multi', 'title' => '批量更新'],
                        ['name' => 'kefu/kbs/recyclebin', 'title' => '回收站'],
                        ['name' => 'kefu/kbs/destroy', 'title' => '真实删除'],
                        ['name' => 'kefu/kbs/restore', 'title' => '还原'],
                    ]
                ]
            ];
            Menu::create($menu, 'kefu');
        }

        // v1.0.3 客服代表管理
        $kefu_csrkpi_menu = Db::name('auth_rule')->where('name', 'kefu/csrkpi')->find();
        if ($kefu_csrkpi_menu['title'] == '客服绩效报表') {
            Db::name('auth_rule')->where('name', 'kefu/csrkpi')->update(['title' => '客服代表管理']);
        }
        if (!\think\Db::name('auth_rule')->where('name', 'kefu/csrkpi/add')->value('id')) {
            $menu = [
                ['name' => 'kefu/csrkpi/add', 'title' => '添加'],
                ['name' => 'kefu/csrkpi/edit', 'title' => '编辑'],
                ['name' => 'kefu/csrkpi/del', 'title' => '删除'],
            ];
            Menu::create($menu, 'kefu/csrkpi');
        }
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        Menu::delete('kefu');
        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enable()
    {
        $this->upgrade();
        Menu::enable('kefu');
        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disable()
    {
        Menu::disable('kefu');
        return true;
    }

    /**
     * 增加命令
     */
    public function appInit($param)
    {
        if (request()->isCli()) {
            \think\Console::addDefaultCommands([
                'addons\kefu\library\GatewayWorker\start'
            ]);
        }
    }

}
