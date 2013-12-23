<?php
/**
 * Blogs
 *
 * @package Blog
 *
 * @todo
 * - Either drop support for "publish date" or duplicate more entity getter
 * functions to work with a non-standard time_created.
 * - Pingbacks
 * - Notifications
 * - River entry for posts saved as drafts and later published
 */

elgg_register_event_handler('init', 'system', 'blog_init');

/**
 * 初始化博客插件
 */
function blog_init() {

	elgg_register_library('elgg:blog', elgg_get_plugins_path() . 'blog/lib/blog.php');

	// 添加站点导航item
	$item = new ElggMenuItem('blog', elgg_echo('blog:blogs'), 'blog/all');
	elgg_register_menu_item('site', $item);

	elgg_register_event_handler('upgrade', 'upgrade', 'blog_run_upgrades');

	// 添加到主css
	elgg_extend_view('css/elgg', 'blog/css');

	// 注册博客的JavaScript
	$blog_js = elgg_get_simplecache_url('js', 'blog/save_draft');
	elgg_register_simplecache_view('js/blog/save_draft');
	elgg_register_js('elgg.blog', $blog_js);

	// url路由选择
	elgg_register_page_handler('blog', 'blog_page_handler');

	// 重写观察博客对象的默认url
	elgg_register_entity_url_handler('object', 'blog', 'blog_url_handler');

	// 单独对每一个事件注册
	elgg_register_event_handler('publish', 'object', 'object_notifications');
	elgg_register_plugin_hook_handler('notify:entity:message', 'object', 'blog_notify_message');

	// 添加博客链接
	elgg_register_plugin_hook_handler('register', 'menu:owner_block', 'blog_owner_block_menu');

	// 搜索的注册
	elgg_register_entity_type('object', 'blog');

	// 添加“群组”选项
	add_group_tool_option('blog', elgg_echo('blog:enableblog'), true);
	elgg_extend_view('groups/tool_latest', 'blog/group_module');

	// 添加一个博客控件
	elgg_register_widget_type('blog', elgg_echo('blog'), elgg_echo('blog:widget:description'));

	// 注册器的动作
	$action_path = elgg_get_plugins_path() . 'blog/actions/blog';
	elgg_register_action('blog/save', "$action_path/save.php");
	elgg_register_action('blog/auto_save_revision', "$action_path/auto_save_revision.php");
	elgg_register_action('blog/delete', "$action_path/delete.php");

	// 实体菜单
	elgg_register_plugin_hook_handler('register', 'menu:entity', 'blog_entity_menu_setup');

	// ECML
	elgg_register_plugin_hook_handler('get_views', 'ecml', 'blog_ecml_views_hook');
}

/**
 * 调度博客页
 * URLs take the form of
 *  All blogs:       blog/all
 *  User's blogs:    blog/owner/<username>
 *  Friends' blog:   blog/friends/<username>
 *  User's archives: blog/archives/<username>/<time_start>/<time_stop>
 *  Blog post:       blog/view/<guid>/<title>
 *  New post:        blog/add/<guid>
 *  Edit post:       blog/edit/<guid>/<revision>
 *  Preview post:    blog/preview/<guid>
 *  Group blog:      blog/group/<guid>/all
 *
 * @todo no archives for all blogs or friends
 *
 * @param array $page
 * @return bool
 */
function blog_page_handler($page) {

	elgg_load_library('elgg:blog');

	// 将博客页转发到正确的url
	blog_url_forwarder($page);

	// 所有博客push进面包屑
	elgg_push_breadcrumb(elgg_echo('blog:blogs'), "blog/all");

	if (!isset($page[0])) {
		$page[0] = 'all';
	}

	$page_type = $page[0];
	switch ($page_type) {
		case 'owner':
			$user = get_user_by_username($page[1]);
			$params = blog_get_page_content_list($user->guid);
			break;
		case 'friends':
			$user = get_user_by_username($page[1]);
			$params = blog_get_page_content_friends($user->guid);
			break;
		case 'archive':
			$user = get_user_by_username($page[1]);
			$params = blog_get_page_content_archive($user->guid, $page[2], $page[3]);
			break;
		case 'view':
			$params = blog_get_page_content_read($page[1]);
			break;
		case 'read':
			register_error(elgg_echo("changebookmark"));
			forward("blog/view/{$page[1]}");
			break;
		case 'add':
			gatekeeper();
			$params = blog_get_page_content_edit($page_type, $page[1]);
			break;
		case 'edit':
			gatekeeper();
			$params = blog_get_page_content_edit($page_type, $page[1], $page[2]);
			break;
		case 'group':
			if ($page[2] == 'all') {
				$params = blog_get_page_content_list($page[1]);
			} else {
				$params = blog_get_page_content_archive($page[1], $page[3], $page[4]);
			}
			break;
		case 'all':
			$params = blog_get_page_content_list();
			break;
		default:
			return false;
	}

	if (isset($params['sidebar'])) {
		$params['sidebar'] .= elgg_view('blog/sidebar', array('page' => $page_type));
	} else {
		$params['sidebar'] = elgg_view('blog/sidebar', array('page' => $page_type));
	}

	$body = elgg_view_layout('content', $params);

	echo elgg_view_page($params['title'], $body);
	return true;
}

/**
 * 将博客格式化并返回它的url
 *
 * @param ElggObject $entity Blog object
 * @return string URL of blog.
 */
function blog_url_handler($entity) {
	if (!$entity->getOwnerEntity()) {
		// 如果没有拥有者默认是标准视图
		return FALSE;
	}

	$friendly_title = elgg_get_friendly_title($entity->title);

	return "blog/view/{$entity->guid}/$friendly_title";
}

/**
 * 添加一个菜单item到一个拥有者块中
 */
function blog_owner_block_menu($hook, $type, $return, $params) {
	if (elgg_instanceof($params['entity'], 'user')) {
		$url = "blog/owner/{$params['entity']->username}";
		$item = new ElggMenuItem('blog', elgg_echo('blog'), $url);
		$return[] = $item;
	} else {
		if ($params['entity']->blog_enable != "no") {
			$url = "blog/group/{$params['entity']->guid}/all";
			$item = new ElggMenuItem('blog', elgg_echo('blog:group'), $url);
			$return[] = $item;
		}
	}

	return $return;
}

/**
 * 添加特定的博客链或博客信息到实体菜单
 */
function blog_entity_menu_setup($hook, $type, $return, $params) {
	if (elgg_in_context('widgets')) {
		return $return;
	}

	$entity = $params['entity'];
	$handler = elgg_extract('handler', $params, false);
	if ($handler != 'blog') {
		return $return;
	}

	if ($entity->status != 'published') {
		foreach ($return as $index => $item) {
			if ($item->getName() == 'access') {
				unset($return[$index]);
			}
		}

		$status_text = elgg_echo("blog:status:{$entity->status}");
		$options = array(
			'name' => 'published_status',
			'text' => "<span>$status_text</span>",
			'href' => false,
			'priority' => 150,
		);
		$return[] = ElggMenuItem::factory($options);
	}

	return $return;
}

/**
 * 设置通告信息主体
 * 
 * @param string $hook    Hook name
 * @param string $type    Hook type
 * @param string $message The current message body
 * @param array  $params  Parameters about the blog posted
 * @return string
 */
function blog_notify_message($hook, $type, $message, $params) {
	$entity = $params['entity'];
	$to_entity = $params['to_entity'];
	$method = $params['method'];
	if (elgg_instanceof($entity, 'object', 'blog')) {
		$descr = $entity->excerpt;
		$title = $entity->title;
		$owner = $entity->getOwnerEntity();
		return elgg_echo('blog:notification', array(
			$owner->name,
			$title,
			$descr,
			$entity->getURL()
		));
	}
	return null;
}

/**
 * 注册ECML的博客
 */
function blog_ecml_views_hook($hook, $entity_type, $return_value, $params) {
	$return_value['object/blog'] = elgg_echo('blog:blogs');

	return $return_value;
}

/**
 * 版本升级，1.7到1.8
 */
function blog_run_upgrades($event, $type, $details) {
	$blog_upgrade_version = elgg_get_plugin_setting('upgrade_version', 'blogs');

	if (!$blog_upgrade_version) {
		 // 在升级时，这些代码被加载到Elgg1.8的时侯检查ElggBlog类是否被注册
		if (!update_subtype('object', 'blog', 'ElggBlog')) {
			add_subtype('object', 'blog', 'ElggBlog');
		}

		elgg_set_plugin_setting('upgrade_version', 1, 'blogs');
	}
}
