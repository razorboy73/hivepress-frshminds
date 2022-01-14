<?php
namespace LWS\WOOREWARDS\Ui\Shortcodes;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Display default pool user point history */
class UserHistory
{
	public static function install()
	{
		$me = new self();
		\add_shortcode('wr_show_history' , array($me, 'shortcode'));
		\add_filter('lws_adminpanel_stygen_content_get_'.'history_template', array($me, 'template'));
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('woorewards-history', LWS_WOOREWARDS_CSS.'/templates/history.css?stygen=lws_woorewards_history_template', array(), LWS_WOOREWARDS_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('woorewards-history');
	}

	/** Handle RetroCompatibility */
	protected function parseArgs($atts)
	{
		$atts = \wp_parse_args($atts, array('count' => 15, 'offset' => 0));
		if( !isset($atts['system']) )
		{
			if( isset($atts['pool_name']) )
				$atts['system'] = $atts['pool_name'];
			else if( isset($atts['pool']) )
				$atts['system'] = $atts['pool'];
		}
		return $atts;
	}

	/** Displays the user's points history in one or several loyalty systems
	 * [wr_show_history system='poolname1,poolname2' count='15']
	 * @param system the loyalty systems for which to show the history
	 * @param count the max number of history lines displayed
	 */
	public function shortcode($atts=array(), $content='')
	{
		$userId = \apply_filters('lws_woorewards_shortcode_current_user_id', \get_current_user_id(), $atts, 'wr_show_history');
		if( !$userId ) return $content;

		$atts = $this->parseArgs($atts);
		$pools = \apply_filters('lws_woorewards_get_pools_by_args', false, $atts);

		$history= array();
		$doneStacks = array();
		foreach($pools->asArray() as $pool)
		{
			$stack = $pool->getStack($userId);
			if( !\in_array($stack, $doneStacks) )
			{
				$doneStacks[] = $stack;
				if( $hist = $stack->getHistory(false, true, 0, $atts['count']) )
				{
					$poolName = $pool->getOption('display_title');
					foreach($hist as $item)
					{
						$item['pool'] = $poolName;
						$history[] = $item;
					}
				}
			}
		}
		usort($history, function($a1, $a2) {
			return strtotime($a2["op_date"]) - strtotime($a1["op_date"]);
		});

		$history = array_slice($history, 0, \intval($atts['count']));
		if( $history )
		{
			$content = $this->getContent($history);
		}
		return $content;
	}

	public function template()
	{
		$this->stygen = true;
		$history = array(
			array('pool' => 'Default', 'op_date' => "2020-10-15", 'op_reason' => 'A test reason', 'op_value' => '50'),
			array('pool' => 'Default', 'op_date' => "2020-09-15", 'op_reason' => 'Another test reason', 'op_value' => '-50'),
			array('pool' => 'Default', 'op_date' => "2020-08-15", 'op_reason' => 'A third test reason', 'op_value' => '20'),
			array('pool' => 'Default', 'op_date' => "2020-07-15", 'op_reason' => 'A fourth test reason', 'op_value' => '350'),
			array('pool' => 'Default', 'op_date' => "2020-06-15", 'op_reason' => 'A fifth test reason', 'op_value' => '18'),
		);
		$html = $this->getContent($history);
		unset($this->stygen);
		return $html;
	}

	/**	@param $history (array of {op_date, op_value, op_result, op_reason}) */
	protected function getContent($history)
	{
		if( !(isset($this->stygen) && $this->stygen) )
			$this->enqueueScripts();
		$labels = array(
			'lsystem' => __("Loyalty System", 'woorewards-lite'),
			'date'    => __("Date", 'woorewards-lite'),
			'descr'   => __("Description", 'woorewards-lite'),
			'points'  => __("Points", 'woorewards-lite')
		);

		$content ="<div class='lwss_selectable wr-history-grid' data-type='Grid'>";
		$content .="<div class='lwss_selectable history-grid-title' data-type='Title'>{$labels['lsystem']}</div>";
		$content .="<div class='lwss_selectable history-grid-title' data-type='Title'>{$labels['date']}</div>";
		$content .="<div class='lwss_selectable history-grid-title' data-type='Title'>{$labels['descr']}</div>";
		$content .="<div class='lwss_selectable history-grid-title' data-type='Title'>{$labels['points']}</div>";
		foreach ($history as $item) {
			$date = \LWS\WOOREWARDS\Core\PointStack::dateI18n($item['op_date']);
			$content .= "<div class='lwss_selectable history-grid-system' data-type='Loyalty System'>{$item['pool']}</div>";
			$content .= "<div class='lwss_selectable history-grid-date' data-type='Date'>{$date}</div>";
			$content .= "<div class='lwss_selectable history-grid-desc' data-type='Description'>{$item['op_reason']}</div>";
			$content .= "<div class='lwss_selectable history-grid-points' data-type='Points'>{$item['op_value']}</div>";
		}
		$content .= '</div>';
		return $content;
	}
}
