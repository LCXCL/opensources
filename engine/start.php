<?php
/**
 * 引导程序
 *
 * 这个文件加载全部的Elgg引擎，检查安装状态，并出发一系列的事件去完成启动：
 * 	- {@elgg_event boot system}
 * 	- {@elgg_event init system}
 * 	- {@elgg_event ready system}
 *
 * 如果Elgg是未安装的，浏览器会重定向到安装页面
 *
 * @see install.php
 * @package Elgg.Core
 * @subpackage Core
 */

/* 没有设置表示是新安装
 */
if (!file_exists(dirname(__FILE__) . '/settings.php')) {
	header("Location: install.php");
	exit;
}

/**
 * 当Egll启动时开始以ms计时
 *
 * @global是float型
 * @global float
 */
global $START_MICROTIME;
$START_MICROTIME = microtime(true);

/**
 * 配置变量
 *
 * $CONFIG 全局变量包含了Elgg运行需要的配置变量，这个变量定义在setting.php文件里
 *
 * 编写插件时调用elgg_get_config()，而不是直接访问这个全局变量
 *
 * @see elgg_get_config()
 * @see engine/settings.php
 * @global stdClass $CONFIG
 */
global $CONFIG;
if (!isset($CONFIG)) {
	$CONFIG = new stdClass;
}
$CONFIG->boot_complete = false;

$lib_dir = dirname(__FILE__) . '/lib/';

// 加载引导程序库
$path = $lib_dir . 'elgglib.php';
if (!include_once($path)) {
	echo "Could not load file '$path'. Please check your Elgg installation for all required files.";
	exit;
}

// 加载系统设置
if (!include_once(dirname(__FILE__) . "/settings.php")) {
	$msg = 'Elgg could not load the settings file. It does not exist or there is a file permissions issue.';
	throw new InstallationException($msg);
}

// 加载其他库文件
$lib_files = array(
	'access.php', 'actions.php', 'admin.php', 'annotations.php', 'cache.php',
	'calendar.php', 'configuration.php', 'cron.php', 'database.php',
	'entities.php', 'export.php', 'extender.php', 'filestore.php', 'group.php',
	'input.php', 'languages.php', 'location.php', 'mb_wrapper.php',
	'memcache.php', 'metadata.php', 'metastrings.php', 'navigation.php',
	'notification.php', 'objects.php', 'opendd.php', 'output.php',
	'pagehandler.php', 'pageowner.php', 'pam.php', 'plugins.php',
	'private_settings.php', 'relationships.php', 'river.php', 'sessions.php',
	'sites.php', 'statistics.php', 'system_log.php', 'tags.php',
	'user_settings.php', 'users.php', 'upgrade.php', 'views.php',
	'web_services.php', 'widgets.php', 'xml.php', 'xml-rpc.php',
	
	// 向后兼容
	'deprecated-1.7.php', 'deprecated-1.8.php',
);

foreach ($lib_files as $file) {
	$file = $lib_dir . $file;
	elgg_log("Loading $file...");
	if (!include_once($file)) {
		$msg = "Could not load $file";
		throw new InstallationException($msg);
	}
}

// 连接数据库，加载语言文件、配置信息，session初始化
// 插件这时还不能用这些事件，因为他们还没有被加载
elgg_trigger_event('boot', 'system');

// 加载活动的插件
elgg_load_plugins();

// 将插件的加载移动到一个单独的启动函数里，代替那里的‘boot’，‘system’
// 使view type生效，这是加载插件后的第一次尝试
$view_type = elgg_get_viewtype();
if (!elgg_is_valid_view_type($view_type)) {
	elgg_set_viewtype('default');
}

// 不建议这样使用，插件可以用‘init’和‘system’事件
elgg_trigger_event('plugins_boot', 'system');

// 完成启动engine和plugins的进程
elgg_trigger_event('init', 'system');

$CONFIG->boot_complete = true;

// 系统加载并准备好
elgg_trigger_event('ready', 'system');
