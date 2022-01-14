<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage badge item like a post. */
class Pool extends \LWS\WOOREWARDS\Core\Pool
{
	protected $dateBegin           = false;     /// event starting period date (included) @see DateTime
	protected $allowDates          = false;     /// allows date edition, set to false will reset any date.
	protected $dateMid             = false;     /// event earning points period end (included) @see DateTime
	protected $dateEnd             = false;     /// event last day of period (included) @see DateTime
	protected $pointLifetime       = false;     /// delay before set user point to zero @see Conveniences\Duration
	protected $transactionalExpiry = false;
	protected $clampLevel          = false;     /// earning points are clamped at each level, so only one can be passed at a time
	protected $drmMaxPercentOfCart = 100.0;     /// directRewardMode restriction: maximum usable as percent of cart
	protected $drmMaxPointsOnCart  = 0;         /// directRewardMode restriction: maximum usable points
	protected $drmTotalFloor       = 0.0;       /// directRewardMode restriction: cart grandtotal cannot be less than
	protected $drmMinSubtotal      = 0.0;       /// directRewardMode restriction: cart subtotal minimum amount to use points

	public function getData()
	{
		return array(
			'id' => $this->getName(),
			'name' => $this->getOption('display_title'),
			'points' => $this->getStackId(),
			'active' => $this->isActive() ? 'on' : 'off',
		);
	}

	public function __construct($name='')
	{
		parent::__construct($name);
		$this->pointLifetime = \LWS\Adminpanel\Duration::void();
		$this->transactionalExpiry = array('date'=>false, 'period'=>\LWS\Adminpanel\Duration::void());
	}

	/** Register all the Hooks required to run points gain events and unlockables.
	 *	Must be called only once per active pool. */
	public function install()
	{
		parent::install();
		if( $this->isActive() )
		{
			\add_action('lws_woorewards_daily_event', array($this, 'checkPointsTimeout'));
			\add_action('lws_woorewards_daily_event', array($this, 'checkTransactionalExpiry'));
		}
		return $this;
	}

	/** Some configuration sets are relevant as specific pool kind.
	 *	@return array of option */
	public function getDefaultConfiguration($type)
	{
		$config = array(
			'whitelist' => array($type)
		);
		return \apply_filters('lws_woorewards_core_pool_default_configuration', $config, $type);
	}

	/** reset point if timeout */
	public function checkPointsTimeout()
	{
		if( !$this->pointLifetime->isNull() )
		{
			$confiscate = $this->getOption('type') == self::T_LEVELLING && $this->getOption('confiscation');
			$users = $this->getStack(0)->reset(\date_create()->sub($this->pointLifetime->toInterval()), $confiscate);

			if( $confiscate && !empty($users) )
			{
				$c = new \LWS\WOOREWARDS\PRO\Core\Confiscator();
				$c->setByPool($this);
				$c->setUserFilter($users);
				$c->revoke();
			}
		}
	}

	/** Remove points unused since a given date.
	 *	Then it revokes rewards too expensive for the rest of users points.
	 *
	 *	If no trigger date, run with every cron trigger (daily).
	 * 	If trigger date set, we wait for it, then plan the next by shift by period.
	 *
	 *	First, look at the points available at trigger_date - period (or closest available date)
	 *	Calculate the Sum of all used points from that date up to now
	 *	If the Sum is inferior to the points, deduce the difference
	 *
	 *	@note
	 *	Here, we assume order by `id` should be as accurate than date (if no one junked the base by hand)
	 *	but avoid too many joins.
	 *	@endnote
	 */
	public function checkTransactionalExpiry()
	{
		if( !($this->transactionalExpiry && is_array($this->transactionalExpiry)) )
			return;
		if( $this->transactionalExpiry['period']->isNull() )
			return;

		if( !$this->transactionalExpiry['date'] || \date_create() >= $this->transactionalExpiry['date'] )
		{
			@set_time_limit(0); // could be a long deal
			$refDate = $this->transactionalExpiry['date'] ? $this->transactionalExpiry['date'] : \date_create()->setTime(0,0);
			//$ceil = $refDate->format('Y-m-d');
			$floor = $refDate->sub($this->transactionalExpiry['period']->toInterval())->format('Y-m-d');
			$stack = $this->getStackId();
			$metaKey = $this->getStack(0)->metaKey();
			$comment = \LWS\WOOREWARDS\Core\Trace::serializeReason(array("Lose points unused since %s", $floor), 'woorewards-pro');

			$this->planNextTransactionalExpiry();

			global $wpdb;
			$origin = sprintf( // build a unique ref.
				'trans_expiry_%d_%d_%d',
				$this->getId(),
				$wpdb->get_var("SELECT max(id) FROM {$wpdb->lwsWooRewardsHistoric}"),
				\date_create()->getTimestamp()
			);

			// First, insert the log, we can find them back with origin value
			// points to lose = (lost.earned - IFNULL(used.consumed, 0))
			// current points = IFNULL(m.meta_value, 0)
			// rest of points = (IFNULL(m.meta_value, 0) - (lost.earned - IFNULL(used.consumed, 0)))
			$sql = <<<EOT
INSERT INTO {$wpdb->lwsWooRewardsHistoric} (
	`user_id`, `points_moved`, `new_total`,
	`stack`, `commentar`, `origin`, `blog_id`
)
SELECT lost.user_id, -(lost.earned - IFNULL(used.consumed, 0)), (IFNULL(m.meta_value, 0) - (lost.earned - IFNULL(used.consumed, 0))),
	%s, %s, %s, %d
FROM (
	SELECT t.user_id, t.new_total as earned
	FROM {$wpdb->lwsWooRewardsHistoric} as t
	JOIN (
		SELECT p.user_id, max(p.id) as p_id
		FROM {$wpdb->lwsWooRewardsHistoric} as p
		WHERE p.stack=%s
		AND p.mvt_date<DATE(%s)
		GROUP BY p.user_id
	) as p ON p_id=t.id
) as lost
LEFT JOIN (
	SELECT u.user_id, -sum(u.points_moved) as consumed
	FROM {$wpdb->lwsWooRewardsHistoric} as u
	WHERE u.stack=%s
	AND u.mvt_date>=DATE(%s)
	AND u.points_moved IS NOT NULL AND u.points_moved<0
	GROUP BY u.user_id
) as used ON used.user_id=lost.user_id
LEFT JOIN {$wpdb->usermeta} as m ON m.user_id=lost.user_id AND m.meta_key=%s
WHERE ((used.consumed IS NULL AND lost.earned > 0) OR lost.earned > used.consumed)
EOT;
			$args = array(
					$stack,
					$comment,
					$origin,
					\get_current_blog_id(),
					$stack,
					$floor,
					$stack,
					$floor,
					$metaKey,
			);

			$affected = $wpdb->query($wpdb->prepare($sql, $args));
			if( false === $affected )
				error_log("Cannot insert logs to spoil users from points transactional expiry.");
			if( !$affected )
				return;

			// Update new user point total in user meta
			$sql = <<<EOT
UPDATE {$wpdb->usermeta} as m
INNER JOIN {$wpdb->lwsWooRewardsHistoric} as h ON h.user_id=m.user_id AND h.stack=%s AND h.origin=%s
SET m.meta_value=h.new_total
WHERE m.meta_key=%s
EOT;
			$args = array(
				$stack,
				$origin,
				$metaKey,
			);
			$wpdb->query($wpdb->prepare($sql, $args));

			if( $this->getOption('type') == self::T_LEVELLING && $this->getOption('confiscation') )
			{
				$sql = "SELECT user_id, new_total as points FROM {$wpdb->lwsWooRewardsHistoric} WHERE `stack`=%s AND `origin`=%s";
				$logs = $wpdb->get_results($wpdb->prepare($sql, $stack, $origin), OBJECT_K);
				if( false === $logs )
				{
					error_log("Cannot read back spoiled users from points transactional expiry.");
					return;
				}

				// rewards grouped by cost
				$byCost = array();
				foreach( $this->getUnlockables()->asArray() as $unlockable )
				{
					$cost = $unlockable->getCost();
					$byCost[$cost][] = $unlockable;
				}
				\krsort($byCost, SORT_NUMERIC); // order by cost DESC

				// by cost DESC (at each loop, we should have less users)
				foreach( $byCost as $cost => $unlockables )
				{
					// filter OUT users with enougth points
					$logs = array_filter($logs, function($log)use($cost){
						return $log->points < $cost;
					});
					if( !$logs )
						break;

					// remove too expensive rewards for users
					$c = new \LWS\WOOREWARDS\PRO\Core\Confiscator();
					foreach( $unlockables as $unlockable )
						$c->addRef($unlockable);
					$c->setUserFilter(array_keys($logs));
					$c->revoke();
				}
			}
		}
	}

	protected function planNextTransactionalExpiry($save=true)
	{
		if( !$this->transactionalExpiry['period']->isNull() && $this->transactionalExpiry['date'] )
		{
			$now = \date_create();
			while( $now >= $this->transactionalExpiry['date'] )
			{
				$this->transactionalExpiry['date'] = $this->transactionalExpiry['period']->getEndingDate($this->transactionalExpiry['date']);
			}

			if( $save )
			{
				\update_post_meta(
					$this->getId(),
					'wre_point_transactional_expiry',
					self::transactionalExpiryToArray($this->transactionalExpiry)
				);
			}
		}
	}

	/** In pro version, an active pool is a buyable one but could be limited by an additionnal date.
	 * After that date, the pool stil lives but not points can be earned anymore. */
	public function isActive()
	{
		if( !isset($this->_isActive) )
		{
			if( !$this->isBuyable() )
				return ($this->_isActive = false);

			if( $this->getOption('type') != self::T_LEVELLING )
			{
				if (!empty($this->dateMid)   && \date_create('now', \function_exists('\wp_timezone') ? \wp_timezone() : NULL)->setTime(0, 0, 0) > $this->dateMid) // dateMid is include, so take care now is computed without time
					return ($this->_isActive = false);
			}

			$this->_isActive = true;
		}
		return $this->_isActive;
	}

	/** A buyable pool is enabled but limited by two extrem dates.
	 * If period is defined, today is included in. */
	public function isBuyable()
	{
		if( !parent::isActive() )
			return false;

		// if( !\is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) && !$this->userCan() )
		//	return false; // bad idea since some plugin (especially for shipping) could change order status via ajax and so on

		if (!empty($this->dateBegin) && \date_create('now', \function_exists('\wp_timezone') ? \wp_timezone() : NULL) < $this->dateBegin)
			return false;
		if (!empty($this->dateEnd)   && \date_create('now', \function_exists('\wp_timezone') ? \wp_timezone() : NULL)->setTime(0, 0) > $this->dateEnd) // dateEnd is include, so take care now is computed without time
			return false;

		return true;
	}

	/** @return (bool) is rewards unlockables.
	 * @param $date (false|DateTime) if pool set to prevent before a date, compare with this argument where false means today. */
	public function isUnlockPrevented($date=false)
	{
		$prevent = $this->getOption('prevent_unlock');
		if( !$prevent )
			return false;
		else if( true === $prevent )
			return true;
		else
			/** Jamais appelé */
			return ($date ? $date : \date_create()) < $prevent;
	}

	/** override to check user role.
	 * @param $user (WP_User|int|false) object, user_id or false for the current user. */
	public function userCan($user=false)
	{
		if (!$user)
		{
			$user = \wp_get_current_user();
		}
		else if (!is_a($user, '\WP_User') && is_numeric($user))
		{
			$user = \get_user_by('ID', $user);
		}

		// Allowed Roles
		if( $roles = $this->getOption('roles') )
		{
			if( !$user || !$user->ID )
				return false;

			if( empty(array_intersect($user->roles, $roles)) )
				return false;
		}

		// Denied Roles
		if( $deniedRoles = $this->getOption('denied_roles') )
		{
			if( $user && !empty(array_intersect($user->roles, $deniedRoles)) )
				return false;
		}

		return true;
	}

	protected function _customLoad(\WP_Post $post, $load=true)
	{
		$this->allowDates    = \boolval(\get_post_meta($post->ID, 'wre_pool_happening', true));
		$this->dateBegin     = $this->get_meta_datetime($post->ID, 'wre_pool_date_begin');
		$this->dateMid       = $this->get_meta_datetime($post->ID, 'wre_pool_date_mid');
		$this->dateEnd       = $this->get_meta_datetime($post->ID, 'wre_pool_date_end');
		$this->pointLifetime = \LWS\Adminpanel\Duration::postMeta($post->ID, 'wre_pool_point_deadline');
		$this->clampLevel    = \boolval(\get_post_meta($post->ID, 'wre_pool_clamp_level', true));
		$this->confiscation  = \boolval(\get_post_meta($post->ID, 'wre_pool_rewards_confiscation', true));
		$this->roles         = \get_post_meta($post->ID, 'wre_pool_roles', true);
		if( !is_array($this->roles) )
			$this->roles = empty($this->roles) ? array() : array($this->roles);
		$this->deniedRoles   = \get_post_meta($post->ID, 'wre_pool_denied_roles', true);
		if (!is_array($this->deniedRoles))
			$this->deniedRoles = empty($this->deniedRoles) ? array() : array($this->deniedRoles);
		$this->symbol        = \intval(\get_post_meta($post->ID, 'wre_pool_symbol', true));
		$this->pointName     = \get_post_meta($post->ID, 'wre_pool_point_name', true);
		$this->pointFormat   = \get_post_meta($post->ID, 'wre_pool_point_format', true);
		$this->thousandSep   = \get_post_meta($post->ID, 'wre_pool_thousand_sep', true);
		$this->bestUnlock    = \get_post_meta($post->ID, 'wre_pool_best_unlock', true);
		$this->preventUnlock = \get_post_meta($post->ID, 'wre_pool_prevent_unlock', true);
		if( !$this->preventUnlock ) $this->preventUnlock = false;
		else if( $this->preventUnlock == 'on' ) $this->preventUnlock = true;
		else $this->preventUnlock = \date_create($this->preventUnlock);

		$transExp = \get_post_meta($post->ID, 'wre_point_transactional_expiry', true);
		$this->transactionalExpiry = self::transactionalExpiryFromValue($transExp);

		if( $this->directRewardMode )
		{
			$this->drmMaxPercentOfCart = \floatval($this->getSinglePostMetaIfExists($post->ID, 'wre_pool_direct_reward_max_percent_of_cart', 100.0));
			$this->drmMaxPointsOnCart  = \intval($this->getSinglePostMetaIfExists($post->ID, 'wre_pool_direct_reward_max_points_on_cart', 0));
			$this->drmTotalFloor       = \floatval($this->getSinglePostMetaIfExists($post->ID, 'wre_pool_direct_reward_total_floor', 0.0));
			$this->drmMinSubtotal      = \floatval($this->getSinglePostMetaIfExists($post->ID, 'wre_pool_direct_reward_min_subtotal', 0.0));
		}

		return parent::_customLoad($post, $load);
	}

	protected function _customSave($withEvents=true, $withUnlockables=true)
	{
		if( !$this->isDeletable() || !$this->allowDates )
		{
			$this->allowDates = false;
			$this->dateBegin = false;
			$this->dateMid   = false;
			$this->dateEnd   = false;
		}
		if( $this->getOption('type') == self::T_LEVELLING )
			$this->dateMid   = false;
		else
			$this->clampLevel = false;

		if( !$this->directRewardMode )
		{
			// reset
			$this->drmMaxPercentOfCart  = 100.0;
			$this->drmMaxPointsOnCart   = 0;
			$this->drmTotalFloor        = 0.0;
			$this->drmMinSubtotal       = 0.0;
		}

		\update_post_meta($this->id, 'wre_pool_happening', $this->allowDates ? 'on' : '');
		\update_post_meta($this->id, 'wre_pool_date_begin', empty($this->dateBegin) ? '' : $this->dateBegin->format('Y-m-d'));
		\update_post_meta($this->id, 'wre_pool_date_mid',   empty($this->dateMid)   ? '' : $this->dateMid->format('Y-m-d'));
		\update_post_meta($this->id, 'wre_pool_date_end',   empty($this->dateEnd)   ? '' : $this->dateEnd->format('Y-m-d'));
		\update_post_meta($this->id, 'wre_pool_clamp_level', $this->clampLevel ? 'on' : '');
		\update_post_meta($this->id, 'wre_pool_rewards_confiscation', (isset($this->confiscation) && $this->confiscation) ? 'on' : '');
		\update_post_meta($this->id, 'wre_pool_roles', isset($this->roles) ? $this->roles : array());
		\update_post_meta($this->id, 'wre_pool_denied_roles', isset($this->deniedRoles) ? $this->deniedRoles : array());
		\update_post_meta($this->id, 'wre_pool_symbol', isset($this->symbol) ? $this->symbol : '');
		\update_post_meta($this->id, 'wre_pool_point_format', isset($this->pointFormat) ? $this->pointFormat : '');
		\update_post_meta($this->id, 'wre_pool_thousand_sep', isset($this->thousandSep) ? $this->thousandSep : '');

		\update_post_meta($this->id, 'wre_pool_direct_reward_max_percent_of_cart', $this->drmMaxPercentOfCart);
		\update_post_meta($this->id, 'wre_pool_direct_reward_max_points_on_cart',  $this->drmMaxPointsOnCart);
		\update_post_meta($this->id, 'wre_pool_direct_reward_total_floor',         $this->drmTotalFloor);
		\update_post_meta($this->id, 'wre_pool_direct_reward_min_subtotal',        $this->drmMinSubtotal);

		$pn = (isset($this->pointName) && $this->pointName) ? $this->pointName : array('singular'=>'', 'plural'=>'');
		\update_post_meta($this->id, 'wre_pool_point_name', $pn);

		$wpml = $this->getPackageWPML(true);
		\do_action('wpml_register_string', $pn['singular'], 'point_name_singular', $wpml, __("Point display name", 'woorewards-pro'), 'LINE');
		\do_action('wpml_register_string', $pn['plural'], 'point_name_plural', $wpml, __("Point display name (plural)", 'woorewards-pro'), 'LINE');

		if( $this->pointLifetime->isNull() )
			$this->pointLifetime->deletePostMeta($this->id, 'wre_pool_point_deadline');
		else
			$this->pointLifetime->updatePostMeta($this->id, 'wre_pool_point_deadline');

		\update_post_meta($this->id, 'wre_point_transactional_expiry', self::transactionalExpiryToArray($this->transactionalExpiry));

		\update_post_meta($this->id, 'wre_pool_best_unlock', isset($this->bestUnlock) ? $this->bestUnlock : 'off');
		$prevent = '';
		if( isset($this->preventUnlock) )
		{
			if( \is_a($this->preventUnlock, '\DateTime') )
				$prevent = $this->preventUnlock->format('Y-m-d');
			else
				$prevent = $this->preventUnlock ? 'on' : '';
		}
		\update_post_meta($this->id, 'wre_pool_prevent_unlock', $prevent);

		return parent::_customSave($withEvents, $withUnlockables);
	}

	/** @param (string) option name
	 * @param $default return that value if option does not exists.
	 *
	 * Options are:
	 * * happening     : (bool) allow period edition.
	 * * period_start  : (false|DateTime) If not false, pool activation is restricted in time. Date is included in active period. @see DateTime
	 * * period_mid    : (false|DateTime) If not false, pool point earning is restricted in time. Date is included in active period. @see DateTime
	 * * period_end    : (false|DateTime) If not false, pool activation is restricted in time. Date is included in active period. @see DateTime
	 * * point_timeout : (\LWS\Adminpanel\Duration instance) delay since last point gain until point reset to zero (\LWS\Adminpanel\Duration::isNull() means no reset). @see \LWS\Adminpanel\Duration
	 * * transactional_expiry : array(date, period) define a trigger date and a period to remove unused points.
	 * * confiscation  : (bool) to use with point_timeout and levelling behavior, remove rewards with points expiry.
	 * * clamp_level   : (bool) Earning points are clamped at each level, so only one can be passed at a time (false if type is not levelling). Only affect the addPoints() method. setPoints() will still pass all available levels.
	 * * roles         : (array of string) user roles restriction
	 * * symbol        : (int) media id used as point symbol.
	 * * symbol_image  : (string) <img> html block
	 * * point_name_singular : (string) point label
	 * * point_name_plural   : (string) point label
	 * * point_format  : (string) sprintf template, expect %1$s for points and %2$s for symbol/label
	 * * thousand_sep  : (string) thousand separator
	 * * best_unlock   : (bool) if several rewards available, unlock the more expensive first
	 * * prevent_unlock: (bool) rewards cannot be unlocked (except if tryUnlock is called with $force argument)
	 * * prevent_unlock_before : (DateTime) same as prevent_unlock but allows unlock after given date
	 * * direct_reward_max_percent_of_cart: (float) directRewardMode restriction: maximum usable point as percent of cart subtotal
	 * * direct_reward_max_points_on_cart : (int) directRewardMode restriction: maximum points that can be used on the cart
	 * * direct_reward_total_floor        : (float) directRewardMode restriction: cart subtotal cannot be less than
	 * * direct_reward_min_subtotal       : (float) directRewardMode restriction: cart subtotal minimum amount to use points
	 **/
	function _getCustomOption($option, $default)
	{
		$wpml = false;
		$value = $default;
		switch($option)
		{
			case 'happening':
				$value = $this->allowDates;
				break;
			case 'period_start':
				$value = $this->dateBegin;
				break;
			case 'period_mid':
				$value = $this->dateMid;
				break;
			case 'period_end':
				$value = $this->dateEnd;
				break;
			case 'point_timeout':
				$value = $this->pointLifetime; // \LWS\Adminpanel\Duration instance
				break;
			case 'transactional_expiry':
				$value = $this->transactionalExpiry; // array(DateTime, \LWS\Adminpanel\Duration)
				break;
			case 'clamp_level':
				$value = $this->clampLevel && (self::T_LEVELLING == $this->type);
				break;
			case 'confiscation':
				$value = isset($this->confiscation) ? $this->confiscation : false;
				break;
			case 'roles':
				$value = isset($this->roles) ? $this->roles : array();
				break;
			case 'denied_roles':
				$value = isset($this->deniedRoles) ? $this->deniedRoles : array();
				break;
			case 'symbol':
				$value = isset($this->symbol) ? intval($this->symbol) : false;
				break;
			case 'symbol_image':
				$imgId = isset($this->symbol) ? intval($this->symbol) : false;
				if( $imgId )
				{
					$img = \wp_get_attachment_image(\apply_filters('wpml_object_id', $imgId, 'attachment', true), 'small', false, array('class'=>'lws-woorewards-point-symbol'));
					if( $img )
						$value = $img;
				}
				break;
			case 'disp_point_name_singular':
				$wpml = $this->getPackageWPML();
			case 'point_name_singular':
				if( isset($this->pointName) && $this->pointName )
				{
					if( is_array($this->pointName) )
					{
						$name = (isset($this->pointName['singular']) ? $this->pointName['singular'] : '');
						if( !$name )
							$name = reset($this->pointName);
					}
					else
						$name = $this->pointName;
					if( $name )
						$value = !$wpml ? $name : \apply_filters('wpml_translate_string', $name, 'point_name_singular', $wpml);
				}
				break;
			case 'disp_point_name_plural':
				$wpml = $this->getPackageWPML();
			case 'point_name_plural':
				if( isset($this->pointName) && $this->pointName && is_array($this->pointName) )
				{
					$name = (isset($this->pointName['plural']) ? $this->pointName['plural'] : '');
					if( $name )
						$value = !$wpml ? $name : \apply_filters('wpml_translate_string', $name, 'point_name_plural', $wpml);
				}
				break;
			case 'point_format':
				$value = (isset($this->pointFormat) && $this->pointFormat) ? $this->pointFormat : '%1$s %2$s';
				break;
			case 'thousand_sep':
				$value = (isset($this->thousandSep) && $this->thousandSep) ? $this->thousandSep : '';
				break;
			case 'best_unlock':
				$value = (isset($this->bestUnlock) && $this->bestUnlock) ? $this->bestUnlock : 'off';
				break;
			case 'prevent_unlock_before':
				$value = (isset($this->preventUnlock) && \is_a($this->preventUnlock, '\DateTime')) ? $this->preventUnlock : false;
				break;
			case 'prevent_unlock':
				$value = isset($this->preventUnlock) ? $this->preventUnlock : false;
				break;
			case 'direct_reward_max_points_on_cart':
				$value = $this->drmMaxPointsOnCart;
				break;
			case 'direct_reward_max_percent_of_cart':
				$value = $this->drmMaxPercentOfCart;
				break;
			case 'direct_reward_total_floor':
				$value = $this->drmTotalFloor;
				break;
			case 'direct_reward_min_subtotal':
				$value = $this->drmMinSubtotal;
				break;
			case 'loading_order':
				$value = isset($this->order) ? $this->order : 1024;
				break;
		}
		return $value;
	}

	/** @param (string) option name.
	 * For option list @see getOption()
	 *
	 * Options are:
	 * * happening     : (bool) allow period edition.
	 * * period_start  : (false|DateTime) If not false, pool activation is restricted in time. Date is included in active period. @see DateTime
	 * * period_mid    : (false|DateTime) If not false, pool point earning is restricted in time. Date is included in active period. @see DateTime
	 * * period_end    : (false|DateTime) If not false, pool activation is restricted in time. Date is included in active period. @see DateTime
	 * * point_timeout : (false|string|DateInterval|\LWS\Adminpanel\Duration) delay since last point gain until point reset to zero. false, void() or empty means no reset. @see DateInterval, @see \LWS\Adminpanel\Duration
	 * * transactional_expiry : array(date, period) define a trigger date and a period to remove unused points.
	 * * confiscation  : (bool) to use with point_timeout and levelling behavior, remove rewards with points expiry.
	 * * clamp_level   : (bool) Earning points are clamped at each level, so only one can be passed at a time. Only affect the addPoints() method. setPoints() will still pass all available levels.
	 * * roles         : (string|array) user roles restriction
	 * * symbol        : (int) media id used as point symbol.
	 * * point_name_singular : (string) point label
	 * * point_name_plural   : (string) point label
	 * * point_format  : (string) sprintf template, expect %1$s for points and %2$s for symbol/label
	 * * thousand_sep  : (string) thousand separator
	 * * best_unlock   : (bool) if several rewards available, unlock the more expensive first
	 * * prevent_unlock: (bool) rewards cannot be unlocked (except if tryUnlock is called with $force argument)
	 * * prevent_unlock_before : (DateTime) same as prevent_unlock but allows unlock after given date
	 * * direct_reward_max_percent_of_cart: (float) directRewardMode restriction: maximum usable point as percent of cart subtotal
	 * * direct_reward_max_points_on_cart : (int) directRewardMode restriction: maximum points that can be used on the cart
	 * * direct_reward_total_floor        : (float) directRewardMode restriction: cart subtotal cannot be less than
	 * * direct_reward_min_subtotal       : (float) directRewardMode restriction: cart subtotal minimum amount to use points
	 **/
	protected function _setCustomOption($option, $value)
	{
		switch($option)
		{
			case 'happening':
				$this->allowDates = boolval($value);
				if( !$this->allowDates )
				{
					$this->dateBegin = false;
					$this->dateMid = false;
					$this->dateEnd = false;
				}
				break;
			case 'period_start':
				if( \is_a($value, '\DateTime') )
					$this->dateBegin = $value;
				else if( !empty($value) && \is_string($value) )
				{
					$d = \date_create($value);
					$this->dateBegin = empty($d) ? false : $d->setTime(0,0,0);
				}
				else
					$this->dateBegin = false;
				break;
			case 'period_mid':
				if( \is_a($value, '\DateTime') )
					$this->dateMid = $value;
				else if( !empty($value) && \is_string($value) )
				{
					$d = \date_create($value);
					$this->dateMid = empty($d) ? false : $d->setTime(0,0,0);
				}
				else
					$this->dateMid = false;
				break;
			case 'period_end':
				if( \is_a($value, '\DateTime') )
					$this->dateEnd = $value;
				else if( !empty($value) && \is_string($value) )
				{
					$d = \date_create($value);
					$this->dateEnd = empty($d) ? false : $d->setTime(0,0,0);
				}
				else
					$this->dateEnd = false;
				break;
			case 'point_timeout':
				if( empty($value) )
					$this->pointLifetime = \LWS\Adminpanel\Duration::void();
				else if( is_a($value, '\LWS\Adminpanel\Duration') )
					$this->pointLifetime = $value;
				else if( is_a($value, '\DateInterval') )
					$this->pointLifetime = \LWS\Adminpanel\Duration::fromInterval($value);
				else if( is_string($value) )
					$this->pointLifetime = \LWS\Adminpanel\Duration::fromString($value);
				else if( is_numeric($value) )
					$this->pointLifetime = \LWS\Adminpanel\Duration::days($value);
				else
					$this->pointLifetime = \LWS\Adminpanel\Duration::void();
				break;
			case 'transactional_expiry':
				$this->transactionalExpiry = self::transactionalExpiryFromValue($value);
				break;
			case 'clamp_level':
				$this->clampLevel = boolval($value);
				break;
			case 'confiscation':
				$this->confiscation = boolval($value);
				break;
			case 'roles':
				$this->roles = (is_array($value) ? $value : (empty($value) ? array() : array($value)));
				break;
			case 'denied_roles':
				$this->deniedRoles = (is_array($value) ? $value : (empty($value) ? array() : array($value)));
				break;
			case 'symbol':
				$this->symbol = intval($value);
				break;
			case 'point_name_singular':
				if( !isset($this->pointName) || !is_array($this->pointName) )
					$this->pointName = array('singular' => $value, 'plural' => '');
				else
					$this->pointName = array_merge($this->pointName, array('singular' => $value));
				break;
			case 'point_name_plural':
				if( !isset($this->pointName) || !is_array($this->pointName) )
					$this->pointName = array('singular' => '', 'plural' => $value);
				else
					$this->pointName = array_merge($this->pointName, array('plural' => $value));
				break;
			case 'point_format':
				$this->pointFormat = trim($value);
				break;
			case 'thousand_sep':
				$this->thousandSep = str_replace(' ', ' ',$value); // replace normal space by a breaking space
				break;
			case 'best_unlock':
				if( \in_array($value, array('off', 'on', 'use_all_points', 'and_loop')) )
					$this->bestUnlock = $value;
				else
					$this->bestUnlock = boolval($value) ? 'on' : 'off';
				break;
			case 'prevent_unlock_before':
			case 'prevent_unlock':
				if( \is_a($value, '\DateTime') )
					$this->preventUnlock = $value;
				else if( !$value )
					$this->preventUnlock = false;
				else if( 'on' == strtolower($value) )
					$this->preventUnlock = true;
				else
					$this->preventUnlock = \date_create($value);
				break;
			case 'direct_reward_max_percent_of_cart':
				$value = \str_replace(',', '.', $value);
				if( \is_numeric($value) )
					$this->drmMaxPercentOfCart = \max(0.0, \min(100.0, \floatval($value)));
				break;
			case 'direct_reward_max_points_on_cart':
				if (\is_numeric($value))
					$this->drmMaxPointsOnCart = \max(0, \intval($value));
				break;
			case 'direct_reward_total_floor':
				$value = \str_replace(',', '.', $value);
				if( \is_numeric($value) )
					$this->drmTotalFloor = \max(0.0, \floatval($value));
				break;
			case 'direct_reward_min_subtotal':
				$value = \str_replace(',', '.', $value);
				if( \is_numeric($value) )
					$this->drmMinSubtotal = \max(0.0, \floatval($value));
				break;
			case 'loading_order':
				if (\is_numeric($value))
					$this->order = \intval($value);
				break;
			default:
				return false;
		}
		return true;
	}

	/** @param $value false or array with:
	 * * date (false|DateTime) the next trigger date
	 * * period (string|\LWS\Adminpanel\Duration) the reccurency. If string, use DateInterval format.  */
	static function transactionalExpiryFromValue($value)
	{
		$transactionalExpiry = array('date'=>false, 'period'=>\LWS\Adminpanel\Duration::void());

		if( $value && is_array($value) )
		{
			if( isset($value['date']) && $value['date'] )
			{
				$transactionalExpiry['date'] = \is_a($value['date'], '\DateTime') ? $value['date'] : \date_create($value['date']);
			}

			if( isset($value['period']) && $value['period'] )
			{
				if( is_a($value['period'], '\LWS\Adminpanel\Duration') )
					$transactionalExpiry['period'] = $value['period'];
				else if( is_a($value['period'], '\DateInterval') )
					$transactionalExpiry['period'] = \LWS\Adminpanel\Duration::fromInterval($value['period']);
				else
					$transactionalExpiry['period'] = \LWS\Adminpanel\Duration::fromString($value['period']);
			}
		}
		return $transactionalExpiry;
	}

	static function transactionalExpiryToArray($value)
	{
		return array(
			'date'   => $value['date'] ? $value['date']->format('Y-m-d') : '',
			'period' => $value['period']->toString(),
		);
	}

	/** Based on user point, check possible unlockable.
	 *	Based on pool setting, apply it or mail user about a choice.
	 *	@param $user (int) the user who consume its points.
	 *	@return (int) the count of unlock. */
	public function tryUnlock($userId, $force=false)
	{
		if( \is_a($userId, '\WP_User') )
		{
			$user = $userId;
			$userId = $user->ID;
		}
		else
			$user = \get_user_by('ID', $userId);

		if( !($user && $userId) )
		{
			error_log("Unlock reward attempt for unknown user ($userId). Pool ".$this->getId());
			return 0;
		}

		/// this method is kind of hub for all sharing pools triggered by only one
		$sharing = $this->getSharingPools($user, true, true, false);
		if( !\array_sum(array_map('\count', $sharing)) )
			return 0;

		return self::enqueueUnlock($sharing, $user, $force);
	}

	/** Append sharing and start dequeue if not already running.
	 *	Used in case an unlockabable->apply triggers an Event,
	 *	taht Event gives points and then call tryUnlock again
	 *	since current call is not finished. */
	protected static function enqueueUnlock($sharing, $user, $force)
	{
		$uCount = 0;
		static $waiters = array();
		if( !$waiters )
		{
			$waiters[] = array($sharing, $user, $force);
			while( $waiters )
			{
				list($s, $u, $f) = reset($waiters);
				foreach( $s as $type => $pools )
				{
					if( self::T_STANDARD == $type )
						usort($pools, array(\get_class(), 'unlockStandardSort'));
					$pools = \apply_filters('lws_woorewards_unlock_sort', $pools, $type);

					foreach( $pools as $p )
					{
						if( self::T_LEVELLING != $p->type )
							$uCount += $p->tryUnlockStandard($u, $f);
						else
							$uCount += $p->tryUnlockLevelling($u, $f);
					}
				}
				array_shift($waiters);
			}
		}
		else
		{
			$waiters[] = array($sharing, $user, $force);
		}
		return $uCount;
	}

	/** to be used with usort() @see enqueueUnlock
	 * @return negative if $a go before $b. */
	static function unlockStandardSort($a, $b)
	{
		$aMode = $a->getOption('best_unlock', 'off');
		$bMode = $b->getOption('best_unlock', 'off');
		if( $aMode == $bMode )
			return 0;

		static $grades = array( // bigger go first
			'use_all_points' => 90, // best and nothing else
			'on'             => 60, // best and rest for other pools
			'and_loop'       => 20, // all we can in cost desc order, then other pools
			'off'            => 10, // manual
		);
		if( $aMode == $bMode )
			return 0;
		$aMode = isset($grades[$aMode]) ? $grades[$aMode] : 0;
		$bMode = isset($grades[$bMode]) ? $grades[$bMode] : 0;
		return $bMode - $aMode;
	}

	protected function tryUnlockStandard($user, $force=false)
	{
		$uCount = 0;
		$tryUnlock = $force || !$this->isUnlockPrevented();
		$bestUnlock = $this->getOption('best_unlock', 'off');

		while( $tryUnlock )
		{
			if( !\apply_filters('lws_woorewards_use_can_unlock_reward', true, $user, $this, $force) )
				break;

			$tryUnlock = false;
			$points = $this->getPoints($user->ID);
			$availables = $this->getGrantedLocalUnlockables($points, $user);

			if( $availables->count() > 0 )
			{
				if ($bestUnlock != 'off')
				{
					if ($availables->count() > 1)
						$availables = $this->getBestUnlockable($availables, true, $user->ID);

					$unlockable = $availables->last();
					if( $this->_applyUnlock($user, $unlockable) )
					{
						$tryUnlock = $this->_payAndContinue($user->ID, $unlockable) && ('on' != $bestUnlock);
						$uCount++;
					}
				}
				else
				{
					// send mail
					$mailTemplate = 'wr_available_unlockables';
					if( !empty(\get_option('lws_woorewards_enabled_mail_'.$mailTemplate, 'on')) && $this->isUserUnlockStateChanged($user, true) )
					{
						\LWS_WooRewards_Pro::delayedMail($this->getStackId(), $user->user_email, $mailTemplate, array(
								'user'        => $user,
								'points'      => $points,
								'pool'        => $this,
								'unlockables' => $availables
							)
						);
					}
				}
			}
		}
		return $uCount;
	}

	protected function tryUnlockLevelling($user, $force=false)
	{
		$uCount = 0;
		if( $force || !$this->isUnlockPrevented() )
		{
			if( \apply_filters('lws_woorewards_use_can_unlock_reward', true, $user, $this, $force) )
			{
				$points = $this->getPoints($user->ID);
				$done = \get_user_meta($user->ID, 'lws-loyalty-done-steps', false);
				$availables = $this->getGrantedLocalUnlockables($points, $user);

				if( 'off' != $this->getOption('best_unlock', 'off') )
					$availables = $this->getBestUnlockable($availables, false, $user->ID);

				foreach( $availables->asArray() as $unlockable )
				{
					// if user not already got it
					if( !in_array($unlockable->getId(), $done) )
					{
						if( $this->_applyUnlock($user, $unlockable) )
							$uCount++;
						// trace
						$this->setPoints($user->ID, $this->getPoints($user->ID), $unlockable->getRawReason(), $unlockable);
						\add_user_meta($user->ID, 'lws-loyalty-done-steps', $unlockable->getId(), false);
					}
				}
			}
		}
		return $uCount;
	}

	/** @return unlockable collection with only the best.
	 * @param $single (bool) if false, on tie, return all of them. if true tie is break arbitrarily */
	public function getBestUnlockable($availables, $single=true, $userId=0)
	{
		$best = array(-1, array());
		foreach( $availables->asArray() as $unlockable )
		{
			$cost = \method_exists($unlockable, 'getUserCost') ? $unlockable->getUserCost($userId) : $unlockable->getCost('pay');
			if( $best[0] < $cost )
			{
				$best[0] = $cost;
				$best[1] = array($unlockable);
			}
			else if( $best[0] == $cost )
				$best[1][] = $unlockable;
		}
		$collection = \LWS\WOOREWARDS\Collections\Unlockables::instanciate();
		if( $best[1] )
		{
			if( $single )
				$collection->add(\array_pop($best[1]));
			else foreach( $best[1] as $unlockable )
				$collection->add($unlockable);
		}
		return $collection;
	}

	public function getSharedUnlockables()
	{
		$stack = $this->getStackId();
		$unlockables = array();
		foreach( $this->getSharablePools()->asArray() as $pool )
		{
			if( $stack == $pool->getStackId() )
				$unlockables = array_merge($unlockables, $pool->unlockables->asArray());
		}
		return $unlockables;
	}

	/** Get all pool using the same stackId, including this.
	 * If $user (int|WP_User) is set, test if useCan */
	public function getSharedUnlockableCount($user = false)
	{
		$stack = $this->getStackId();
		$count = 0;
		foreach( $this->getSharablePools()->asArray() as $pool )
		{
			if( $stack == $pool->getStackId() && (!$user || $pool->userCan($user)) )
			{
				$count += $pool->unlockables->count();
			}
		}
		return $count;
	}

	/** If $user (int|WP_User) is set, test if useCan */
	public function getGrantedLocalUnlockables($points, $user=null)
	{
		$availables = $this->unlockables->filter(function($item)use($points, $user){
			return $item->isPurchasable($points, \is_numeric($user) ? $user : $user->ID);
		});
		return $availables->sort();
	}

	/** Return all loaded pool sharing the same stack.
	 *	@param $user (false|int|WP_User) if set, test if userCan() on each pool
	 *	@return array of array, pools are sorted by type */
	public function getSharingPools($user=false, $buyableOnly=false, $withMe=true, $withDirectDiscount=true)
	{
		$sharing = array(
			self::T_LEVELLING => array(), // level first since it consumes no points
			self::T_STANDARD  => array(),
		);
		$pools = $this->getSharablePools()->filterByStackId($this->getStackId());
		foreach( $pools->sort()->asArray() as $pool )
		{
			if( $withMe || $pool->getId() != $this->getId() )
			{
				if( !$withDirectDiscount && $pool->directRewardMode )
					continue;

				if( !$user || $pool->userCan($user) )
				{
					if( !$buyableOnly || $pool->isBuyable() )
						$sharing[$pool->type][$pool->getId()] = $pool;
				}
			}
		}
		return \apply_filters('lws_woorewards_sharing_pools_about_to_redeem', $sharing);
	}

	/** Override to get the unlockable of all pool sharing the same point stack.
	 * If $user (int|WP_User) is set, test if useCan */
	public function _getGrantedUnlockables($points, $user=null)
	{
		$availables = \LWS\WOOREWARDS\Collections\Unlockables::instanciate();
		$stack = $this->getStackId();

		foreach( $this->getSharablePools()->asArray() as $pool )
		{
			if( $stack == $pool->getStackId() && (!$user || $pool->userCan($user)) )
			{
				foreach( $pool->unlockables->asArray() as $unlockable )
				{
					if( $unlockable->isPurchasable($points, \is_numeric($user) ? $user : $user->ID) )
					{
						$availables->add($unlockable, $unlockable->getId());
					}
				}
			}
		}

		return $availables->sort();
	}

	/** @return a collection of pool that can share the same point stack. */
	protected function getSharablePools()
	{
		return \LWS_WooRewards_Pro::getBuyablePools();
	}

	/** do not pay on levelling mode */
	protected function _payAndContinue($userId, &$unlockable)
	{
		if( $this->getOption('type') != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING )
		{
			if( 'use_all_points' == $this->getOption('best_unlock', 'off') )
				$cost = $this->getPoints($userId);
			else
				$cost = \method_exists($unlockable, 'getUserCost') ? $unlockable->getUserCost($userId) : $unlockable->getCost('pay');
			if( $cost > 0 )
				$this->usePoints($userId, $cost, $unlockable->getRawReason(), $unlockable);
			return ($cost > 0);
		}
		else
			return false;
	}

	/** Try to apply a specific Unlockable.
	 *	User HAVE to have enought point.
	 * @return (bool) if something is unlocked. */
	public function unlock($user, $unlockable, $force=false)
	{
		if( empty($user) )
		{
			error_log("Unlock reward attempt for unknown user. Pool ".$this->getId());
			return false;
		}
		if( empty($unlockable) )
		{
			error_log("Undefined Unlock reward attempt for user:".$user->ID." / Pool ".$this->getId());
			return false;
		}
		if( false === $this->unlockables->find($unlockable) )
		{
			error_log("Unlock reward attempt for user(".$user->ID.") for unlockable(".$unlockable->getId().") that do not belong to the pool:".$this->getId());
			return false;
		}
		if( !$this->isBuyable() )
			return false;
		if( $this->isUnlockPrevented() && !$force )
			return false;
		if( !\apply_filters('lws_woorewards_use_can_unlock_the_reward', true, $user, $this, $force, $unlockable) )
			return false;

		$points = $this->getPoints($user->ID);
		if( $unlockable->isPurchasable($points, $user->ID) )
		{
			if( $this->_applyUnlock($user, $unlockable) )
			{
				$this->_payAndContinue($user->ID, $unlockable);

				if( $this->getOption('type') == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING )
				{
					// trace
					$this->setPoints($user->ID, $this->getPoints($user->ID), $unlockable->getRawReason(), $unlockable);
					\add_user_meta($user->ID, 'lws-loyalty-done-steps', $unlockable->getId(), false);
				}
				return true;
			}
		}
		return false;
	}

	/**	Override: could udate value to clamp point total on next level.
	 *	Add points to the pool point stack of a user.
	 *	@param $user (int) the user earning points.
	 *	@param $value (int) final number of point earned.
	 *	@param $reason (string) optional, the cause of the earning.
	 *	@param $origin (Event) optional, the source Event. */
	public function addPoints($userId, $value, $reason='', \LWS\WOOREWARDS\Abstracts\Event $origin=null, $origin2=false)
	{
		if( $this->getOption('clamp_level') )
		{
			$current = $this->getPoints($userId);
			$points = $current + $value;
			$done = \get_user_meta($userId, 'lws-loyalty-done-steps', false);

			$rest = array();
			foreach( $this->_getGrantedUnlockables($points, $userId)->asArray() as $unlockable )
			{
				if( !in_array($unlockable->getId(), $done) )
					$rest[] = $unlockable;
			}

			if( $rest )
			{
				$clamp = $this->getOption('best_unlock') != 'off' ? array_pop($rest) : array_shift($rest);
				$cost = $clamp->getUserCost($userId);
				if( $cost != $points )
				{
					$value = $cost - $current;
					// mark it to be understandable by users
					if( is_string($reason) )
					{
						if( $reason )
							$reason .= ' ';
						$reason .= sprintf(__("(%+d reduced to level)", 'woorewards-pro'), $value);
					}
				}
			}
		}
		return parent::addPoints($userId, $value, $reason, $origin, $origin2);
	}

	protected function _applyUnlock($user, &$unlockable)
	{
		$done = parent::_applyUnlock($user, $unlockable);
		if( $done )
			$this->saveUserUnlockState($user, true);
		return $done;
	}

	function saveUserUnlockState($user, $reset=false)
	{
		$userId = is_numeric($user) ? $user : $user->ID;
		if( $reset )
			\update_user_meta($userId, 'lws_woorewards_unlock_state_hash_'.$this->getId(), '');
		else
			\update_user_meta($userId, 'lws_woorewards_unlock_state_hash_'.$this->getId(), $this->getUserUnlockState($user));
		return $this;
	}

	/** @param $saveOnChanged (bool) if changed, the state is updated but return as changed anyway. */
	function isUserUnlockStateChanged($user, $saveOnChanged=false)
	{
		$userId = is_numeric($user) ? $user : $user->ID;
		$old = \get_user_meta($userId, 'lws_woorewards_unlock_state_hash_'.$this->getId(), true);
		$new = $this->getUserUnlockState($user);
		if( $old != $new )
		{
			if( $saveOnChanged )
				\update_user_meta($userId, 'lws_woorewards_unlock_state_hash_'.$this->getId(), $new);
			return true;
		}
		return false;
	}

	protected function getUserUnlockState($user)
	{
		$points = $this->getPoints($user->ID);
		$availables = $this->_getGrantedUnlockables($points, $user);
		$options = defined('JSON_PARTIAL_OUTPUT_ON_ERROR') ? JSON_PARTIAL_OUTPUT_ON_ERROR : 0;
		$json = json_encode($availables->asArray(), $options, 3);
		$hash = md5($json);
		return $hash;
	}

	/** Provided for convenience.
	 * Try to find the pool in the already loaded ones,
	 * Then thy to load
	 * Else return false.
	 * @param $reference (string|int) can be pool name, then Id */
	static function getOrLoad($reference, $deep=true)
	{
		$pool = \LWS_WooRewards_Pro::getLoadedPools()->find($reference);
		if( $pool )
			return $pool;

		static $cache = array();
		if( isset($cache[$reference]) && ($cache[$reference]['deep'] || !$deep) )
			return $cache[$reference]['pool'];

		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('name'=>$reference, 'deep'=>$deep))->last();
		if( $pool )
		{
			$cache[$reference] = array('deep' => $deep, 'pool' => $pool);
			return $pool;
		}

		if( $reference = intval($reference) )
		{
			$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('p'=>$reference, 'deep'=>$deep))->last();
			if( $pool )
			{
				$cache[$reference] = array('deep' => $deep, 'pool' => $pool);
				return $pool;
			}
		}

		return false;
	}

	/** @return (string) symbol or point name html */
	public function getPointSymbol($count=0)
	{
		$value = $this->getOption('symbol_image');
		if( !$value )
			$value = $this->getOption('disp_point_name_'.($count==1 ? 'singular' : 'plural'));
		if( !$value && $count != 1 )
			$value = $this->getOption('disp_point_name_singular');
		return $value;
	}

	/** override: remove prefabs deletion restriction since wizard. */
	public function isDeletable()
	{
		return true;
	}
}
