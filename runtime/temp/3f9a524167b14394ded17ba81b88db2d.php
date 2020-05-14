<?php if (!defined('THINK_PATH')) exit(); /*a:1:{s:80:"D:\phpstudy_pro\WWW\www.fastlocal.com\public/assets/addons/kefu/tpl/default.html";i:1589418076;}*/ ?>
<!-- KeFu-Template -->
<ins class="KeFu">

    <!-- 右侧悬浮按钮 -->
    <div class="kefu_button" id="kefu_button" data-html="true" data-container="body" data-toggle="popover" data-placement="left" data-content=""></div>
    <!-- 右侧悬浮按钮-end -->

    <!-- 聊天窗口 -->

    <div class="modal fade bs-example-modal-lg" id="KeFuModal" tabindex="-1" role="dialog" aria-labelledby="KeFuModal">
	  <div class="modal-dialog modal-lg" role="document">
	    <div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
				<h4 class="modal-title">
					<span id="modal-title"></span>
					<span id="csr_status"></span>
					<span id="kefu_error">链接中...</span>
				</h4>
			</div>
			<div class="modal-body">
				<div class="kefu-left">
					<div class="alert alert-warning-light announcement">
    					<i class="fa fa-bell-o"></i><span id="announcement"></span>
					</div>
					<div class="chat">
						<div class="chat_scroll kefu_window_view" id="kefu_scroll"></div>
						<div id="kefu_leave_message" style="display: none;" class="panel panel-success kefu_window_view">
							<div class="panel-heading">非常抱歉，当前无客服在线，您留言后我们将尽快与您联系</div>
							<div class="panel-body">
								<form method="get" action="" class="form-horizontal">
									<div class="form-group">
										<label for="c-name" class="col-sm-2 control-label">姓名</label>
										<div class="col-sm-10">
											<input type="text" name="name" class="form-control" id="c-name" placeholder="请输入您的姓名">
										</div>
									</div>
									<div class="form-group">
										<label for="c-contact" class="col-sm-2 control-label">联系方式</label>
										<div class="col-sm-10">
											<input type="text" name="contact" class="form-control" id="c-contact" placeholder="请输入手机/QQ/微信号">
										</div>
									</div>
									<div class="form-group">
										<label for="c-message" class="col-sm-2 control-label">留言内容</label>
										<div class="col-sm-10">
											<textarea rows="5" name="message" class="form-control" id="c-message" placeholder="遇到的问题、所需服务、产品等，我们将尽快与您取得联系"></textarea>
										</div>
									</div>
									<div class="form-group">
										<div class="col-sm-offset-2 col-sm-10">
											<button type="button" class="btn btn-success">确认留言</button>
										</div>
									</div>
								</form>
							</div>
						</div>
					</div>
					<div class="kefu_emoji">
						<div id="kefu_emoji">
							<?php $__FOR_START_1176416980__=1;$__FOR_END_1176416980__=37;for($i=$__FOR_START_1176416980__;$i < $__FOR_END_1176416980__;$i+=1){ ?>
							<img class="emoji" title="" src="/assets/addons/kefu/img/emoji/<?php echo $i; ?>.png">
							<?php } ?>
						</div>
					</div>
					<div class="write">
						<div class="write_top">
							<i class="smiley"></i>
							<div class="select_file">
								<input id="chatfile" size="1" width="20" type="file" name="chatfile">
								<i class="attach"></i>
							</div>
							<span id="send_tis">按下Enter发送消息</span>
						</div>
						<pre contenteditable="plaintext-only" id="kefu_message"></pre>
					</div>
				</div>
				
				<div class="kefu-right">
					<div id="kefu_chat_slide_f"></div>
					<div class="chat_introduces"></div>
				</div>
			</div>
	    </div>
	  </div>
	</div>

    <!-- 聊天窗口-end -->
</ins>
<!-- KeFu-Template-end -->