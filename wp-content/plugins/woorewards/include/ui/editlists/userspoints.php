<?php
namespace LWS\WOOREWARDS\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Display users and their point at backend. */
class UsersPoints extends \LWS\Adminpanel\EditList\Source
{
	const L_PREFIX = 'lws_wre_pool_';
	const S_PREFIX = 'lws_wre_points_';

	function labels()
	{
		$default = \get_option('lws_wr_default_pool_name', 'default');
		$labels = array('user' => array(__("Users", 'woorewards-lite'), '1fr'));
		$labels[self::L_PREFIX.$default] = array(\LWS_WooRewards::getPointSymbol(2, $default), 'max-content'); // usermeta 'lws_wre_points_default'
		$labels['rewards'] = array(__("Rewards", 'woorewards-lite'), 'auto'); // filled by filter
		return \apply_filters('lws_woorewards_ui_userspoints_labels', $labels);
	}

	function read($limit)
	{
		global $wpdb;
		$stackIds = $this->getStackIds();
		$needed = (count($stackIds) * (1+strlen(self::S_PREFIX)));
		$needed = array_reduce($stackIds, function($carry, $item){return $carry + strlen($item->stack_id);}, $needed);
		// ensure sql settings are enough
		if( $wpdb->get_var("SHOW VARIABLES LIKE 'group_concat_max_len'", 1) < $needed )
			$wpdb->query($wpdb->prepare("SET SESSION group_concat_max_len=%d", $needed));

		$sql = array(
			'select' => "SELECT ID as user_id, u.user_login, u.user_email, u.display_name, u.user_nicename, GROUP_CONCAT(pts.meta_key SEPARATOR '|') as mkeys, GROUP_CONCAT(pts.meta_value SEPARATOR '|') as mvalues",
			'from'   => "FROM {$wpdb->users} as u",
			'join'   => "LEFT JOIN {$wpdb->usermeta} as pts ON pts.user_id=u.ID AND pts.meta_key LIKE 'lws_wre_points_%'",
			'where'  => '',
			'groupby'=> "GROUP BY u.ID",
			'order'  => "ORDER BY u.user_login"
		);
		$sql = $this->sort($sql);
		$sql = \apply_filters('lws_woorewards_ui_userspoints_request', $this->search($sql), true);
		$sql = \LWS\Adminpanel\EditList\RowLimit::append($limit, implode(' ', $sql));
		$users = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter

		$stacks = $this->filterStacksByLabels($stackIds);
		foreach( $users as &$user )
		{
			$metas = array_combine(explode('|', $user['mkeys']), explode('|', $user['mvalues']));
			foreach( $stacks as $poolKey => $stack )
			{
				// get raw value
				$user[$stack->alias] = \LWS_WooRewards::formatPoints(isset($metas[$stack->alias]) ? intval($metas[$stack->alias]) : 0, $stack->system);
				// add formated value
//				$user[$poolKey] = "<a class='lws_wre_point_history maxwidth right lws-icon-ic_history_24px' data-stack='{$stack->id}' data-user='{$user['user_id']}'>{$user[$stack->alias]}</a>";
				$user[$poolKey] = "<a class='lws_wre_point_history maxwidth right lws-icon-time-machine' data-stack='{$stack->id}' data-user='{$user['user_id']}'>{$user[$stack->alias]}</a>";
			}

			$edit = esc_attr(\get_edit_user_link($user['user_id']));
			$mailto = esc_attr('mailto:' . $user['user_email']);
			$user['user'] = implode(' - ', array(
				"<a href='$edit' target='_blank'>" . $user['user_login'] . "</a>",
				"<a href='$mailto'>" . $user['user_email'] . "</a>",
				"<span class='lws_wre_history_dispname'>".\apply_filters('lws_woorewards_customer_display_name', $user['display_name'], $user)."</span>"
			));

			$user['rewards'] = "<div class='lws-editlist-btns-line'>";
			$user['rewards'] .= implode('', \apply_filters('lws_woorewards_ui_userspoints_rewards_cell', array(), $user));
			$user['rewards'] .= "</div>";
		}
		return $users;
	}

	/** return array with same keys as labels (filter pool point only)
	 * and value as an object{
		 $id // esc_attr stack id
		 $alias // stack point amount sql meta_key
	 } */
	function filterStacksByLabels($stackIds)
	{
		$preLen = strlen(self::L_PREFIX);
		$stacks = array();

		foreach( $this->labels() as $label => $text )
		{
			if( substr($label, 0, $preLen) == self::L_PREFIX )
			{
				$poolName = substr($label, $preLen);
				if( isset($stackIds[$poolName]) )
				{
					$stacks[$label] = (object)array(
						'id' => esc_attr($stackIds[$poolName]->stack_id),
						'alias' => self::S_PREFIX . $stackIds[$poolName]->stack_id,
						'system' => $poolName,
					);
				}
			}
		}
		return $stacks;
	}

	/** @return array as [string:pool_name] => object{post_name, stack_id} */
	protected function getStackIds()
	{
		if( !isset($this->stackIds) )
		{
			global $wpdb;
			$this->stackIds = $wpdb->get_results("SELECT post_name, meta_value as stack_id, post_id FROM {$wpdb->postmeta} INNER JOIN {$wpdb->posts} ON ID=post_id WHERE meta_key='wre_pool_point_stack'", OBJECT_K);
		}
		return $this->stackIds;
	}

	function total()
	{
		global $wpdb;
		$sql = array(
			'select' => "SELECT COUNT(u.ID)",
			'from'   => "FROM {$wpdb->users} as u",
			'join'   => '',
			'where'  => ''
		);
		$sql = \apply_filters('lws_woorewards_ui_userspoints_request', $this->search($sql), false);
		$sql = implode(' ', $sql);
		$c = $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter
		return (is_null($c) ? -1 : $c);
	}

	/** @return the given $sql array with WHERE clause if required. */
	protected function search($sql)
	{
		$search = isset($_REQUEST['usersearch']) ? trim($_REQUEST['usersearch']) : '';
		if( !empty($search) )
		{
			global $wpdb;
			$like = $wpdb->prepare(" LIKE %s", "%$search%");
			$sql['where'] = "WHERE u.user_login$like OR u.user_email$like OR u.display_name$like OR u.user_nicename$like";
			if( !empty($term = intval($search)) )
				$sql['where'] .= " OR u.ID=$term";
			$sql['where'] .= " OR 0 < (SELECT COUNT(m.umeta_id) FROM {$wpdb->usermeta} as m WHERE u.ID=m.user_id AND m.meta_value$like AND (m.meta_key LIKE '%name' OR m.meta_key LIKE 'billing%'))";
		}
		return $sql;
	}

	public function getSortColumns()
	{
		if (!isset($this->sortSource)) {
			$this->sortSource = array(
				array('value' => '', 'label' => __("Default", 'woorewards-lite')),
			);
			global $wpdb;
			$req = \LWS\Adminpanel\Tools\Request::from($wpdb->posts, 'pool');
			$req->where(array(
				sprintf('post_type="%s"', \LWS\WOOREWARDS\Core\Pool::POST_TYPE),
				'post_status NOT IN ("trash")',
			));
			$req->leftJoin($wpdb->postmeta, 'stack', array(
				'stack.post_id=pool.ID',
				'stack.meta_key="wre_pool_point_stack"',
			));
			$req->order('post_title');
			$req->select('pool.ID, stack.meta_value as stack_id, pool.post_title');

			foreach($req->getResults() as $stack) {
				$this->sortSource[] = array(
					'value' => ($stack->ID . '-' . $stack->stack_id),
					'label' => $stack->post_title,
				);
			}
		}
		return $this->sortSource;
	}

	protected function sort($sql)
	{
		$column = $this->getSortValue('userspoints');
		$sql['order'] = "ORDER BY u.user_login";

		if ($column) {
			$column = explode('-', $column, 2);
			if (count($column) > 1) {
				global $wpdb;
				$key = ('lws_wre_points_' . $column[1]);
				$sql['join'] .= $wpdb->prepare("LEFT JOIN {$wpdb->usermeta} as csort ON csort.user_id=u.ID AND csort.meta_key=%s", $key); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$sql['order'] = "ORDER BY CASE WHEN csort.meta_value IS NOT NULL THEN CAST(csort.meta_value AS SIGNED) ELSE 0 END";
			}
		}

		$sql['order'] .= $this->isSortDescsending('userspoints') ? ' DESC' : ' ASC';
		return $sql;
	}

	/** no edition, use bulk action */
	function input()
	{
		return '';
	}

	/** no edition, use bulk action */
	function write($row)
	{
		return false;
	}

	/** Cannot erase a user here. */
	function erase($row)
	{
		return false;
	}
}
