<?php
namespace LWS\WOOREWARDS\PRO;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Conveniences Class for shared functions*/
class Conveniences
{

	public static function install()
	{
		$me =& self::instance();
		\add_filter('lws_woorewards_get_pools_by_args', array($me, 'getPoolsByArgs'), 10, 2); // Higher proprity than WooRewards Standard
	}

	/** @return singleton instance */
	static function &instance()
	{
		static $_inst = false;
		if( false === $_inst )
			$_inst = new self();
		return $_inst;
	}

	/** prevent outside instanciation */
	protected function __construct()
	{}

	/** @return array of object [pool_name, pool_id, stack_id, pool_type] with pool_name as array keys. */
	function getPoolsInfo()
	{
		static $buffer = false;
		if( false === $buffer )
		{
			$postTypes = \apply_filters('lws_woorewards_available_post_types', array('lws-wre-pool', 'lws-wre-achievement'));
			$postTypes = implode("','", array_map('\esc_sql', $postTypes));

			global $wpdb;
			$sql = <<<EOT
SELECT p.post_name as pool_name, p.ID as pool_id, m.meta_value as stack_id, t.meta_value as pool_type
FROM {$wpdb->posts} as p
LEFT JOIN {$wpdb->postmeta} as t ON t.post_id=p.ID AND t.meta_key='wre_pool_type'
LEFT JOIN {$wpdb->postmeta} as m ON m.post_id=p.ID AND m.meta_key='wre_pool_point_stack'
WHERE p.post_type IN ('{$postTypes}')
ORDER BY p.ID ASC
EOT;
			$buffer = $wpdb->get_results($sql, OBJECT_K);
			if( !$buffer ) // sql error
				$buffer = array();
		}
		return $buffer;
	}

	/** @param $ref (string|int) the pool name or pool id.
	 * @return (string) the stackid */
	function getStackOfPool($ref)
	{
		$byPoolName = $this->getPoolsInfo();
		if( isset($byPoolName[$ref]) )
			return $byPoolName[$ref]->stack_id;
		if( \is_numeric($ref) )
		{
			static $byPoolId = false;
			if( false === $byPoolId )
				$byPoolId = \array_column($byPoolName, 'stack_id', 'pool_id');
			if( isset($byPoolId[$ref]) )
				return $byPoolId[$ref];
		}
		return false;
	}

	/** @param $ref (string|int) the pool name or pool id.
	 * @return (string) the type @see \LWS\WOOREWARDS\Core\Pool::T_STANDARD @see \LWS\WOOREWARDS\Core\Pool::T_LEVELLING */
	function getTypeOfPool($ref)
	{
		$byPoolName = $this->getPoolsInfo();
		if( isset($byPoolName[$ref]) )
			return $byPoolName[$ref]->pool_type;
		if( \is_numeric($ref) )
		{
			static $byPoolId = false;
			if( false === $byPoolId )
				$byPoolId = \array_column($byPoolName, 'pool_type', 'pool_id');
			if( isset($byPoolId[$ref]) )
				return $byPoolId[$ref];
		}
		return false;
	}

	/** returns a collection of pools depending on the sent parameters
	 *	@return Collections\Pools
	 * 	@param $pools (false|Collections\Pools)
	 *	@param $atts (array)
	 *	* system = loyalty system (or several loyalty systems, comma separated)
	 *	* shared = show shared pools
	 *  * force  = don't check if user has rights on the pool
	*/
	public function getPoolsByArgs($pools, $atts, $userId = false)
	{
		$atts = \wp_parse_args($atts, array('system'=>'', 'shared'=>'', 'force'=>''));
		if( !$atts['system'] ) // No pools specified, fallback to the prefab
			return $pools;

		if( false === $userId )
			$userId = \get_current_user_id();

		if( $atts['shared'] )
		{
			$stackId = $this->getStackOfPool($atts['system']);
			if( $stackId )
			{
				$pools = \LWS_WooRewards_Pro::getBuyablePools()->filterByStackId($stackId);
				if( !$atts['force'] )
					$pools = $pools->filterByUserCan($userId);
			}
			else
				$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate();
		}
		else
		{
			$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate();
			$poolList = array_map('trim', explode(',',$atts['system']));
			foreach($poolList as $item)
			{
				$pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($item, true);
				if ($pool && $pool->isBuyable() && ($atts['force'] || $pool->userCan($userId)))
					$pools->add($pool);
			}
		}
		return $pools;
	}

	/* Get Available WooCommerce coupons for the provided user */
	public function getCoupons($userId)
	{
		$user = \get_user_by('ID', $userId);
		if( empty($user->user_email) )
			return array();
		$todayDate = strtotime(date('Y-m-d'));
		global $wpdb;
		$query = <<<EOT
SELECT p.ID, p.post_content, p.post_title, p.post_excerpt, e.meta_value AS expiry_date
FROM {$wpdb->posts} as p
INNER JOIN {$wpdb->postmeta} as m ON p.ID = m.post_id AND m.meta_key='customer_email'
LEFT JOIN {$wpdb->postmeta} as l ON p.ID = l.post_id AND l.meta_key='usage_limit'
LEFT JOIN {$wpdb->postmeta} as u ON p.ID = u.post_id AND u.meta_key='usage_count'
LEFT JOIN {$wpdb->postmeta} as e ON p.ID = e.post_id AND e.meta_key='date_expires'
WHERE m.meta_value=%s AND post_type = 'shop_coupon' AND post_status = 'publish'
AND (e.meta_value is NULL OR e.meta_value = '' OR e.meta_value >= '{$todayDate}')
AND (u.meta_value < l.meta_value OR u.meta_value IS NULL OR l.meta_value IS NULL OR l.meta_value=0)
EOT;
		$result = $wpdb->get_results($wpdb->prepare($query, serialize(array($user->user_email))), OBJECT_K);
		if( empty($result) )
			return $result;
		$ids = implode(",", array_map('intval', array_keys($result)));
		$sub = $wpdb->get_results(<<<EOT
SELECT p.ID, v.meta_value AS coupon_amount, o.meta_value AS product_ids, w.meta_value AS discount_type
FROM {$wpdb->posts} as p
LEFT JOIN {$wpdb->postmeta} as w ON p.ID = w.post_id AND w.meta_key='discount_type'
LEFT JOIN {$wpdb->postmeta} as v ON p.ID = v.post_id AND v.meta_key='coupon_amount'
LEFT JOIN {$wpdb->postmeta} as o ON p.ID = o.post_id AND o.meta_key='product_ids'
WHERE p.ID IN ({$ids})
EOT
		, OBJECT_K);

		foreach( $sub as $id => $info )
		{
			foreach( $info as $k => $v )
			$result[$id]->$k = $v;
		}
		return $result;
	}

	/** Get a list of unlockables for a given user
	 * $auth = all | Provide all unlockables
	 * $auth = avail | Provide only available unlockables
	 * */
	public function getUserUnlockables($userId, $auth= 'all')
	{
		$unlockables = \LWS\WOOREWARDS\Collections\Unlockables::instanciate();
		foreach( \LWS_WooRewards_Pro::getBuyablePools()->asArray() as $pool )
		{
			if($pool->userCan($userId))
			{
				$type = $pool->getOption('type');
				if($type != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING && !$pool->getOption('direct_reward_mode'))
				{
					$userPoints = $auth==='all' ? PHP_INT_MAX : $pool->getPoints($userId);
					foreach( $pool->getUnlockables()->asArray() as $item )
					{
						if($auth==='all' || ($auth==='avail' && $item->isPurchasable($userPoints, $userId)))
						{
							$unlockables->add($item, $item->getId());
						}
					}
				}
			}
		}
		return $unlockables->sort()->asArray();
	}

	/* Get the Url page for redeeming rewards */
	public function getUrlTarget($demo=false)
	{
		if( $demo )
		{
			return '#';
		}
		else
		{
			if( !isset($this->urlTarget) )
			{
				if( !empty($page = get_option('lws_woorewards_reward_claim_page', '')) )
					$this->urlTarget = \get_permalink($page);
				else if( \LWS_WooRewards::isWC() && \get_option('lws_woorewards_wc_my_account_endpont_loyalty', 'on') )
					$this->urlTarget = \LWS_WooRewards_Pro::getEndpointUrl('lws_woorewards');
				else
					$this->urlTarget = \home_url();
			}
			return $this->urlTarget;
		}
	}

	/** Compute a hue (for HSL color) from an arbitrary index value.
	 *	Hue is build by dichotomy on hue wheel
	 *	@param $index (int) greater or equal than zero
	 *	@return (int) between 0 and 360 */
	function indexToHue($index)
	{
		$hue = 0;
		$wheel = 180;
		while( $index && $wheel >= 1.0 )
		{
			$part = ($index % 2);
			$hue += $wheel * $part;
			$index -= $part;
			$index = \floor($index / 2);
			$wheel /= 2.0;
		}
		return $hue;
	}

	/**	@param $hue (int) from 0 ot 360
	 *	@param $lightness (int) from 0 ot 100
	 *	@param $saturation (int) from 0 ot 100
	 *	@return (string) CSS compliant HSL color string. */
	function getHSL($hue=0, $lightness=100, $saturation=50, $addSemicolon=false)
	{
		$hsl = sprintf('hsl(%d, %d%%, %d%%)', \intval($hue) % 360, \intval($lightness), \intval($saturation));
		if( $addSemicolon )
			$hsl .= ';';
		return $hsl;
	}

	/** @see getHSL @see indexToHue */
	function getHSLFromIndex($index, $lightness=100, $saturation=50, $addSemicolon=false)
	{
		return $this->getHSL($this->indexToHue($index), $lightness, $saturation, $addSemicolon);
	}

	/** @return WC_Product or false */
	function getProductFromOrderItem(&$order, &$item)
	{
		if( \method_exists($item, 'get_product') )
			return $item->get_product();
		else
			return $order->get_product_from_item($item);
	}

	function getSponsorshipOrigins()
	{
		static $origins = false;
		if( false === $origins ) {
			$origins = array(
				array('value' => ''         , 'label' => __("Email", 'woorewards-pro')),
				array('value' => 'sponsor'  , 'label' => __("Email", 'woorewards-pro')),
				array('value' => 'referral' , 'label' => __("Referral Link", 'woorewards-pro')),
				array('value' => 'manual'   , 'label' => __("Manual", 'woorewards-pro')),
				array('value' => 'facebook' , 'label' => __("Facebook", 'woorewards-pro')),
				array('value' => 'twitter'  , 'label' => __("Twitter", 'woorewards-pro')),
				array('value' => 'pinterest', 'label' => __("Pinterest", 'woorewards-pro')),
				array('value' => 'linkedin' , 'label' => __("LinkedIn", 'woorewards-pro')),
				array('value' => 'whatsapp' , 'label' => __("Whatsapp", 'woorewards-pro')),
				array('value' => 'mewe'     , 'label' => __("MeWe", 'woorewards-pro')),
			);
		}
		return \apply_filters('lws_woorewards_sponsorship_origins', $origins);
	}
}
