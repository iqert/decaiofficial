<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:91:"D:\phpstudy_pro\WWW\www.fastlocal.com\public/../application/index\view\index\proserver.html";i:1589435554;}*/ ?>
<!DOCTYPE html>
<html>

    <head>

        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="">
        <meta name="author" content="">

        <title><?php echo $site['name']; ?></title>
        <link rel="stylesheet" href="/assets/index/css/main.css">
        <link href="https://fonts.googleapis.com/css?family=Roboto:300,300i,400,400i,500,500i,700,700i" rel="stylesheet">
        <link rel="stylesheet" href="/assets/index/css/owl.carousel.css">
        <link rel="stylesheet" href="/assets/index/css/barfiller.css">
        <link rel="stylesheet" href="/assets/index/css/animate.css">
        <link rel="stylesheet" href="https://www.jq22.com/jquery/font-awesome.4.7.0.css">
        <link rel="stylesheet" href="/assets/index/css/slicknav.css">
       <!-- <link rel="stylesheet" href="https://www.jq22.com/jquery/bootstrap-4.2.1.css">-->
        <!-- Bootstrap Core CSS -->
        <!--<link href="https://cdn.staticfile.org/twitter-bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">-->
        <!--<link href="/assets/css/index.css" rel="stylesheet">
-->
        <!-- Plugin CSS -->
        <link href="https://cdn.staticfile.org/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
        <link href="https://cdn.staticfile.org/simple-line-icons/2.4.1/css/simple-line-icons.min.css" rel="stylesheet">

        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!--[if lt IE 9]>
            <script src="https://cdn.staticfile.org/html5shiv/3.7.3/html5shiv.min.js"></script>
            <script src="https://cdn.staticfile.org/respond.js/1.4.2/respond.min.js"></script>
        <![endif]-->
        <!--csdn-->
        <link rel="stylesheet" href="https://cdn.staticfile.org/twitter-bootstrap/3.3.7/css/bootstrap.min.css">
        <!--<script src="https://cdn.staticfile.org/jquery/2.1.1/jquery.min.js"></script>-->
        <!--客服-->
        <script src="https://cdn.staticfile.org/layer/2.3/layer.js"></script>
        <script src="/assets/addons/kefu/js/kefu.js"></script>
        <link href="/assets/addons/kefu/css/kefu_default.css" rel="stylesheet">
    </head>

    <body id="page-top">

    <nav id="mainNav" class="navbar navbar-default navbar-fixed-top">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse-menu">
                    <span class="sr-only">Toggle navigation</span><i class="fa fa-bars"></i>
                </button>
                <a class="navbar-brand page-scroll" style="color:black" href="#page-top"><?php echo $site['name']; ?></a>
            </div>

            <div class="collapse navbar-collapse" id="navbar-collapse-menu">
                <ul class="nav navbar-nav navbar-right" style="color:black">
                    <li><a href="<?php echo url('/'); ?>" target="_self"><?php echo __('Home'); ?></a></li>
                    <li><a href="<?php echo url('index/index/proserver'); ?>" target="_self"><?php echo __('ProServer'); ?></a></li>
                    <li><a href="<?php echo url('index/index/aboutus'); ?>" target="_self"><?php echo __('aboutUs'); ?></a></li>
                </ul>
            </div>
        </div>
    </nav>

        <div class="hero-slider">
            <?php $__g0H718nQb3__ = \addons\cms\model\Block::getBlockList(["id"=>"block","name"=>"proCycle","row"=>"5","orderby"=>"weigh","orderway"=>"asc"]); if(is_array($__g0H718nQb3__) || $__g0H718nQb3__ instanceof \think\Collection || $__g0H718nQb3__ instanceof \think\Paginator): $i = 0; $__LIST__ = $__g0H718nQb3__;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$block): $mod = ($i % 2 );++$i;?>
                <div class="single-slide" style="background-image: url(<?php echo $block['image']; ?>)">
                    <div class="inner">
                        <div class="container" style="width: 500px">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="slide-content">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; else: echo "" ;endif; $__LASTLIST__=$__g0H718nQb3__; ?>
        </div>
        <div class="tabs-area spb">
            <div class="container">
                <ul class="nav tabs-nav nav-justified flex-column" id="pills-tab" role="tablist">
                    <?php $__nvJ0DN4aqG__ = \addons\cms\model\Block::getBlockList(["id"=>"block","name"=>"proServer","row"=>"6","orderby"=>"weigh","orderway"=>"asc"]); if(is_array($__nvJ0DN4aqG__) || $__nvJ0DN4aqG__ instanceof \think\Collection || $__nvJ0DN4aqG__ instanceof \think\Paginator): $i = 0; $__LIST__ = $__nvJ0DN4aqG__;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$block): $mod = ($i % 2 );++$i;?>
                        <li class="nav-item">
                            <a class="nav-link <?php if($drops==$i): ?> active <?php endif; ?>" id="pills-home-tab" data-toggle="tab" href="#mission_<?php echo $i; ?>">
                                <i class="fa <?php if($i==1): ?> fa-puzzle-piece <?php else: ?>fa-binoculars<?php endif; ?>"></i>
                                <span><?php echo $block['title']; ?></span>
                            </a>
                        </li>
                    <?php endforeach; endif; else: echo "" ;endif; $__LASTLIST__=$__nvJ0DN4aqG__; ?>

                </ul>
                <div class="tab-content" id="pills-tabContent">
                    <?php $__sSxzqbYI3L__ = \addons\cms\model\Block::getBlockList(["id"=>"block","name"=>"proServer","row"=>"6","orderby"=>"weigh","orderway"=>"asc"]); if(is_array($__sSxzqbYI3L__) || $__sSxzqbYI3L__ instanceof \think\Collection || $__sSxzqbYI3L__ instanceof \think\Paginator): $i = 0; $__LIST__ = $__sSxzqbYI3L__;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$block): $mod = ($i % 2 );++$i;?>
                        <div class="tab-pane fade  in <?php if($drops==$i): ?> active show <?php endif; ?>" id="mission_<?php echo $i; ?>" role="tabpanel">
                            <div class="row">
                                <div class="col-md-12">
                                    <?php echo $block['content']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; endif; else: echo "" ;endif; $__LASTLIST__=$__sSxzqbYI3L__; ?>

                </div>

            </div>
        </div>

        <!--图文区域-->


        <!-- 底部-->
        <footer>
            <div class="footer-top">
                <div class="container">
                    <div class="row">
                        <?php if(is_array($footlist) || $footlist instanceof \think\Collection || $footlist instanceof \think\Paginator): if( count($footlist)==0 ) : echo "" ;else: foreach($footlist as $key=>$foots): ?>
                        <div class="col-md-6 col-lg-3 footer_widget">
                            <div class="inner">
                                <h4><?php echo $foots['title']; ?></h4>
                                <?php echo $foots['content']; ?>
                            </div>
                        </div>
                        <?php endforeach; endif; else: echo "" ;endif; ?>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="container">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="copyright-txt">
                                <?php if(is_array($footba) || $footba instanceof \think\Collection || $footba instanceof \think\Paginator): if( count($footba)==0 ) : echo "" ;else: foreach($footba as $key=>$foots): ?>
                                <?php echo $foots['content']; endforeach; endif; else: echo "" ;endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
                <!-- jQuery -->
      <!--  <script src=https://cdn.staticfile.org/jquery/2.1.4/jquery.min.js></script>-->
        <script src="https://www.jq22.com/jquery/jquery-1.10.2.js"></script>
        <!-- Bootstrap Core JavaScript -->
        <!--<script src="https://cdn.staticfile.org/twitter-bootstrap/3.3.7/js/bootstrap.min.js"></script>-->
        <script src="/assets/index/js/vendor/popper.min.js"></script>
        <script src="/assets/index/js/vendor/bootstrap.min.js"></script>
        <script src="/assets/index/js/vendor/owl.carousel.min.js"></script>
        <script src="/assets/index/js/vendor/isotope.pkgd.min.js"></script>
        <script src="/assets/index/js/vendor/jquery.barfiller.js"></script>
        <script src="/assets/index/js/vendor/loopcounter.js"></script>
        <script src="/assets/index/js/vendor/slicknav.min.js"></script>
        <script src="/assets/index/js/active.js"></script>

    </body>
    <script>
        $(function () {
            var arr='<?php echo $drops ?>';

            // 参数一为在线客服所在网站的域名(启动Workerman服务的网站的域名)
            // 参数二为模块名,站外直接填写index
            // 参数三为初始化完成后的回调方法
            // 参数四为指定客服，可在此处填写客服代表的后台账户id
            // 若要站外调用，请于后台-》插件管理-》本插件的配置中-》跨站调用允许域名-》填写外站的域名
            KeFu.initialize(document.domain, 'index');
            $('#pills-tab a').click(function (e) {
                e.preventDefault();
                $(this).tab('show');
            })
        })


    </script>
</html>
