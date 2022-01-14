<?php

namespace LWS\WOOREWARDS\PRO\Ui\Shortcodes;

// don't call the file directly
if (!defined('ABSPATH')) exit();

/** Shortcode to show when points expire for a user on a specified points and rewards system */
class PointsExpiration
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('wr_points_expiration', array($me, 'shortcode'));
	}

	public function shortcode($atts = array(), $content = '')
	{
		$atts = \wp_parse_args($atts, array('format' => \get_option('date_format'), 'raw' => 'no'));
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_points_expiration');
		if (!$atts['system'])
			return '';
		if (!$userId)
			return '';

		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);
		if ($pools && $pools->count()) {
			$pool = $pools->first();
			$delay = $pool->getOption('point_timeout');

			if ($delay->isNull()) {
				return '';
			} else {
				$lastDate = $this->getLastMovement($pool->getStackId(), $userId);
				if ($lastDate) {
					$expirationDate = $delay->getEndingDate($lastDate);

					if ($atts['format'] == 'days') {
						$content = $expirationDate->diff(\date_create('now', \wp_timezone()), true)->format('%a');
					} else {
						$content = \date_i18n($atts['format'], $expirationDate->getTimestamp());
					}
					if (!\LWS\Adminpanel\Tools\Conveniences::argIsTrue($atts['raw'])) {
						$content = "<span class='lws-points-expiration'>" . $content . "</span>";
					}
				}
			}
		}
		return $content;
	}

	/** @return \DateTime or false */
	private function getLastMovement($stackId, $userId)
	{
		global $wpdb;
		$request = \LWS\Adminpanel\Tools\Request::from($wpdb->lwsWooRewardsHistoric);
		$request->select('MAX(mvt_date)');
		$request->where(array(
			"user_id = %d",
			"stack = %s",
		))->arg(array(
			\intval($userId),
			$stackId,
		));
		$date = $request->getVar();
		return $date ? \date_create($date, \wp_timezone()) : false;
	}
}
