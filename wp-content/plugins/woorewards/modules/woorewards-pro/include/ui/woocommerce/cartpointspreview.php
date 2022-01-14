<?php
namespace LWS\WOOREWARDS\PRO\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Compute a earning point estimation and insert it in the cart page. */
class CartPointsPreview
{
	const POS_ASIDE  = 'cart_collaterals';
	const POS_INSIDE = 'middle_of_cart';
	const POS_AFTER  = 'bottom_of_cart';
	const POS_NONE   = 'not_displayed';

	static function register()
	{
		$position = \lws_get_option('lws_woorewards_cart_potential_position', self::POS_NONE);
		$poolIds = \lws_get_option('lws_woorewards_cart_potential_pool', array());
		$me = new self($poolIds, $position);

		\add_filter('lws_adminpanel_stygen_content_get_'.'cartpointspreview', array( $me, 'template'));
		\add_action('wp_ajax_lws_woorewards_get_cart_preview', array( $me, 'updateDisplay'));
		\add_action('wp_ajax_nopriv_lws_woorewards_get_cart_preview', array( $me, 'updateDisplay'));
		\add_shortcode('wr_cart_points_preview', array($me, 'shortcode'));

		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_script('lws_wre_cart_points_preview', LWS_WOOREWARDS_PRO_JS . '/cartpreview.js', array('jquery', 'lws-tools'), LWS_WOOREWARDS_PRO_VERSION, true);
		\wp_register_style('lws_wre_cart_points_preview', LWS_WOOREWARDS_PRO_CSS.'/templates/cartpointspreview.css?stygen=lws_wre_cart_points_preview', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	public function enqueueScripts()
	{
		\wp_enqueue_script('lws_wre_cart_points_preview');
		\wp_enqueue_style('lws_wre_cart_points_preview');
		\wp_enqueue_style('lws-wr-point-symbol');
	}

	function __construct($poolIds, $position)
	{
		$this->poolIds = $poolIds;
		$this->position = $position;

		if( !empty($hook = $this->getHook($position)) )
		{
			\add_action($hook, array($this, 'display'));
		}
	}

	protected function getPools()
	{
		if( !isset($this->pools) )
		{
			$this->pools = array();
			if( $this->poolIds && \is_array($this->poolIds) )
			{
				foreach( \LWS_WooRewards_Pro::getActivePools()->asArray() as $pool )
				{
					if( in_array($pool->getId(), $this->poolIds) )
					{
						$this->pools[] = $pool;
					}
				}
			}
			else
				$this->pools = \LWS_WooRewards_Pro::getActivePools()->asArray();
		}
		return $this->pools;
	}

	protected function getHook($position)
	{
		if( $position == self::POS_ASIDE )
			return 'woocommerce_cart_collaterals';
		else if( $position == self::POS_INSIDE )
			return 'woocommerce_after_cart_table';
		else if( $position == self::POS_AFTER )
			return 'woocommerce_after_cart';
		else
			return false;
	}

	function template($snippet){
		$this->stygen = true;
		$items = array(
			array(
				'system' => __("Standard System", 'woorewards-pro'),
				'points' => '100 '.__("Points", 'woorewards-pro'),
				'events' => array(
					array(
						'name' => __("Spend Money", 'woorewards-pro'),
						'points' => '80 '.__("Points", 'woorewards-pro'),
					),
					array(
						'name' => __("Place an order", 'woorewards-pro'),
						'points' => '20 '.__("Points", 'woorewards-pro'),
					),
				)
			),
			array(
				'system' => __("Levelling System", 'woorewards-pro'),
				'points' => '124 '.__("Points", 'woorewards-pro'),
				'events' => array(
					array(
						'name' => __("Do Something", 'woorewards-pro'),
						'points' => '124 '.__("Points", 'woorewards-pro'),
					),
				)
			)
		);
		$this->position = '';
		$snippet = $this->getContent($items, false, false);
		unset($this->stygen);
		return $snippet;
	}

	protected function getForcedPools($atts)
	{
		$forcedPool = array();
		if( $atts && \is_array($atts) && isset($atts['force']) && $atts['force'] )
			$forcedPool = \is_array($atts['force']) ? $atts['force'] : explode(',', $atts['force']);
		return \array_map('\trim', $forcedPool);
	}

	function shortcode($atts=array())
	{
		$cart = \WC()->cart;
		if(!$cart) return '';
		$items = array();
		$userId = \get_current_user_id();
		$forcedPool = $this->getForcedPools($atts);

		foreach( $this->getPools() as $pool )
		{
			if( $pool->userCan($userId) || \in_array($pool->getName(), $forcedPool) )
			{
				$system = $pool->getOption('display_title');
				$sum = 0;
				$events = array();
				foreach( $pool->getEvents()->asArray() as $event )
				{
					if( \is_a($event, 'LWS\WOOREWARDS\PRO\Events\I_CartPreview') && 0 < ($points = $event->getPointsForCart($cart)) )
					{
						$cat = $event->getCategories();
						if(!isset($cat['sponsorship']))
						{
							$sum += $points;
							$name = $event->getTitle(false);
							if( !$name )
								$name = $event->getDescription('frontend');
							$events[] = array('name' => $name, 'points' =>\LWS_WooRewards::formatPointsWithSymbol($points, $pool->getName()));
						}
					}
				}
				if( $sum > 0 )
					$items[] = array('system' => $system, 'points' => \LWS_WooRewards::formatPointsWithSymbol($sum, $pool->getName()), 'events' => $events);
			}
		}

		if( $items )
		{
			return $this->getContent($items, $userId, \get_option('lws_woorewards_cpp_show_unlogged', ''));
		}
	}


	function display()
	{
		echo($this->shortcode());
	}

	function updateDisplay()
	{
		echo($this->shortcode());
		exit();
	}

	/** @param $items an array of array('system' => /string/, 'points' => /int/) */
	protected function getContent($items=array(), $userId=false, $force=false)
	{
		$this->enqueueScripts();
		$html = '';

		$title = \lws_get_option('lws_woorewards_title_cpp', __("Loyalty points you will earn", 'woorewards-pro'));
		if( !isset($this->stygen) )
			$title = \apply_filters('wpml_translate_single_string', $title, 'Widgets', "WooRewards - Cart Point Preview - Title");

		if( !$userId )
		{
			$unlogText = \lws_get_option('lws_woorewards_cpp_unlogged_text', '');
			if( $unlogText )
			{
				if( !isset($this->stygen) )
					$unlogText = \apply_filters('wpml_translate_single_string', $unlogText, 'Widgets', "WooRewards - Cart Points Preview - Unlogged Text");
				if( $force )
					$title = $unlogText;
				else
					$html .= "<h2 class='lwss_selectable lws-wre-cpp-nouser-text' data-type='Unlogged Text'>{$unlogText}</h2>";
			}
		}

		if( $userId || $force || isset($this->stygen) )
		{
			$html .= <<<EOT
			<h2 class='lwss_selectable lwss_modify lws-wre-cartpointspreview-title' data-type='Main title' data-id='lws_woorewards_title_cpp'>
				<span class='lwss_modify_content'>{$title}</span>
			</h2>
			<table class='shop_table shop_table_responsive'>
			<tbody>
EOT;
			$titles = array(
				'ls'		=> __("Loyalty System", 'woorewards-pro'),
				'tpoints'	=> __("Total Points", 'woorewards-pro'),
				'action' 	=> __("Action", 'woorewards-pro'),
				'points'	=> __("Points", 'woorewards-pro'),
			);

			$detail = \get_option('lws_woorewards_cpp_show_detail', '');
			foreach ($items as $item)
			{
				$html .= "<tr><td class='lwss_selectable lws-wre-cartpointspreview-label' data-title='{$titles['ls']}' data-type='System title'>{$item['system']}</td>";
				$html .= "<td class='lwss_selectable lws-wre-cartpointspreview-points' data-title='{$titles['tpoints']}' data-type='Points'>{$item['points']}</td></tr>";
				if ($detail && !empty($item['events']))
				{
					foreach ($item['events'] as $event)
					{
						$html .= "<tr><td class='lwss_selectable lws-wre-cpp-event-label' data-title='{$titles['action']}' data-type='Method Name'>{$event['name']}</td>";
						$html .= "<td class='lwss_selectable lws-wre-cpp-event-points' data-title='{$titles['points']}' data-type='Method Points'>{$event['points']}</td></tr>";
					}
				}
			}

			$html .= "</tbody></table>";
		}

		if( $html )
		{
			$wcClass = '';
			if(isset($this->position) && $this->position == self::POS_ASIDE)
			{
				$wcClass = " cross-sells'";
			}
			$html = "<div class='lwss_selectable lws-wre-cartpointspreview-main woocommerce$wcClass' data-type='Main Div'>{$html}</div>";
		}
		return $html;
	}
}