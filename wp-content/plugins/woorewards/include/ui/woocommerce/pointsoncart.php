<?php
namespace LWS\WOOREWARDS\Ui\Woocommerce;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Provide widget in Cart and Checkout page to
 * convert points to some kind of immediate disconut.
 * Show point amount used resume in Cart and Checkout Subtotal.
 * The widget lets user choose the point amount to use on that Cart. */
class PointsOnCart
{
	const CART_ASIDE  = 'cart_collaterals';
	const CART_AFTER  = 'after_products';
	const CART_NONE   = 'not_displayed';

	const CHECKOUT_TOP      = 'top_page';
	const CHECKOUT_CUSTOMER = 'before_customer';
	const CHECKOUT_REVIEW   = 'before_review';
	const CHECKOUT_NONE     = 'not_displayed';

	static function install()
	{
		// global hooks, works on any instance of pool or defined at last minute
		$me = new self();
		\add_filter('lws_adminpanel_stygen_content_get_' . 'lws_woorewards_points_to_cart', array($me, 'template'));
		\add_shortcode('wr_points_on_cart', array($me, 'shortcode'));
		\add_shortcode('wr_max_points_on_cart', array($me, 'shortcodeMaxUsable'));

		\add_action('lws_wr_pool_admin_options_before_update', array($me, 'poolUpdateBefore'), 10, 2);
		\add_action('lws_wr_pool_admin_options_after_update', array($me, 'poolUpdateAfter'), 10, 2);

		// refresh
		\add_action('wp_ajax_lws_woorewards_pointsoncart_bloc_refresh', array($me, 'ajaxRefresh'));
		\add_action('wp', array($me, 'formValidation')); // be sure cart is loaded AND computed

		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));

		/** Ask for a \LWS\WOOREWARDS\Collections\Pools instance */
		$pools = \apply_filters('lws_woorewards_pointsoncart_pools', false);
		if( false === $pools )
			$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, array('system' => 'default'), false);
		if( !$pools )
			return;

		$pools = $pools->filter(array(\get_class(), 'filterPools'));
		if( $pools->count() )
		{
			$cartHook = self::getCartHook();
			$checkoutHook = self::getCheckoutHook();
			if( $cartHook || $checkoutHook )
			{
				foreach( $pools->sort()->asArray() as $pool )
				{
					$dedicated = new self($pool, $cartHook ? $cartHook[0] : false, $checkoutHook ? $checkoutHook[0] : false);
					if( $cartHook )
						\add_action($cartHook[1], array($dedicated, 'inCart'));
					if( $checkoutHook )
						\add_action($checkoutHook[1], array($dedicated, 'inCheckout'));
				}
			}
		}
	}

	static function filterPools($pool)
	{
		if( !$pool->getOption('direct_reward_mode') )
			return false;
		if( !$pool->userCan() )
			return false;
		if( !$pool->isActive() )
			return false;
		return true;
	}

	protected function __construct($pool=false, $positionInCart=false, $positionInCheckout=false)
	{
		$this->pool = $pool;
		$this->positionInCart     = $positionInCart ? $positionInCart : 'not_displayed';
		$this->positionInCheckout = $positionInCheckout ? $positionInCheckout : 'not_displayed';
	}

	function registerScripts()
	{
		\wp_register_script('lws_wr_pointsoncart', LWS_WOOREWARDS_JS.'/pointsoncart.js', array('jquery'), LWS_WOOREWARDS_VERSION, true);
		\wp_register_style('lws_wr_pointsoncart_hard', LWS_WOOREWARDS_CSS.'/pointsoncart.css', array(), LWS_WOOREWARDS_VERSION);
		\wp_register_style('lws_wr_pointsoncart_custom', LWS_WOOREWARDS_CSS.'/templates/pointsoncart.css?stygen=lws_woorewards_points_to_cart_style', array(), LWS_WOOREWARDS_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_script('lws_wr_pointsoncart');
		\wp_enqueue_style('lws_wr_pointsoncart_hard');
		\wp_enqueue_style('lws_wr_pointsoncart_custom');
	}

	protected static function getCartHook()
	{
		$pos = \get_option('lws_woorewards_points_to_cart_pos', self::CART_NONE);

		if( $pos == self::CART_ASIDE )
			return array($pos, 'woocommerce_cart_collaterals');
		else if( $pos == self::CART_AFTER )
			return array($pos, 'woocommerce_after_cart_table');
		else
			return false;
	}

	protected static function getCheckoutHook()
	{
		$pos = \get_option('lws_woorewards_points_to_checkout_pos', self::CHECKOUT_NONE);

		if( $pos == self::CHECKOUT_TOP )
			return array($pos, 'woocommerce_before_checkout_form');
		else if( $pos == self::CHECKOUT_CUSTOMER )
			return array($pos, 'woocommerce_before_checkout_billing_form');
		else if( $pos == self::CHECKOUT_REVIEW )
			return array($pos, 'woocommerce_checkout_before_order_review');
		else
			return false;
	}

	protected function getPositionClass($origin)
	{
		$class = '';
		if( 'shortcode' == $origin )
		{
			$class .= ' wr-pointsoncart-shortcode';
		}
		else if( 'demo' == $origin )
		{
			if( $this->positionInCart == self::CART_ASIDE )
				$class .= ' cross-sells';
		}
		else
		{
			if( $this->positionInCart == self::CART_ASIDE )
				$class .= ' cross-sells wr-action-collateral';
			else if( $this->positionInCart == self::CART_AFTER )
				$class .= ' wr-action-after-cart';

			if( $this->positionInCheckout == self::CHECKOUT_TOP )
				$class .= ' wr-action-before-checkout';
			else if( $this->positionInCheckout == self::CHECKOUT_CUSTOMER )
				$class .= ' wr-action-before-billing';
			else if( $this->positionInCheckout == self::CHECKOUT_REVIEW )
				$class .= ' wr-action-before-order-review';
		}
		return $class;
	}

	/** Update used points by POST instead of Ajax (option 'reload') */
	function formValidation()
	{
		if( isset($_POST['lws_wr_pointsoncart_amount_value']) )
		{
			if( isset($_POST['nonce']) && \wp_verify_nonce($_POST['nonce'], 'lws_woorewards_reserve_pointsoncart') && \is_numeric($_POST['lws_wr_pointsoncart_amount_value']) )
			{
				$points = \intval($_POST['lws_wr_pointsoncart_amount_value']);
				$userId = \get_current_user_id();
				if( $userId && isset($_POST['system']) && ($pool = \sanitize_key($_POST['system'])) )
				{
					$pool = \apply_filters('lws_woorewards_get_pools_by_args', false, array('system' => $pool), $userId)->last();
					if( !$pool )
						return;
					if( !$pool->getOption('direct_reward_mode') )
						return;

					if( !\WC()->cart )
					{
						error_log('Points on Cart value update too soon, WooCommerce cart not init yet.');
						return;
					}
					$coupons = \WC()->cart->get_applied_coupons();

					$stackId = $pool->getStackId();
					$max = $pool->getPoints($userId);
					$points = \max(0, \min($points, $max));
					\update_user_meta($userId, 'lws_wr_points_on_cart_'.$stackId, $points);

					$code = 'wr_points_on_cart-' . $pool->getName();
					if( $points )
					{
						// add coupon if not exists
						if( !\WC()->cart->has_discount($code) )
							\WC()->cart->apply_coupon($code);
					}
					else
					{
						// silently remove coupon if exists
						if( \WC()->cart->has_discount($code) )
							\WC()->cart->remove_coupon($code);
					}

					if( \wp_redirect(\add_query_arg(array())) )
						exit;
				}
			}
		}
	}

	/** keep 'direct_reward_mode' */
	function poolUpdateBefore($pool, $whitelist)
	{
		$this->lastDirectRewardMode = $pool->getOption('direct_reward_mode');
	}

	/** If 'direct_reward_mode' just activated,
	 *	ensure a widget is visible on cart page.
	 */
	function poolUpdateAfter($pool, $whitelist)
	{
		if( isset($this->lastDirectRewardMode) && !$this->lastDirectRewardMode )
		{
			$this->lastDirectRewardMode = $pool->getOption('direct_reward_mode');
			if( $this->lastDirectRewardMode && !$this->getCartHook() )
			{
				\update_option('lws_woorewards_points_to_cart_pos', self::CART_ASIDE);
				\lws_admin_add_notice_once(
					'lws_wr_pointsoncart_auto_pos',
					__("The Points on Cart widget has been placed aside the Cart.", 'woorewards-lite'),
					array('level' => 'info')
				);
			}
		}
	}

	function template()
	{
		$this->stygen = true;
		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create('dummy')->last();
		$pool->setOptions(array(
			'title'    => __("Demo. System", 'woorewards-lite'),
			'type'     => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
			'disabled' => false,
			'direct_reward_point_rate' => 0.1
		));
		$info = \apply_filters('lws_woorewards_pointsoncart_template_info', array(
			'amount' => 150,
			'max'    => 100,
			'used'   => 20,
			'stack'  => 'dummy',
			'pool'   => $pool,
		));
		$content = $this->getContent('demo', $info);
		unset($this->stygen);
		return $content;
	}

	function inCart()
	{
		if( $this->pool )
		{
			if( $info = $this->getInfo($this->pool) )
				echo $this->getContent('cart', $info);
			else
				echo $this->getPlaceholder($this->pool, 'cart');
		}
	}

	function inCheckout()
	{
		if( $this->pool )
		{
			if( $info = $this->getInfo($this->pool) )
				echo $this->getContent('checkout', $info);
			else
				echo $this->getPlaceholder($this->pool, 'checkout');
		}
	}

	function shortcode($atts=array(), $content='')
	{
		$userId = \get_current_user_id();
		if( !$userId )
			return $content;

		$pool = $this->getPool($atts, $userId);
		if( $pool )
		{
			if( $info = $this->getInfo($pool) )
				$content = $this->getContent('shortcode', $info);
			else
				$content = $this->getPlaceholder($pool, 'shortcode');
		}
		return $content;
	}

	/** Echo the html bloc and die.
	 * @param $_GET['action'] (string) "lws_woorewards_pointsoncart_bloc_refresh"
	 * @param $_GET['origin'] (string) given by the DOM `.lws_wr_pointsoncart_bloc[data-origin]` */
	function ajaxRefresh()
	{
		// clean args
		$origin = (isset($_GET['origin']) ? \sanitize_key($_GET['origin']) : '');
		if( !\in_array($origin, array('cart', 'checkout', 'shortcode')) )
			$origin = '';

		$this->positionInCart = $this->positionInCheckout = '';
		if( 'cart' == $origin )
		{
			if( $pos = self::getCartHook() )
				$this->positionInCart = $pos[0];
		}
		else if( 'checkout' == $origin )
		{
			if( $pos = self::getCheckoutHook() )
				$this->positionInCheckout = $pos[0];
		}

		$atts = array();
		foreach (array('system', 'shared', 'force') as $a) {
			if (isset($_GET[$a])) $atts[$a] = \sanitize_text_field($_GET[$a]);
		}
		$pool = $this->getPool($atts);
		if( $pool )
		{
				if( $info = $this->getInfo($pool) )
					echo $this->getContent($origin, $info);
				else
					echo $this->getPlaceholder($pool, $origin);
		}
		// all is done, kill process
		exit;
	}

	protected function getPool($atts, $userId=false)
	{
		if( false === $userId )
			$userId = \get_current_user_id();

		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts, $userId);
		if( !$pools )
			return false;

		$pools = $pools->filter(array(\get_class(), 'filterPools'));
		return $pools->last();
	}

	protected function getMaxPoints(&$pool, &$cart, $userId)
	{
		$rate = $pool->getOption('direct_reward_point_rate');
		if( $rate == 0.0 )
			return 0;
		$points = $pool->getPoints($userId);

		$total = $cart->get_subtotal();
		if( 'yes' === get_option('woocommerce_prices_include_tax') )
			$total += $cart->get_subtotal_tax();

		foreach($cart->get_applied_coupons() as $otherCode)
		{
			if (strpos($otherCode, 'wr_points_on_cart') === false) {
				$total -= $cart->get_coupon_discount_amount($otherCode);
			}
		}
		$max = (int)\ceil($total / $rate);
		$points = \min($max, $points);
		$points = \apply_filters('lws_woorewards_pointdiscount_max_points', $points, $rate, $pool, $userId, $cart);
		return $points;
	}

	/** Format relevant information for partial payment display
	 * @return false|array */
	function getInfo($pool)
	{
		$userId = \get_current_user_id();
		if( !$userId )
			return false;
		if( !\WC()->cart )
			return false;
		if( !$pool )
			return false;

		$stackId = $pool->getStackId();
		$max = $this->getMaxPoints($pool, \WC()->cart, $userId);
		$used = \intval(\get_user_meta($userId, 'lws_wr_points_on_cart_'.$stackId, true));
		if( $used > $max )
		{
			\update_user_meta($userId, 'lws_wr_points_on_cart_'.$stackId, $max);
			$used = $max;
		}

		return array(
			'amount' => $pool->getPoints($userId),
			'max'    => $max,
			'used'   => $used,
			'stack'  => $stackId,
			'pool'   => $pool,
		);
	}

	function shortcodeMaxUsable($atts=array(), $content='')
	{
		$userId = \get_current_user_id();
		if( !$userId )
			return $content;

		$atts = \wp_parse_args($atts, array('raw' => ''));
		$pool = $this->getPool($atts, $userId);

		if( $pool && \WC()->cart )
		{
			$content = $this->getMaxPoints($pool, \WC()->cart, $userId);
			if( !$atts['raw'] )
				$content = \LWS_WooRewards::formatPointsWithSymbol($content, $pool->getName());
		}
		return $content;
	}

	function getPlaceholder($pool, $origin, $content='')
	{
		if( !(isset($this->stygen) && $this->stygen) )
			$this->enqueueScripts();

		$class = '';
		$url = '';
		if( !(isset($this->stygen) && $this->stygen) )
		{
			$class = 'lws_wr_pointsoncart_bloc';
			$url = \esc_attr(\admin_url('/admin-ajax.php'));
		}

		$content = \apply_filters('lws_woorewards_pointsoncart_placeholder', $content, $origin, $this);
		if( $content )
		{
			$class .= ' lwss_selectable lws-wr-pointsoncart ';
			$class .= ($origin=='cart') ? 'cart-pointsoncart' : 'order-pointsoncart' ;
			$class .= $this->getPositionClass($origin);
			$content = \do_shortcode($content);
		}
		else
			$content = '&nbsp;';

		$pool = $pool ? \esc_attr($pool->getName()) : '';
		return "<div class='{$class}' data-origin='{$origin}' data-pool='{$pool}' data-url='{$url}'>{$content}</div>";
	}

	/** Display the partial payment tool on cart or checkout page */
	function getContent($origin, $info)
	{
		if( !(isset($this->stygen) && $this->stygen) )
		{
			if( !\WC()->cart || \WC()->cart->is_empty() )
				return '';
			$this->enqueueScripts();
		}
		$poolInfo = array_merge(array(
			'name' 			=> $info['pool']->getName(),
			'symbol'		=> \LWS_WooRewards::getPointSymbol('1', $info['pool']->getName()),
			'symbols'		=> \LWS_WooRewards::getPointSymbol('2', $info['pool']->getName()),
		), $info['pool']->getOptions(array(
			'direct_reward_point_rate',
			'direct_reward_max_percent_of_cart',
			'direct_reward_max_points_on_cart',
			'direct_reward_total_floor',
			'direct_reward_min_subtotal'
		)));

		$balance = \LWS_WooRewards::formatPoints($info['amount'], $poolInfo['name']);
		$labels = array(
			'balance'     => sprintf(__("Your %s :", 'woorewards-lite'),  $poolInfo['symbols']),
			'use'         => _x("Used Amount :", "Part of Points to use as reward", 'woorewards-lite'),
			'update'      => __("Apply", 'woorewards-lite'),
			'max'         => __("Use Max Amount", 'woorewards-lite'),
		);
		$labels['balance']     = \lws_get_option('lws_woorewards_points_to_cart_action_balance', $labels['balance']);
		$labels['use']         = \lws_get_option('lws_woorewards_points_to_cart_action_use'    , $labels['use']);
		$labels['update']      = \lws_get_option('lws_woorewards_points_to_cart_action_update' , $labels['update']);
		$labels['max']         = \lws_get_option('lws_woorewards_points_to_cart_action_max'    , $labels['max']);
		$labels['use']     = \apply_filters('wpml_translate_single_string', $labels['use']    , 'Widgets', "WooRewards - Points On Cart Action - Use");
		$labels['update']  = \apply_filters('wpml_translate_single_string', $labels['update'] , 'Widgets', "WooRewards - Points On Cart Action - Update");
		$labels['max']     = \apply_filters('wpml_translate_single_string', $labels['max']    , 'Widgets', "WooRewards - Points On Cart Action - Max");

		$esc = array(
			'amount' => \esc_attr($info['amount']),
			'max'    => \esc_attr($info['max']),
			'used'   => \esc_attr($info['used']),
			'update' => \esc_attr($labels['update']),
			'pool'  => \esc_attr($info['pool']->getName()),
			'url'    => \esc_attr(\admin_url('/admin-ajax.php')),
			'nonce'  => \esc_attr(\wp_create_nonce('lws_woorewards_reserve_pointsoncart')),
		);

		$applydisabled = ' disabled';
		$maxdisabled = ($info['used'] < $info['max']) ? '' : ' disabled';
		if( isset($this->stygen) && $this->stygen )
		{
			$applydisabled = '';
			$maxdisabled = '';
		}

		$class = ($origin=='cart') ? 'cart-pointsoncart' : 'order-pointsoncart' ;
		$class .= $this->getPositionClass($origin);
		$reload = \get_option(('cart'==$origin) ? 'lws_woorewards_points_to_cart_reload' : 'lws_woorewards_points_to_checkout_reload') ? 'on' : 'off';
		$header = \lws_get_option('lws_wooreward_points_cart_header', __('Loyalty points discount', 'woorewards-lite'));
		$header = \apply_filters('wpml_translate_single_string', $header, 'Widgets', "WooRewards - Points On Cart Action - Header");

		$details = implode('', $this->getDetailsText($origin, $info, $poolInfo));
		$url = '';
		if( !(isset($this->stygen) && $this->stygen) )
		{
			$class .= ' lws_wr_pointsoncart_bloc';
			$url = \esc_attr(\admin_url('/admin-ajax.php'));
		}

		$content = <<<EOT
		<div class='lwss_selectable lws-wr-pointsoncart {$class}' data-origin='{$origin}' data-pool='{$esc['pool']}' data-url='{$url}' data-type="Main Container">
			<h2>{$header}</h2>
			<div class='lwss_selectable lws-wr-cart lws_wr_pointsoncart_contribution' data-type='Inner Container' data-nonce='{$esc['nonce']}' data-url='{$esc['url']}' data-reload='{$reload}'>
				<div class='lwss_selectable wr-cart-balance' data-type='Balance Container'>
					<div class='lwss_selectable wr-cart-balance-label' data-editable='text' data-id='lws_woorewards_points_to_cart_action_balance' data-type='Balance Label'>
						<span class='lwss_modify_content'>{$labels['balance']}</span>
					</div>
					<div class='lwss_selectable wr-cart-balance-value' data-type='Balance Value'>{$balance}</div>
				</div>
				<div class='lwss_selectable wr-cart-input' data-type='Input Line'>
					<div class='lwss_selectable wr-cart-use-label' data-editable='text' data-id='lws_woorewards_points_to_cart_action_use' data-type='Input Label'>
						<span class='lwss_modify_content'>{$labels['use']}</span>
					</div>
					<div class='lwss_selectable wr-cart-line-input' data-type='Input Area'>
						<input name='lws_wr_pointsoncart_amount_value' autocomplete='off' value='{$esc['used']}' class='lwss_selectable wr-input-amount lws_wr_pointsoncart_amount_value' data-usemax='{$esc['max']}' data-max='{$esc['amount']}' data-type="Amount Input">
					</div>
				</div>
				<div class='lwss_selectable wr-cart-buttons' data-type='Buttons Line'>
					<button data-editable='text' data-id='lws_woorewards_points_to_cart_action_max' type='button' title='{$esc['max']}' class='button lwss_selectable wr-cart-max lws_wr_pointsoncart_use_max_amount'{$maxdisabled} data-type='Max Button'>
						<span class='lwss_modify_content'>{$labels['max']}</span>
					</button>
					<button data-editable='text' data-id='lws_woorewards_points_to_cart_action_update' type='button' class='button lwss_selectable wr-cart-apply lws_wr_pointsoncart_amount_apply' name='update_pointsoncart' value='{$esc['update']}'{$applydisabled} data-type='Update Button'>
						<span class='lwss_modify_content'>{$labels['update']}</span>
					</button>
				</div>
			</div>
			{$details}
		</div>
EOT;
		return $content;
	}

	protected function getDetailsText($origin, $info, $poolInfo)
	{
		$details = array();

		if ($poolInfo['direct_reward_point_rate'] != '') {
			$details['rate'] = sprintf(
				"<div class='lwss_selectable wr-rateinfo' data-type='Point Rate'>%s</div>",
				sprintf(
					__('Every %s you use is worth %s', 'woorewards-lite'),
					$poolInfo['symbol'],
					\LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice($poolInfo['direct_reward_point_rate'], true)
				)
			);
		}

		if ($poolInfo['direct_reward_max_points_on_cart'] != '' && $poolInfo['direct_reward_max_points_on_cart'] > 0) {
			$details['max_points'] = sprintf(
				"<div class='lwss_selectable wr-maxpoints' data-type='Max Points'>%s</div>",
				sprintf(
					__('You can use a maximum of %1$s %2$s on a single cart.', 'woorewards-lite'),
					$poolInfo['direct_reward_max_points_on_cart'],
					$poolInfo['symbols']
				)
			);
		}

		if ($poolInfo['direct_reward_max_percent_of_cart'] != '' && $poolInfo['direct_reward_max_percent_of_cart'] < 100.0) {
			$details['max_perc'] = sprintf(
				"<div class='lwss_selectable wr-maxpercent' data-type='Max Cart Percentage'>%s</div>",
				sprintf(
					__('You can only use your %s to reduce the cart total by %s%%', 'woorewards-lite'),
					$poolInfo['symbols'],
					$poolInfo['direct_reward_max_percent_of_cart']
				)
			);
		}

		if ($poolInfo['direct_reward_total_floor'] != '' && $poolInfo['direct_reward_total_floor'] > 0.0) {
			$details['floor'] = sprintf(
				"<div class='lwss_selectable wr-lowerlimit' data-type='Lower Cart Limit'>%s</div>",
				sprintf(
					__('You can only use your %s to reduce the cart total to %s', 'woorewards-lite'),
					$poolInfo['symbols'],
					\LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice($poolInfo['direct_reward_total_floor'])
				)
			);
		}

		if ($poolInfo['direct_reward_min_subtotal'] != '' && $poolInfo['direct_reward_min_subtotal'] > 0.0) {
			$details['min'] = sprintf(
				"<div class='lwss_selectable wr-mincartamount' data-type='Min Cart Amount'>%s</div>",
				sprintf(
					__('The cart subtotal needs to be over %s if you want to use %s', 'woorewards-lite'),
					\LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice($poolInfo['direct_reward_min_subtotal']),
					$poolInfo['symbols']
				)
			);
		}
		return \apply_filters('lws_woorewards_pointsoncart_details_text', $details, $info, $poolInfo, $origin);
	}
}
