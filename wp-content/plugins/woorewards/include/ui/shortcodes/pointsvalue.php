<?php

namespace LWS\WOOREWARDS\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Displays the value of points in a points to cart system */
class PointsValue
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('wr_points_value', array($me, 'shortcode'));
	}

	/** Handle RetroCompatibility */
	protected function parseArgs($atts)
	{
		$atts = \wp_parse_args($atts, array('text' => '', 'raw' => true));
		return $atts;
	}

	/** Displays the user's points value in currency for a specific pool
	 * [wr_points_value system='poolname1' text='Your points are worth' raw='true']
	 * @param system the loyalty system for which to show the value
	 * @param text text displayed before the points value
	 * @param raw if true, the output is a simple text, otherwise, it's wrapped into dom elements
	 */
	public function shortcode($atts = array(), $content = '')
	{
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_points_value');
		if (!$userId) return $content;

		$atts = $this->parseArgs($atts);
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
		// we only display the value of a simple pool
		$pool = $pools->first();
		if ($pool && $pool->getOption('direct_reward_mode')) {
			// It's a points on cart pool
			$points = $pool->getPoints($userId);
			if ($points < 0) return '';
			$rate = $pool->getOption('direct_reward_point_rate');
			$value = $points * $rate;
			$formatted_value = \LWS\Adminpanel\Tools\Conveniences::getCurrencyPrice($value, true);
			if ($atts['raw']) {
				if ($atts['text'] != '') {
					$content .= $atts['text'] . ' ';
				}
				$content .= $formatted_value;
			} else {
				$content .= "<span class='wr-points-value-wrapper'>";
				if ($atts['text'] != '') {
					$content .= "<span class='wr-points-value-text'>" . $atts['text'] . " </span>";
				}
				$content .= "<span class='wr-points-value-value'>" . $formatted_value . " </span>";
				$content .= "</span>";
			}
		}
		return $content;
	}
}
