<?php
namespace LWS\Adminpanel\Pages;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/**	The first class to manage frontend page.
 *	Need WooCommerce.
 *	Add a new tab in WC My-acconut.
 *	Content is a normal page.
 *  */
class Endpoint
{
	/** Override this method or set the same array to constructor */
	protected function getDefaultOptions()
	{
		return array(
			'prefix'  => '', /// not empty string, used for options names.
			'title'   => '', /// not empty string (human readable).
			'slug'    => '', /// not empty string (url).
			'enable'  => true, /// tab activated by default.
			'wpml'    => false, /// string with first letter case up.
			'content' => false, /// string (content text) or callable (returning a string), to use as default page content.
		);
	}

	/** Add the page in WooCommerce MyAccount */
	public static function install($options=false)
	{
		$me = new static($options);
		$slug = $me->getSlug();

		if ($me->currentUserCan()) {
			// register endpoint in WC my-account
			\add_filter('woocommerce_account_menu_items', array($me, 'getWCMyAccountTabs'));
			\add_filter('woocommerce_get_query_vars', array($me, 'getQueryVars'));
			\add_action('init', array($me, 'addRewriteRules'), 11);

			// behavior in WC my-account
			\add_action('woocommerce_account_' . $slug . '_endpoint', array($me, 'printPage'));
			\add_filter('woocommerce_endpoint_' . $slug . '_title', array($me, 'getTitle'));
		}

		// option update should force a rewrite rules
		\add_filter('pre_update_option_' . $me->options['prefix'] . '_slug', array($me, 'forceRules'), 10, 3);
		if ($me->isEnabled() && !\get_option($me->options['prefix'] . '_rflush')) {
			\add_action('shutdown', array($me, 'rewriteRules'));
		}
	}

	/** Get the settings fields to add in Admin page. */
	public static function getAdminFields($options=false, $fields=array())
	{
		$me = new static($options);

		$roles = array();
		foreach (\wp_roles()->get_names() as $value => $label) {
			$roles[] = array('value' => $value, 'label' => $label);
		}

		$fields['enable'] = array(
			'id'    => $me->options['prefix'] . '_enable',
			'title' => __("Enable", 'lws-adminpanel'),
			'type'  => 'box',
			'extra' => array(
				'class'   => 'lws_checkbox',
				'default' => $me->options['enable'] ? 'on' : '',
			)
		);
		$fields['title']  = array(
			'id'    => $me->options['prefix'] . '_title',
			'title' => __("Tab title", 'lws-adminpanel'),
			'type'  => 'text',
			'extra' => array(
				'placeholder' => $me->options['title'],
				'wpml' => $me->options['wpml'],
			)
		);
		$fields['slug']  = array(
			'id'    => $me->options['prefix'] . '_slug',
			'title' => __("Slug", 'lws-adminpanel'),
			'type'  => 'text',
			'extra' => array(
				'placeholder' => $me->options['slug'],
			)
		);
		$fields['roles']  = array(
			'id'    => $me->options['prefix'] . '_role',
			'title' => __("Role restriction", 'lws-adminpanel'),
			'type'  => 'lacchecklist',
			'extra' => array(
				'help' => __("Restrict this tab to given roles. No roles selected means no restriction at all.", 'lws-adminpanel'),
				'source' => $roles,
			)
		);
		$fields['page'] = array(
			'id'    => $me->options['prefix'] . '_page',
			'title' => __("Content page", 'lws-adminpanel'),
			'type'  => 'lacselect',
			'extra' => array(
				'predefined' => 'page',
				'maxwidth' => '22em',
			)
		);

		$pageId = \intval(\get_option($me->options['prefix'] . '_page'));
		if ($pageId) {
			$fields['see'] = array(
				'id'    => 'lws_referralcodes_myaccount_see',
				'type'  => 'custom',
				'extra' => array(
					'content' => sprintf(
						'<a href="%s">%s</a>',
						\esc_attr(\get_edit_post_link($pageId, 'raw')),
						__('Edit the page')
					)
				)
			);
		}

		if ($me->options['content'] && \current_user_can('edit_pages') && \current_user_can('publish_pages')) {
			$fields['create'] = array(
				'id'    => $me->options['prefix'] . '_create',
				'title' => __("Create a new default page", 'lws-adminpanel'),
				'type'  => 'button',
				'extra' => array(
					'callback' => array($me, 'createPage'),
				)
			);
		}

		return $fields;
	}

	function createPage($btnId, $data=array())
	{
		if( $btnId != ($this->options['prefix'] . '_create') ) return false;
		return $this->_createPage();
	}

	function _createPage()
	{
		$pageId = \wp_insert_post(array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => \lws_get_option($this->options['prefix'] . '_title', $this->options['title']),
			'post_content' => $this->getDefaultContent(),
		), false);

		if ($pageId) {
			\update_option($this->options['prefix'] . '_page', $pageId);
			return sprintf(
				'<div class="notice notice-success"><p>%s<br/><a href="%s">%s</a></p></div>',
				__("Page created and setup as tab.", 'lws-adminpanel'),
				\esc_attr(\get_edit_post_link($pageId, 'raw')),
				__('Edit the page')
			);
		} else {
			return sprintf('<div class="notice notice-error"><p>%s</p></div>', __("An error occured during page creation.", 'lws-adminpanel'));
		}
	}

	protected function getDefaultContent()
	{
		if (!isset($this->options['content']))
			return '';
		elseif (!$this->options['content'])
			return '';
		elseif (\is_string($this->options['content']))
			return $this->options['content'];
		elseif (\is_callable($this->options['content']))
			return \call_user_func($this->options['content']);
		else
			return '';
	}

	public function forceRules($value, $oldValue, $option)
	{
		\update_option($this->options['prefix'] . '_rflush', false);
		return $value;
	}

	public function rewriteRules()
	{
		$this->addRewriteRules();
		\flush_rewrite_rules(true);
		\update_option($this->options['prefix'] . '_rflush', \time());
	}

	public function addRewriteRules()
	{
		\add_rewrite_endpoint($this->getSlug(), EP_ROOT|EP_PAGES);
	}

	/**	@param $key (slug) url endpoint
	 *	@param $title page title or my-account tab title if WC activated. */
	function __construct($options=false)
	{
		$this->options = $options ? $options : $this->getDefaultOptions();
	}

	/** Add our tab key to WC Query Vars */
	function getQueryVars($vars)
	{
		$vars[$this->getSlug()] = $this->getSlug();
		return $vars;
	}

	function getSlug()
	{
		return \lws_get_option($this->options['prefix'] . '_slug', $this->options['slug']);
	}

	function isEnabled()
	{
		return \get_option($this->options['prefix'] . '_enable', $this->options['enable']);
	}

	function currentUserCan()
	{
		$roles = \get_option($this->options['prefix'] . '_role');
		if ($roles && \is_array($roles)) {
			$user = \wp_get_current_user();
			if ($user && $user->exists()) {
				return (bool)\array_intersect($user->roles, $roles);
			} else {
				return false;
			}
		}
		return true;
	}

	/** @return WC my-account tab */
	function getTitle($title)
	{
		$title = \lws_get_option($this->options['prefix'] . '_title', $this->options['title']);
		if ($wpml = $this->getWPML())
			$title = \apply_filters('wpml_translate_single_string', $title, 'Widgets', $wpml);
		return $title;
	}

	/** get translation key */
	function getWPML()
	{
		return isset($this->options['wpml']) ? \ucfirst($this->options['wpml']) : false;
	}

	/** @return WC my-account tab list including our. */
	function getWCMyAccountTabs($items=array())
	{
		$tab = array($this->getSlug() => $this->getTitle(''));
		if( empty($items) )
			$items = $tab;
		else // insert at penultimate place (-1), since last is usually "log off".
			$items = array_slice($items, 0, -1, true) + $tab + array_slice($items, -1, NULL, true);
		return $items;
	}

	/** echo the page for WC my-account page */
	function printPage()
	{
		$postId = \get_option($this->options['prefix'] . '_page');
		if ($postId) {
			$content = \get_the_content(null, false, $postId);
			$content = \apply_filters('the_content', $content);
			$content = \str_replace(']]>', ']]&gt;', $content);
			echo $content;
		}
	}
}
