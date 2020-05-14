<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:87:"D:\phpstudy_pro\WWW\www.fastlocal.com\public/../application/index\view\index\index.html";i:1589435617;}*/ ?>
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

        <!-- Bootstrap Core CSS -->
        <link href="https://cdn.staticfile.org/twitter-bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
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
                <!-- /.navbar-collapse -->
            </div>
            <!-- /.container-fluid -->
        </nav>


        <div class="hero-slider">
            <?php $__CvdtUah4mz__ = \addons\cms\model\Block::getBlockList(["id"=>"block","name"=>"indexCycle","row"=>"5","orderby"=>"weigh","orderway"=>"asc"]); if(is_array($__CvdtUah4mz__) || $__CvdtUah4mz__ instanceof \think\Collection || $__CvdtUah4mz__ instanceof \think\Paginator): $i = 0; $__LIST__ = $__CvdtUah4mz__;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$block): $mod = ($i % 2 );++$i;?>
              <div class="single-slide" style="background-image: url(<?php echo $block['image']; ?>)">
                <div class="inner">
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="slide-content">
                                    <h2><?php echo $block['title']; ?></h2>
                                    <p><?php echo $block['content']; ?></p>
                                    <!--<div class="slide-btn">
                                        <a href="#" class="button">了解更多</a>
                                    </div>-->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; else: echo "" ;endif; $__LASTLIST__=$__CvdtUah4mz__; ?>
        </div>
        <!--图文区域-->
        <div class="about-area sp">
            <div class="container">
                <?php $__R3vTYHFWaN__ = \addons\cms\model\Block::getBlockList(["id"=>"block","name"=>"indeximg","row"=>"6","orderby"=>"weigh","orderway"=>"asc"]); if(is_array($__R3vTYHFWaN__) || $__R3vTYHFWaN__ instanceof \think\Collection || $__R3vTYHFWaN__ instanceof \think\Paginator): $i = 0; $__LIST__ = $__R3vTYHFWaN__;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$block): $mod = ($i % 2 );++$i;if($i%2==0): ?>
                    <div class="row" style="margin-top: 50px">
                        <div class="col-md-6">
                            <div class="about-content">
                                <h3><?php echo $block['title']; ?></h3>
                                <p> <?php echo $block['content']; ?></p>
                                <a href="<?php echo url('index/index/proserver',['drop'=>$i]); ?>" target="_blank" class="button">了解更多</a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="about-img">
                                <img src="<?php echo $block['image']; ?>" alt="">
                            </div>
                        </div>
                    </div>
                <?php endif; if($i%2==1): ?>
                    <div class="row" style="margin-top: 50px">
                        <div class="col-md-6">
                            <div class="about-img">
                                <img src="<?php echo $block['image']; ?>" alt="">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="about-content">
                                <h3><?php echo $block['title']; ?></h3>
                                 <p> <?php echo $block['content']; ?></p>
                                <a href="<?php echo url('index/index/proserver',['drop'=>$i]); ?>" class="button">了解更多</a>
                                <!--<?php if($i==1): ?>
                                <a href="#" class="button">得才实训平台</a>
                                <?php endif; ?>-->
                            </div>
                        </div>

                    </div>
                <?php endif; endforeach; endif; else: echo "" ;endif; $__LASTLIST__=$__R3vTYHFWaN__; ?>
        </div>
            <div class="portfolio-area sp">
                <div class="container">
                    <div class="section-title">
                        <h3>他们都在和我们合作</h3>
                    </div>
                    <div class="row">
                        <?php $__bzJ2oaviLx__ = \addons\cms\model\Block::getBlockList(["id"=>"block","name"=>"cooperation","row"=>"6","orderby"=>"weigh","orderway"=>"asc"]); if(is_array($__bzJ2oaviLx__) || $__bzJ2oaviLx__ instanceof \think\Collection || $__bzJ2oaviLx__ instanceof \think\Paginator): $i = 0; $__LIST__ = $__bzJ2oaviLx__;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$block): $mod = ($i % 2 );++$i;?>
                          <div class="single-portfolio col-md-2">
                            <div class="inner">
                                <div class="portfolio-img" >
                                    <img src="<?php echo $block['image']; ?>" style="width:150px;height:150px" alt="portfolio-image">
                                    <?php echo $block['title']; ?>
                                    <div class="hover-content">
                                        <div>
                                            <?php echo $block['title']; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; endif; else: echo "" ;endif; $__LASTLIST__=$__bzJ2oaviLx__; ?>
                    </div>
                    <div class="row">
                        <div class="col-12 text-center" data-margin="40px 0 0">
                            <a href="#" class="button">了解更多</a>
                        </div>
                    </div>
                </div>
            </div>
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
        <script src=https://cdn.staticfile.org/jquery/2.1.4/jquery.min.js></script>

        <!-- Bootstrap Core JavaScript -->
        <script src="https://cdn.staticfile.org/twitter-bootstrap/3.3.7/js/bootstrap.min.js"></script>
        <script src="/assets/index/js/vendor/popper.min.js"></script>
        <script src="/assets/index/js/vendor/owl.carousel.min.js"></script>
        <script src="/assets/index/js/vendor/isotope.pkgd.min.js"></script>
        <script src="/assets/index/js/vendor/jquery.barfiller.js"></script>
        <script src="/assets/index/js/vendor/loopcounter.js"></script>
        <script src="/assets/index/js/vendor/slicknav.min.js"></script>
        <script src="/assets/index/js/active.js"></script>
        <script type="text/javascript">
            KeFu.initialize(document.domain, 'index');
            // 参数一为在线客服所在网站的域名(启动Workerman服务的网站的域名)
            // 参数二为模块名,站外直接填写index
            // 参数三为初始化完成后的回调方法
            // 参数四为指定客服，可在此处填写客服代表的后台账户id
            // 若要站外调用，请于后台-》插件管理-》本插件的配置中-》跨站调用允许域名-》填写外站的域名
        </script>
    </body>

</html>
