<?php
namespace LWS\WOOREWARDS;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** satic class to manage activation and version updates. */
class Updater
{
	private static $defaultStackId = 'default';

	/** @return array[ version => changelog ] */
	static function getNotices()
	{
		$notes = array();
/*
		$notes['3.3'] = <<<EOT
		<b>MyRewards 3.3</b><br/>
		<ul><li><b>New Feature : </b> Possibility to attribute points on order 'Complete' instead of 'Processing'</li></ul>
EOT;
		$notes['3.4'] = <<<EOT
		<b>MyRewards 3.4</b><br/>
		<ul>
			<li><b>New Feature : </b> Show Points widget : Display a message if customer not connected</li>
			<li><b>MyRewards is now fully compatible with WPML </b></li>
		</ul>
EOT;
*/
		return $notes;
	}


	/** First use */
	static function activate()
	{
		// repeated here since WooCommerce could be activated afterward
		// and re-activate this will resolve WC role missing capacity problem
		self::addCapacity();
	}

	static function checkUpdate()
	{
		$wpInstalling = \wp_installing();
		\wp_installing(true); // should force no cache

		$oldVersion = \get_option('lws_woorewards_version', '0');
		if( version_compare($oldVersion, LWS_WOOREWARDS_VERSION, '<') )
		{
			\wp_suspend_cache_invalidation(false);
			self::update($oldVersion, LWS_WOOREWARDS_VERSION);
			update_option('lws_woorewards_version', LWS_WOOREWARDS_VERSION);
			self::notice($oldVersion, LWS_WOOREWARDS_VERSION);
		}

		\wp_installing($wpInstalling);
	}

	static function notice($fromVersion, $toVersion)
	{
		if( version_compare($fromVersion, '1.0', '>=') )
		{
			$notices = self::getNotices();
			$text = '';
			foreach($notices as $version => $changelog)
			{
				if( version_compare($fromVersion, $version, '<') && version_compare($version, $toVersion, '<=') ) // from < v <= new
					$text .= "<p>{$changelog}</p>";
			}
			if( !empty($text) )
				\lws_admin_add_notice('woorewards-lite'.'-changelog-'.$toVersion, $text, array('level'=>'info', 'forgettable'=>true, 'dismissible'=>true));
		}

		/*
		if( !(defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED) )
		{
			$url = \apply_filters('lws_adm_menu_license_url', false, LWS_WOOREWARDS_PAGE, 'woorewards.system');
			if( !$url )
				$url = \add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.system', 'tab'=>'lic'), admin_url('admin.php'));
			$a = array(
				'licence' => array(
					'name' => _x("here", 'link to licence tab in admin notice', 'woorewards-lite'),
					'href' => \esc_attr($url),
				),
				'order' => array(
					'name' => _x("Long Watch Studio - MyRewards", 'link to plugin selling site', 'woorewards-lite'),
					'href' => "https://plugins.longwatchstudio.com/product/woorewards/",
				)
			);
			$note = __("<p>You just installed or updated the <b>MyRewards Lite</b> version.</p>", 'woorewards-lite');
			$note .= sprintf(
				__("<p>Do you have a <b>licence key</b> (for Trial or Pro version)? Think about setting it %s and discover all the possibilities of MyRewards.</p>", 'woorewards-lite'),
				"<a href='{$a['licence']['href']}'>{$a['licence']['name']}</a>"
			);
			$note .= sprintf(
				__("<p>Or try the Trial version for free by ordering it on %s.</p>", 'woorewards-lite'),
				"<a href='{$a['order']['href']}'>{$a['order']['name']}</a>"
			);
			\lws_admin_add_notice('woorewards_first_licence', $note, array('level'=>'info', 'forgettable'=>true));
		}
		*/
	}

	/** Update
	 * @param $fromVersion previously registered version.
	 * @param $toVersion actual version. */
	static function update($fromVersion, $toVersion)
	{
		global $wpdb;

		if( version_compare($fromVersion, '2.6.6', '<') )
		{
			self::addCapacity();
			\update_option('lws_woorewards_redirect_to_licence', 1);
		}

		self::updateDatabaseTables();

		if( version_compare($fromVersion, '3.0.0', '<') )
		{
			\wp_clear_scheduled_hook('lws_woorewards_coupon_reminder_event'); // replaced by 'lws_woorewards_daily_event'

			if( !(defined('LWS_WIZARD_SUMMONER') && LWS_WIZARD_SUMMONER) )
				self::addDefaultPools();

			self::databaseMigrationv2v3();
			self::optionMigrationv2v3();

			// Convert each shop_order postmeta 'lws_woorewards_validate_order' to new event <once> mark
			foreach( array('lws_woorewards_events_firstorder', 'lws_woorewards_events_orderamount', 'lws_woorewards_events_ordercompleted') as $meta ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) SELECT s.post_id, %s, s.meta_value FROM {$wpdb->postmeta} AS s WHERE s.meta_key='lws_woorewards_validate_order'", $meta));
			}
		}

		if( version_compare($fromVersion, '3.13.2', '<') )
		{
			self::updateNamesWPML();
		}

		if( version_compare($fromVersion, '4.0.0', '<') )
		{
			self::updateProductReviewEvent(); // product review is now in free
			self::updateDefaultPoolEvents();

			\do_action('lws_adminpanel_licenses_migration', 'woorewards');

			// badly named option mirgated from bool to list
			$status = \get_option('lws_woorewards_points_distribution_status', false);
			if( false === $status )
			{
				$status = array('processing', 'completed');
				if( \get_option('lws_woorewards_coupon_state' , false) ) // checked: Points on 'Complete' order only
					$status = array('completed');
				\update_option('lws_woorewards_points_distribution_status', $status);
			}

			self::eventPoolNameToId();
		}

		// woorewards is based on woocommerce coupons, so enable them
		\update_option('woocommerce_enable_coupons', 'yes');
		\update_option('lws_woorewards_ignore_woocommerce_disable_coupons', '');
	}

	private static function eventPoolNameToId()
	{
		global $wpdb;
		$sql = <<<EOT
SELECT p.post_name, m.post_id
FROM {$wpdb->postmeta} as m
INNER JOIN {$wpdb->posts} as e ON m.post_id=e.ID
INNER JOIN {$wpdb->posts} as p ON p.ID=e.post_parent
WHERE m.`meta_key`='wre_event_type'
AND m.`meta_value` = 'lws_woorewards_events_productreview'
EOT;
		$events = $wpdb->get_results($sql);
		foreach( $events as $event )
		{
			$up = <<<EOT
UPDATE {$wpdb->usermeta}
SET `meta_key`='lws_wre_event_review_{$event->post_id}'
WHERE `meta_key`='lws_wre_event_review_{$event->post_name}'
EOT;
			$wpdb->query($up); // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	public static function log($msg)
	{
		if( !empty($msg) )
			error_log($msg);
	}

	private static function updateProductReviewEvent()
	{
		global $wpdb;
		$wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = 'lws_woorewards_events_productreview' WHERE meta_value = 'lws_woorewards_pro_events_productreview'");
	}

	private static function updateDefaultPoolEvents()
	{
		$pools = self::loadStandardPool(true);
		if( $pools->count() <= 0 )
			return;
		$pool = $pools->get(0);
		if( 1 != $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_productreview';})->count() )
		{
			require_once LWS_WOOREWARDS_INCLUDES.'/events/productreview.php';
			$event = new \LWS\WOOREWARDS\Events\ProductReview();
			$pool->addEvent($event, 0);
			$pool->save();
		}
	}

	private static function updateDatabaseTables()
	{
		global $wpdb;
		/// Alter table historic: Add stack field
		$thistoric = $wpdb->prefix.'lws_wr_historic';
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE $thistoric (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			stack varchar(255) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '' COMMENT 'Each pool can have its own or share point stack',
			mvt_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			points_moved int(10) NULL DEFAULT NULL,
			new_total int(20) NULL DEFAULT NULL,
			commentar text NOT NULL,
			`origin` tinytext NOT NULL DEFAULT '' COMMENT 'eg. unlockable/event post id (max 255 char)',
			`origin2` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Additionnal info. eg. user_id that trigger the event origin',
			`order_id` INT(20) NULL DEFAULT NULL COMMENT 'If about a wc_order = post.ID',
			`blog_id` INT(20) NULL DEFAULT NULL COMMENT 'For multisite, the current blog during operation',
			PRIMARY KEY id  (id),
			KEY `user_id` (`user_id`),
			KEY `stack` (`stack`)
			) $charset_collate;";

		$tSuccess = $wpdb->prefix.'lws_wr_achieved_log';
		$sqlAchieved = "CREATE TABLE $tSuccess (
			`id` bigint(30) NOT NULL AUTO_INCREMENT,
			`user_id` bigint(20) NOT NULL COMMENT 'recipient user id',
			`creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`display` TIMESTAMP NULL DEFAULT NULL,
			`popup` int(1) NOT NULL DEFAULT 1 COMMENT 'Is the achievement must pop to the user page or (false) only for log purpose',
			`title` text NOT NULL DEFAULT '',
			`message` text NOT NULL DEFAULT '',
			`image` text NOT NULL DEFAULT '' COMMENT 'Achievement icon, if no url given, a default image is picked',
			`background` text NOT NULL DEFAULT '' COMMENT 'Achievement icon background, if no url given, a default image is picked',
			`badge_id` bigint(20) NULL DEFAULT NULL COMMENT 'The source badge if any',
			`origin` tinytext NOT NULL DEFAULT '' COMMENT 'source of the achievement',
			PRIMARY KEY id  (id),
			KEY `user_id` (`user_id`),
			KEY `display` (`display`)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		ob_start(array(get_class(), 'log')); // dbDelta could write on standard output
		dbDelta( $sql );
		dbDelta( $sqlAchieved );
		ob_end_flush();
	}

	/** Point history table migration. */
	private static function databaseMigrationv2v3()
	{
		global $wpdb;
		$thistoric = $wpdb->prefix.'lws_wr_historic';

		$default = !empty(self::$defaultStackId) ? self::$defaultStackId : 'default';

		$wpdb->query("ALTER TABLE {$thistoric} CHANGE points_moved points_moved SMALLINT(10) NULL DEFAULT NULL;"); // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query($wpdb->prepare("UPDATE {$thistoric} SET stack=%s WHERE stack=''", $default)); // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$tmeta = $wpdb->usermeta;
		$mkey = \LWS\WOOREWARDS\Core\PointStack::MetaPrefix;
		// this query only works on MySql, we should only update last entry per user but it is ok like this
		$copyPts = "UPDATE $thistoric INNER JOIN $tmeta ON $thistoric.user_id=$tmeta.user_id AND $tmeta.meta_key='lws_wr_points' SET new_total=$tmeta.meta_value";
		if( false !== $wpdb->query($copyPts) ) // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		{
			// point usermeta key renamed
			$wpdb->query($wpdb->prepare("UPDATE {$tmeta} SET meta_key=%s WHERE meta_key='lws_wr_points'", $mkey . $default)); // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	private static function optionMigrationv2v3()
	{
		// mail common settings
		$prefix = 'lws_mail_'.'woorewards'.'_attribute_';
		\add_option($prefix.'headerpic', \get_option('lws_woorewards_mail_attribute_headerpic', ''));
		\add_option($prefix.'footer',    \get_option('lws_woorewards_mail_attribute_footertext', ''));

		// mail 'wr_new_reward'
		$suffix = 'wr_new_reward';
		\add_option('lws_mail_subject_' .$suffix, \get_option('lws_woorewards_mail_subject_newcoupon', ''));
		\add_option('lws_mail_title_'   .$suffix, \get_option('lws_woorewards_mail_title_newcoupon', ''));
		\add_option('lws_mail_header_'  .$suffix, \get_option('lws_woorewards_mail_header_newcoupon', ''));
		\add_option('lws_mail_template_'.$suffix, \get_option('lws_woorewards_mail_template_newcoupon', ''));
	}

	/** Add 'manage_rewards' capacity to 'administrator' and 'shop_manager'. */
	private static function addCapacity()
	{
		$cap = 'manage_rewards';
		foreach( array('administrator', 'shop_manager') as $slug )
		{
			$role = \get_role($slug);
			if( !empty($role) && !$role->has_cap($cap) )
			{
				$role->add_cap($cap);
			}
		}
	}

	/** Add pool "loyalty system -> standard" */
	public static function addDefaultPools()
	{
		$pools = self::loadStandardPool(false);

		if( $pools->count() <= 0 )
		{
			$name = 'default';
			if( \is_multisite() )
				$name .= \get_current_blog_id();
			// create the default pool for free version
			$pool = $pools->create($name)->last();
			$pool->setOptions(array(
				'type'      => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
				'disabled'  => true,
				'title'     => __("Standard System", 'woorewards-lite'),
				'whitelist' => array(\LWS\WOOREWARDS\Core\Pool::T_STANDARD)
			));

			// order amount
			require_once LWS_WOOREWARDS_INCLUDES.'/events/orderamount.php';
			$event = new \LWS\WOOREWARDS\Events\OrderAmount();
			$event->setDenominator(\get_option('lws_woorewards_value', 1));
			$pool->addEvent($event, intval(\get_option('lws_woorewards_points', 0)));

			// order completed
			require_once LWS_WOOREWARDS_INCLUDES.'/events/ordercompleted.php';
			$event = new \LWS\WOOREWARDS\Events\OrderCompleted();
			$pool->addEvent($event, intval(\get_option('lws_woorewards_rewards_orders', 0)));
			// first order
			require_once LWS_WOOREWARDS_INCLUDES.'/events/firstorder.php';
			$event = new \LWS\WOOREWARDS\Events\FirstOrder();
			$pool->addEvent($event, intval(\get_option('lws_woorewards_rewards_orders_first', 0)));
			// product review
			require_once LWS_WOOREWARDS_INCLUDES.'/events/productreview.php';
			$event = new \LWS\WOOREWARDS\Events\ProductReview();
			$pool->addEvent($event, 0);

			// coupon
			require_once LWS_WOOREWARDS_INCLUDES.'/unlockables/coupon.php';
			$coupon = new \LWS\WOOREWARDS\Unlockables\Coupon();
			$coupon->setValue(\get_option('lws_woorewards_value_coupon', ''));
			$coupon->setTimeout(\get_option('lws_woorewards_expiry_days', ''));
			$pool->addUnlockable($coupon, intval(\get_option('lws_woorewards_stage', 0)));

			if( $fromV2 = (false !== \get_option('lws_woorewards_value', false)) )
				$pool->setOption('public', \LWS_WooRewards::isWC());

			$pool->save();
			if( !empty($pool->getId()) ) // not deletable
			{
				\clean_post_cache($pool->getId());
				\update_post_meta($pool->getId(), 'wre_pool_prefab', 'yes');
				\update_option('lws_wr_default_pool_name', $pool->getName());
				self::$defaultStackId = $pool->getStackId();

				if($fromV2)
				{
					// remove outdated settings
					\delete_option('lws_woorewards_points');
					\delete_option('lws_woorewards_value');
					\delete_option('lws_woorewards_rewards_orders');
					\delete_option('lws_woorewards_rewards_orders_first');
					\delete_option('lws_woorewards_value_coupon');
					\delete_option('lws_woorewards_expiry_days');
					\delete_option('lws_woorewards_stage');
				}
			}

			if($fromV2)
			{
				\lws_admin_add_notice(
					'up-lws-v2-settings',
					__("Your settings have been migrated during MyRewards update.
We tried our best to conserve the same behavior as before but we advise you to check them anyway.", 'woorewards-lite'),
					array(
						'level' => 'success',
						'once' => false,
						'forgettable' => true
					)
				);
			}
		}
	}

	/** Could be on purpose after a downgrade from trial to free version. */
	public static function isMissingPrefabEventsAndUnlockables()
	{
		$pools = self::loadStandardPool(true);
		if( $pools->count() <= 0 )
			return true;
		$pool = $pools->get(0);
		if( 1 != $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_orderamount';})->count() )
			return true;
		if( 1 != $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_ordercompleted';})->count() )
			return true;
		if( 1 != $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_firstorder';})->count() )
			return true;
		if( 1 != $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_productreview';})->count() )
			return true;
		if( 1 != $pool->getUnlockables()->filter(function($item){return $item->getType() == 'lws_woorewards_unlockables_coupon';})->count() )
			return true;
		return false;
	}

	/** Look at pool prefabs type='standard',
	 * add missing orderCompleted, firstOrder, OrderAmount and Coupon.
	 *
	 * Could be on purpose after a downgrade from trial to free version. */
	public static function addMissingPrefabEventsAndUnlockables()
	{
		$pools = self::loadStandardPool(true);
		if( $pools->count() <= 0 )
		{
			self::addDefaultPools();
		}
		else
		{
			$pool = $pools->get(0);
			$dirty = false;

			$e = $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_orderamount';});
			while( $e->count() > 1 )
			{
				$item = $e->last();
				$e->remove($item);
				$pool->removeEvent($item);
				$item->delete();
			}
			if( $e->count() <= 0 )
			{
				require_once LWS_WOOREWARDS_INCLUDES.'/events/orderamount.php';
				$event = new \LWS\WOOREWARDS\Events\OrderAmount();
				$pool->addEvent($event, 0);
				$dirty = true;
			}

			$e = $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_ordercompleted';});
			while( $e->count() > 1 )
			{
				$item = $e->last();
				$e->remove($item);
				$pool->removeEvent($item);
				$item->delete();
			}
			if( $e->count() <= 0 )
			{
				require_once LWS_WOOREWARDS_INCLUDES.'/events/ordercompleted.php';
				$pool->addEvent(new \LWS\WOOREWARDS\Events\OrderCompleted(), 0);
				$dirty = true;
			}

			$e = $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_firstorder';});
			while( $e->count() > 1 )
			{
				$item = $e->last();
				$e->remove($item);
				$pool->removeEvent($item);
				$item->delete();
			}
			if( $e->count() <= 0 )
			{
				require_once LWS_WOOREWARDS_INCLUDES.'/events/firstorder.php';
				$pool->addEvent(new \LWS\WOOREWARDS\Events\FirstOrder(), 0);
				$dirty = true;
			}

			$e = $pool->getEvents()->filter(function($item){return $item->getType() == 'lws_woorewards_events_productreview';});
			while( $e->count() > 1 )
			{
				$item = $e->last();
				$e->remove($item);
				$pool->removeEvent($item);
				$item->delete();
			}
			if( $e->count() <= 0 )
			{
				require_once LWS_WOOREWARDS_INCLUDES.'/events/productreview.php';
				$pool->addEvent(new \LWS\WOOREWARDS\Events\ProductReview(), 0);
				$dirty = true;
			}

			$u = $pool->getUnlockables()->filter(function($item){return $item->getType() == 'lws_woorewards_unlockables_coupon';});
			while( $e->count() > 1 )
			{
				$item = $u->last();
				$u->remove($item);
				$pool->removeUnlockable($item);
				$item->delete();
			}
			if( $u->count() <= 0 )
			{
				require_once LWS_WOOREWARDS_INCLUDES.'/unlockables/coupon.php';
				$pool->addUnlockable(new \LWS\WOOREWARDS\Unlockables\Coupon(), 0);
				$dirty = true;
			}

			if( $dirty )
			{
				$pool->save();
			}
		}
	}

	/** @return a pool collection */
	protected static function loadStandardPool($deep=false)
	{
		// if not already exists (prefabs are not deletable)
		$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
			'numberposts' => 1,
			'meta_query'  => array(
				array(
					'key'     => 'wre_pool_prefab',
					'value'   => 'yes', // This cannot be empty because of a bug in WordPress
					'compare' => 'LIKE'
				),
				array(
					'key'     => 'wre_pool_type',
					'value'   => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
					'compare' => 'LIKE'
				)
			),
			'deep' => $deep
		));
		return $pools;
	}

	protected static function updateNamesWPML()
	{
		global $wpdb;
		$icl = $wpdb->prefix . 'icl_strings';

		if( $wpdb->get_col("SHOW TABLES LIKE '{$icl}'") )
		{
			$template = 'Woorewards mail - %s - %s';
			$suffixes = array('Subject', 'Preheader', 'Title', 'Header');
			$strings = array(
				"New Reward" => array(
					"Nouvelle r??compense",
					"Nueva recompensa",
					"Neue Belohnung",
					"Uusi palkkio",
					"????????????",
				),
				"Achievement" => array(
					"Succ??s",
					"Erfolg",
					"??????",
					"Logros",
				),
				"Sponsorship" => array(
					"Patrocinio",
					"??????",
					"Parrainage",
					"Sponsorointi",
				),
				"Reward Choice" => array(
					"Eleccion de recompensa",
					"????????????",
					"Belohnungsauswahl",
					"Choix de r??compense",
					"Palkinnon valinta",
				),
				"Expiry Reminder" => array(
					"Recordatorio de caducidad",
					"????????????",
					"Erinnerung f??r Verfallsdatum",
					"Rappel d'expiration",
					"P????ttymisen muistutin",
				),
				"Points Expiry Reminder" => array(
					"Recordatorio de caducidad de puntos",
					"??????????????????",
					"Erinnerung f??r Punkteverfall",
					"Rappel d???expiration de points",
					"P????ttymisen muistutin",
				),
			);

			foreach( $suffixes as $suffix )
			{
				foreach( $strings as $dst => $src )
				{
					$where = array();
					foreach( $src as $trad )
						$where[] = $wpdb->prepare('`name`=%s', sprintf($template, $trad, $suffix)); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

					$sql = sprintf(
						"UPDATE `{$icl}` SET `name`='{$template}' WHERE %s",
						$dst, $suffix, implode(' OR ', $where)
					);
					$wpdb->query($sql); // phpcs:ignore WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
				}
			}
		}
	}
}
