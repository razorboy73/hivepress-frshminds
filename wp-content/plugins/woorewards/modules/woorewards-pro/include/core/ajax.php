<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Ajax API. Actions are:
 * * lws_woorewards_wc_product_list */
class Ajax
{
	function __construct()
	{
		\add_action( 'wp_ajax_lws_woorewards_wc_product_list', array( $this, 'getWCProducts') );
		\add_action( 'wp_ajax_lws_woorewards_wc_products_and_variations_list', array( $this, 'getWCProductsAndVariations') );
		\add_action( 'wp_ajax_lws_woorewards_pool_list', array( $this, 'getPools') );
		\add_action( 'wp_ajax_lws_woorewards_badge_list', array( $this, 'getBadges') );
		\add_action( 'wp_ajax_lws_woorewards_unlockable_list', array( $this, 'getUnlockables') );

		\add_action( 'wp_ajax_lws_woorewards_pointstack_list', array( $this, 'getPointStacks') );
		\add_action( 'wp_ajax_lws_woorewards_shared_points_with', array( $this, 'getSharedPointsWith') );
	}

	public function getBadges()
	{
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);

		global $wpdb;
		$sql = "SELECT ID as value, post_title as label FROM {$wpdb->posts}";
		if( $fromValue )
		{
			$sql .= " WHERE ID IN (" . implode(',', $term) . ")";
		}
		else
		{
			$where = array();
			if( !empty($term) )
			{
				$search = trim($term, "%");
				$where[] = $wpdb->prepare("post_title LIKE %s", "%$search%");
			}
			$where[] = "post_type='".\LWS\WOOREWARDS\PRO\Core\Badge::POST_TYPE."'";

			$sql .= " WHERE " . implode(' AND ', $where);
			$sql .= " AND post_status IN ('publish', 'private', 'future', 'pending')";
		}
		$sql = $this->finalizeQuery($sql, 'post_title');

		$badges = $wpdb->get_results($sql);
		foreach($badges as &$badge)
		{
			$img = \get_the_post_thumbnail($badge->value, array(21, 21), array('class'=>'lws-wr-thumbnail lws-wr-badge-icon'));
			$badge->html = "<div class='lws-wr-select-badge-icon'>" . ($img ? $img : '') . "</div>";
			$badge->html .= "<div class='lws-wr-select-badge-label'>{$badge->label}</div>";
		}
		\wp_send_json($badges);
	}

	public function getPools()
	{
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);

		global $wpdb;
		$sql = "SELECT ID as value, post_title as label FROM {$wpdb->posts}";
		if( $fromValue )
		{
			$sql .= " WHERE ID IN (" . implode(',', $term) . ")";
		}
		else
		{
			$where = array();
			if( !empty($term) )
			{
				$search = trim($term, "%");
				$where[] = $wpdb->prepare("post_title LIKE %s", "%$search%");
			}
			$where[] = "post_type='".\LWS\WOOREWARDS\Core\Pool::POST_TYPE."'";

			$sql .= " WHERE " . implode(' AND ', $where);
		}

		$sql = $this->finalizeQuery($sql, 'post_title');
		\wp_send_json($wpdb->get_results($sql));
	}

	/** The events about spending (personal or sponsored)
	 *  grouped by pool
	 *  @return LAC complient json */
	function getUnlockables()
	{
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);
		$poolType = \LWS\WOOREWARDS\Core\Pool::POST_TYPE;
		$childType = \LWS\WOOREWARDS\Abstracts\Unlockable::POST_TYPE;
		$instances = array();

		global $wpdb;
		if( $fromValue )
		{
			if( !$term )
			{
				\wp_send_json(array());
			}
			else
			{
				$terms = implode(',', $term);
				$sql = <<<EOT
SELECT ID as value, post_title as label, m.meta_value as u_type
FROM {$wpdb->posts} as p
INNER JOIN {$wpdb->postmeta} as m ON m.post_id=p.ID AND m.meta_key='wre_unlockable_type'
WHERE ID IN ($terms)
EOT;
				$sql = $this->finalizeQuery($sql, 'post_title');
				$json = $wpdb->get_results($sql);
				foreach( $json as &$row )
				{
					if( !(isset($instances[$row->u_type]) && $instances[$row->u_type]) )
						$instances[$row->u_type] = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create($row->u_type)->last();
					if( $instances[$row->u_type] )
					{
						$instances[$row->u_type]->id = $row->value;
						$instances[$row->u_type]->setTitle($row->label); // translations or default value
						$row->label = $instances[$row->u_type]->getTitle(true);
					}
					else if( !$row->label )
						$row->label = "deleted[{$row->value}]";
				}
				\wp_send_json($json);
			}
		}
		else
		{
			$sql = <<<EOT
SELECT p.ID as grpId, p.post_title as grpLabel,
e.ID as value, e.post_title as label, m.meta_value as u_type
FROM {$wpdb->posts} AS p
INNER JOIN {$wpdb->posts} as e ON e.post_parent=p.ID AND e.post_type='{$childType}'
INNER JOIN {$wpdb->postmeta} as m ON m.post_id=e.ID AND m.meta_key='wre_unlockable_type'
WHERE p.post_type='{$poolType}'
EOT;
			$where = array();
			if( !empty($term) )
			{
				$search = trim($term, "%");
				$sql .= $wpdb->prepare(" AND (p.post_title LIKE %s OR e.post_title LIKE %s)", "%$search%", "%$search%");
			}
			$sql .= " ORDER BY p.ID DESC, e.post_title ASC";
			$sql = $this->finalizeQuery($sql);
			$results = $wpdb->get_results($sql);

			$json = array();
			if( $results )
			{
				foreach( $results as $result )
				{
					if( !isset($json[$result->grpId]) )
						$json[$result->grpId] = array('value'=>$result->grpId, 'label'=>$result->grpLabel, 'group'=>array());

					if( !(isset($instances[$result->u_type]) && $instances[$result->u_type]) )
						$instances[$result->u_type] = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->create($result->u_type)->last();
					if( $instances[$result->u_type] )
					{
						$instances[$result->u_type]->id = $result->value;
						$instances[$result->u_type]->setTitle($result->label); // translations or default value
						$result->label = $instances[$result->u_type]->getTitle(true);
					}
					$json[$result->grpId]['group'][] = array(
						'value'=>$result->value,
						'label'=>$result->label
					);
				}
			}
			\wp_send_json($json);
		}
	}

	/** autocomplete/lac compliant.
	 * Search wp_post(wc_product) on id (or name if fromValue is false or missing).
	 * @see hook 'lws_woorewards_wc_product_list'.
	 * @param $_REQUEST['term'] (string) filter on product name
	 * @param $_REQUEST['page'] (int /optional) result page, not set means return all.
	 * @param $_REQUEST['count'] (int /optional) number of result per page, default is 10 if page is set. */
	public function getWCProducts()
	{
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);
		$spec = array();

		global $wpdb;
		$sql = "SELECT ID as value, post_title as label FROM {$wpdb->posts}";
		if( $fromValue )
		{
			$sql .= " WHERE ID IN (" . implode(',', $term) . ")";
		}
		else
		{
			$where = array();
			if( !empty($term) )
			{
				$search = trim($term, "%");
				$where[] = $wpdb->prepare("post_title LIKE %s", "%$search%");
			}
			$where[] = "post_type='product' AND (post_status='publish' OR post_status='private')";

			$sql .= " WHERE " . implode(' AND ', $where);
		}

		$sql = $this->finalizeQuery($sql, 'post_title');
		\wp_send_json($wpdb->get_results($sql));
	}

	/** autocomplete/lac compliant.
	 * Search wp_post(wc_product & variations) on id (or name if fromValue is false or missing).
	 * @see hook 'lws_woorewards_wc_products_and_variations_list'.
	 * @param $_REQUEST['term'] (string) filter on product name
	 * @param $_REQUEST['page'] (int /optional) result page, not set means return all.
	 * @param $_REQUEST['count'] (int /optional) number of result per page, default is 10 if page is set. */
	public function getWCProductsAndVariations()
	{
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue);
		$spec = array();

		global $wpdb;
		$sql = array(
			'SELECT' => "SELECT p.ID as value, p.post_title as label",
			'FROM' => "FROM {$wpdb->posts} as p",
			'JOIN' => '',
			'WHERE' => '',
			'GROUP' => '',
		);

		if( $fromValue )
		{
			$sql['WHERE'] = " WHERE p.ID IN (" . implode(',', $term) . ")";
		}
		else
		{
			$sql['SELECT'] .= ", GROUP_CONCAT(v.ID SEPARATOR ',') as variations";
			$sql['GROUP'] = "GROUP BY p.ID";
			$sql['JOIN'] = "LEFT JOIN {$wpdb->posts} as v ON p.ID=v.post_parent AND v.post_type='product_variation'";
			$sql['WHERE'] = "WHERE p.post_type='product' AND (p.post_status='publish' OR p.post_status='private')";

			if( !empty($term) )
			{
				$search = trim($term, "%");
				$sql['WHERE'] .= $wpdb->prepare(" AND p.post_title LIKE %s", "%$search%");
			}
		}

		$sql = $this->finalizeQuery(implode(' ', $sql), 'p.post_title');
		if( $products = $wpdb->get_results($sql) )
		{
			foreach($products as &$product)
			{
				if( isset($product->variations) && $product->variations )
				{
					$product->group = $wpdb->get_results(<<<EOT
SELECT p.ID as value, CONCAT('#', p.ID, ' - ', p.post_title) as label FROM {$wpdb->posts} as p
WHERE p.post_type='product_variation' AND (p.post_status='publish' OR p.post_status='private')
AND ID IN ({$product->variations})
ORDER BY p.post_title ASC
EOT
					);
				}
			}
		}
		\wp_send_json($products);
	}

	/** Get all existing point stacks, used or not. */
	function getPointStacks()
	{
		/// $fromValue: true means exact values, false is a search string
		$fromValue = (isset($_REQUEST['fromValue']) && boolval($_REQUEST['fromValue']));
		$term = $this->getTerm($fromValue, '', '\sanitize_key', '\sanitize_key');

		global $wpdb;
		$sql1 = "SELECT DISTINCT(SUBSTRING(u.meta_key, 16)) as `value` FROM {$wpdb->usermeta} as u";
		$sql2 = "SELECT DISTINCT(p.meta_value) as `value` FROM {$wpdb->postmeta} as p";
		if( $fromValue )
		{
			$metaKey = \array_map(function($t){return 'lws_wre_points_'.$t;}, $term);
			$sql1 .= sprintf(' WHERE u.meta_key IN ("%s")', implode('","', $metaKey));
			$sql2 .= sprintf(' WHERE p.meta_key="wre_pool_point_stack" AND p.meta_value IN ("%s")', implode('","', $term));
		}
		else
		{
			$sql1 .= " WHERE u.meta_key LIKE 'lws_wre_points_{$term}%'";
			$sql2 .= " WHERE p.meta_key='wre_pool_point_stack' AND p.meta_value LIKE '{$term}%'";
		}

		$sql = $this->finalizeQuery("{$sql1} UNION {$sql2}");
		$stacks = $wpdb->get_results($sql, OBJECT_K);
		if( $stacks && isset($stacks['']) )
			unset($stacks['']);

		if( $stacks )
		{
			$stacks = \array_map(function($s){$s->pools = array(); return $s;}, $stacks);

			$type = \esc_sql(\LWS\WOOREWARDS\Core\Pool::POST_TYPE);
			$sql = <<<EOT
SELECT meta_value, post_title FROM {$wpdb->posts}
JOIN {$wpdb->postmeta} ON post_id=ID AND meta_key='wre_pool_point_stack'
WHERE post_type='{$type}'
EOT;
			$pools = $wpdb->get_results($sql);
			if( $pools )
			{
				foreach( $pools as $pool )
				{
					if( isset($stacks[$pool->meta_value]) )
						$stacks[$pool->meta_value]->pools[] = $pool->post_title;
				}
			}

			$stacks = \array_map(function($s){
				if( !$s->pools )
					$html = sprintf("<div class='stack-container'><div class='stack-name'>%s</div></div>", $s->value);
				else
					$html = sprintf(
						"<div class='stack-container'><div class='stack-name'>%s</div><div class='used-cont'><div class='used-label'>%s</div><div class='used-value'>%s</div></div></div>",
						$s->value,
						__("Used by", 'woorewards-pro'),
						implode(_x(", ", 'pool name separator', 'woorewards-pro'), $s->pools)
					);
				return (object)array(
					'value' => $s->value,
					'label' => $s->value,
					'html'  => $html,
				);
			}, $stacks);
		}

		\wp_send_json($stacks);
	}

	/** output json object with
	 *	* systems: an array of pool id using the given stack
	 *	* message: a text list of pool names. */
	function getSharedPointsWith()
	{
		$data = array(
			'origin' => isset($_REQUEST['stack']) ? sanitize_key($_REQUEST['stack']) : false,
			'message' => '',
			'systems' => array(),
		);
		if( $data['origin'] )
		{
			$except = isset($_REQUEST['except']) ? \intval($_REQUEST['except']) : 0;

			global $wpdb;
			$sql = <<<EOT
SELECT ID, post_title FROM {$wpdb->posts}
JOIN {$wpdb->postmeta} ON post_id=ID AND meta_key='wre_pool_point_stack' AND meta_value=%s
WHERE post_type=%s AND ID<>%d
EOT;
			$pools = $wpdb->get_results($wpdb->prepare($sql, $data['origin'], \LWS\WOOREWARDS\Core\Pool::POST_TYPE, $except));
			if( $pools )
			{
				$data['systems'] = array_column($pools, 'ID');
				$data['message'] = implode(_x(", ", 'pool name separator', 'woorewards-pro'), array_column($pools, 'post_title'));
			}
		}
		\wp_send_json($data);
	}

	/** @return $sql with order by and limit appended. */
	protected function finalizeQuery($sql, $orderBy='', $dir='ASC')
	{
		if( !empty($orderBy) )
			$sql .= " ORDER BY {$orderBy} {$dir}";

		if( isset($_REQUEST['page']) && is_numeric($_REQUEST['page']) )
		{
			$count = absint(isset($_REQUEST['count']) && is_numeric($_REQUEST['count']) ? $_REQUEST['count'] : 10);
			$offset = absint($_REQUEST['page']) * $count;
			$sql .= " LIMIT $offset, $count";
		}
		return $sql;
	}

	/** @param $readAsIdsArray (bool) true if term is an array of ID or false if term is a string
	 *	@param $prefix (string) remove this prefix at start of term values.
	 *	@param $_REQUEST['term'] (string) filter on post_title or if $readAsIdsArray (array of int) filter on ID.
	 *	@return an array of int if $readAsIdsArray, else a string. */
	private function getTerm($readAsIdsArray, $prefix='', $keySanitize='\intval', $valueSanitize='\sanitize_text_field')
	{
		$len = strlen($prefix);
		$term = '';
		if( isset($_REQUEST['term']) )
		{
			if( $readAsIdsArray )
			{
				if( is_array($_REQUEST['term']) )
				{
					$term = array();
					foreach( $_REQUEST['term'] as $t )
					{
						if( $len > 0 && substr($t, 0, $len) == $prefix )
							$t = substr($t, $len);
						$term[] = \call_user_func($keySanitize, $t);
					}
				}
				else
					$term = array(\call_user_func($keySanitize, $_REQUEST['term']));
			}
			else
				$term = \call_user_func($valueSanitize, trim($_REQUEST['term']));
		}
		return $term;
	}
}
