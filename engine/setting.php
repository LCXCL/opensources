<?php
/**
 * 定义数据库凭证
 *
 * 大部分Elgg的配置信息存在数据库里。这个文件包含了连接数据库的凭证，还有一些
 * 可选的配置值
 *
 * Elgg安装时会将正确的设置置于这个文件里，并将他们重命名为setting.php
 *
 * @todo Turn this into something we handle more automatically.
 * @package Elgg.Core
 * @subpackage Configuration
 */

global $CONFIG;
if (!isset($CONFIG)) {
	$CONFIG = new stdClass;
}

/**
 * 数据库用户名
 *
 * @global string $CONFIG->dbuser
 * @name $CONFIG->dbuser
 */
$CONFIG->dbuser = 'elgguser';

/**
 * 数据库密码
 *
 * @global string $CONFIG->dbpass
 */
$CONFIG->dbpass = 'SYXWm5NUsQnB3K8h';

/**
 * 数据库名
 *
 * @global string $CONFIG->dbname
 */
$CONFIG->dbname = 'elggdb';

/**
 * 数据库主机
 *
 * 大多数的安装都是localhost
 *
 * @global string $CONFIG->dbhost
 */
$CONFIG->dbhost = 'localhost';

/**
 * 数据库前缀
 * The database prefix
 *
 * 前缀将被用到所有的Elgg表中，防止共享数据库时重名
 *
 * @global string $CONFIG->dbprefix
 */
$CONFIG->dbprefix = 'elgg_';

/**
 * @global bool $CONFIG->broken_mta
 */
$CONFIG->broken_mta = FALSE;

/**
 * 禁用数据库查询缓存
 *
 * Elgg把每次查询和查询结果都存在查询缓存中
 * 随使用时间增长，缓存会变得很大，启用缓存设置为TRUE即可
 *
 * @global bool $CONFIG->db_disable_query_cache
 */
$CONFIG->db_disable_query_cache = FALSE;

/**
 * 最小密码长度
 *
 * 注册时验证用户密码使用
 *
 * @global int $CONFIG->min_password_length
 */
$CONFIG->min_password_length = 6;
