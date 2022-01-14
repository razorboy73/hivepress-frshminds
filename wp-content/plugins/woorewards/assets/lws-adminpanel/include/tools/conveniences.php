<?php
namespace LWS\Adminpanel\Tools;

if( !defined( 'ABSPATH' ) ) exit();

class Conveniences
{
	static function getOrderStatusList()
	{
		static $orderStatusList = false;
		if (false === $orderStatusList) {
			if (\function_exists('\wc_get_order_statuses'))
			{
				$orderStatusList = array();
				foreach (\wc_get_order_statuses() as $value => $label)
				{
					if (substr($value, 0, 3) == 'wc-')
						$value = substr($value, 3);
					$orderStatusList[] = array('value' => $value, 'label' => $label);
				}
			}
			else
			{
				$orderStatusList = array(
					array('value' => 'pending', 'label' => __("Pending payment", 'lws-adminpanel')),
					array('value' => 'processing', 'label' => __("Processing", 'lws-adminpanel')),
					array('value' => 'on-hold', 'label' => __("On hold", 'lws-adminpanel')),
					array('value' => 'completed', 'label' => __("Completed", 'lws-adminpanel')),
					array('value' => 'cancelled', 'label' => __("Cancelled", 'lws-adminpanel')),
					array('value' => 'refunded', 'label' => __("Refunded", 'lws-adminpanel')),
					array('value' => 'failed', 'label' => __("Failed", 'lws-adminpanel')),
				);
			}
		}
		return $orderStatusList;
	}

	static function getWooCommerceCurrencies()
	{
		static $currenciesList = false;
		if (false === $currenciesList){
			if (\function_exists('\get_woocommerce_currencies')){
				foreach (\get_woocommerce_currencies() as $value => $label)
				{
					$currenciesList[] = array('value' => $value, 'label' => $label);
				}
			}
		}
		return $currenciesList;
	}

	static function getCurrentAdminPage()
	{
		static $currentPage = false;
		if (false !== $currentPage)
			return $currentPage;
		if (isset($_REQUEST['page']) && ($currentPage = \sanitize_text_field($_REQUEST['page'])))
			return $currentPage;
		if (isset($_REQUEST['option_page']) && ($currentPage = \sanitize_text_field($_REQUEST['option_page'])))
			return $currentPage;
		return false;
	}

	/** Simulates a WooCommerce Product to return a price for multi currency plugins
	 * $price        → The price to format
	 * $calcdecimals → False : Uses WooCommerce decimals | True : Determines the number of decimals from $price
	 * $formatted    → False : Raw Price | True : Formats the price using WooCommerce
	 */
	static function getCurrencyPrice($price, $calcdecimals=false, $formatted = true)
	{
		if (\class_exists('\WC_Product')) {
			$product = new \WC_Product();
			$product->set_price($price);
			$amount = $product->get_price();
		} else {
			$amount = $price;
		}

		if($formatted) {
			if($calcdecimals){
				if ((int)$amount == $amount) {
					$dec = 0;
				} else {
					$dec = strlen($amount) - strrpos($amount, '.') - 1;
				}
			} else {
				if( \function_exists('\wc_get_price_decimals') ){
					$dec = \wc_get_price_decimals();
				} else {
					$dec = 2;
				}
			}

			if( \function_exists('\wc_price') )
				return \wc_price($amount, array('decimals' => $dec));
			else
				return \number_format_i18n($amount, $dec);
		} else {
			return $amount;
		}
	}

	/** Provided for convenience.
	 * @return (string) the current page url.
	 * @param $args (array of key(string) => value(string)) arguments that will be append to url before it is returned. */
	public static function getCurrentPageUrl($args=array())
	{
		$protocol = 'http://';
		if( (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') )
			$protocol = 'https://';

		$url = ($protocol . $_SERVER['HTTP_HOST'] . \add_query_arg($args, false));
		return $url;
	}

	/** $return the link that leads to current page without any unnecessary arguments. */
	public static function getCurrentPermalink($fallbackOnCurrentQuery=false)
	{
		if (\is_home()) {
			return \home_url();
		}
		if (\is_singular()) {
			return \get_permalink();
		}
		if (\is_search()) {
			if ($fallbackOnCurrentQuery)
				return \add_query_arg('s', \get_query_var('s'), \home_url());
			else
				return \home_url();
		}
		if (\is_date()) {
			if ($fallbackOnCurrentQuery)
				return \add_query_arg(array(
					'second'   => \get_query_var('second'),
					'minute'   => \get_query_var('minute'),
					'hour'     => \get_query_var('hour'),
					'day'      => \get_query_var('day'),
					'monthnum' => \get_query_var('monthnum'),
					'year'     => \get_query_var('year'),
					'm'        => \get_query_var('m'),
					'w'        => \get_query_var('w'),
				), \home_url());
			else
				return \home_url();
		}
		if (\is_feed()) {
			if ($fallbackOnCurrentQuery)
				return \add_query_arg('feed', \get_query_var('feed'), \home_url());
			else
				return \home_url();
		}

		$objId = \get_queried_object_id();
		if ($objId) {
			if (\is_author()) {
				// author archive page
				$url = \get_author_posts_url($objId);
				if ($url && !\is_wp_error($url))
					return $url;
			}
			if (\is_archive()) {
				// categories, tags and other taxonmies list
				$url = \get_term_link($objId);
				if ($url && !\is_wp_error($url))
					return $url;
			}
		}

		if (function_exists('\is_woocommerce') && \is_woocommerce()) {
			// wc bypass standard page flow for some of them
			if (\is_shop())
				$url = \wc_get_page_permalink('shop');
			if ($url && !\is_wp_error($url))
				return $url;
		}

		if ($fallbackOnCurrentQuery)
			return self::getCurrentPageUrl();
		else
			return \home_url();
	}

	/** Convert between bases.
	* @param   string      $number     The number to convert
	* @param   int         $frombase   Numeric base of the number to convert
	* @param   int         $tobase     destination base or 0 if a map is used (default is biggest base possible with $map)
	* @param   string      $map        The alphabet to use (default is [0-9a-zA-Z_-]; means base 64)
	* @return  string|false            Converted number or FALSE on error
	* @author  Geoffray Warnants */
	static function rebaseNumber($number, $frombase, $tobase=false, $map=false)
	{
		if (!$map)
			$map = implode('',array_merge(range(0,9),range('a','z'),range('A','Z'), array('-', '_')));
		if (false === $tobase)
			$tobase = strlen($map);
		if ($frombase<2 || ($tobase==0 && ($tobase=strlen($map))<2) || $tobase<2)
			return false;

		// conversion en base 10 si nécessaire
		if ($frombase != 10) {
			$number = ($frombase <= 16) ? strtolower($number) : (string)$number;
			$map_base = substr($map,0,$frombase);
			$decimal = 0;
			for ($i=0, $n=strlen($number); $i<$n; $i++) {
				$decimal += strpos($map_base,$number[$i]) * pow($frombase,($n-$i-1));
			}
		} else {
			$decimal = $number;
		}
		// conversion en $tobase si nécessaire
		if ($tobase != 10) {
			$map_base = substr($map,0,$tobase);
			$tobase = strlen($map_base);
			$result = '';
			while ($decimal >= $tobase) {
				$result = $map_base[intval($decimal%$tobase)].$result;
				$decimal /= $tobase;
			}
			return $map_base[intval($decimal)].$result;
		}
		return $decimal;
	}

	/** generate a random gift card code */
	public static function randString($length = 8)
	{
		$characters       = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString     = '';
		for( $i = 0; $i < $length; $i++ ) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

	/** To ease boolean argument reading from user.
	 * Understand yes, no, on, off, true, false, numeric value and empty string.
	 * Empty string is false.
	 * @param $arg (string) a human meaning of true (case insensitive)
	 * @return bool */
	public static function argIsTrue($arg)
	{
		if( !$arg )
			return false;
		if( \is_numeric($arg) )
			return (0 != \intval($arg));
		$low = \strtolower($arg);
		if( 'of' == \substr($low, 0, 2) )
			return false;
		return \in_array(\substr($low, 0, 1), array('y', 't', 'o'));
	}

	/**	Default return (int) $userId or id from cart/order if provided.
	 *	@param $userId (int|false) default value
	 *	@param $orderOrCart (WC_Order|WC_Cart|false) */
	public static function getCustomerId($userId, $orderOrCart=false)
	{
		$original = $userId;
		if ($orderOrCart) {
			if (\is_a($orderOrCart, 'WC_Order')) {
				$userId = $orderOrCart->get_customer_id('edit');
				if (!$userId) {
					$user = \get_user_by('email', $orderOrCart->get_billing_email());
					$userId = ($user && $user->exists()) ? $user->ID : 0;
				}
			} elseif (\is_a($orderOrCart, 'WC_Cart')) {
				$customer = $orderOrCart->get_customer();
				if ($customer) {
					$email = $customer->get_billing_email();
					if ($email) {
						$user = \get_user_by('email', $email);
						$userId = ($user && $user->exists()) ? $user->ID : 0;
					}
				}
			}
		}
		return \apply_filters('lws_adminpanel_get_customer_id', $userId, $orderOrCart, $original);
	}

	/**	Default return (WP_User|false) $user or WP_User instance from cart/order if provided.
	 *	@param $userId (WP_User|false) default value
	 *	@param $orderOrCart (WC_Order|WC_Cart|false) */
	public static function getCustomer($user=false, $orderOrCart=false)
	{
		$original = $user;
		if ($orderOrCart) {
			if (\is_a($orderOrCart, 'WC_Order')) {
				$userId = $orderOrCart->get_customer_id();
				if ($userId) {
					if (!$user || ($user->ID != $userId))
						$user = \get_user_by('ID', $userId);
				} else {
					$email = $orderOrCart->get_billing_email();
					if (!$user || ($user->user_email != $email))
						$user = \get_user_by('email', $email);
				}
			} elseif (\is_a($orderOrCart, 'WC_Cart')) {
				$customer = $orderOrCart->get_customer();
				if ($customer) {
					$email = $customer->get_billing_email();
					if ($email && (!$user || ($user->user_email != $email))) {
						$user = \get_user_by('email', $email);
					}
				}
			}
		}
		return \apply_filters('lws_adminpanel_get_customer', ($user && $user->exists()) ? $user : false, $orderOrCart, $original);
	}
}