<?php
namespace LWS\WOOREWARDS\PRO\WC;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Refund points at order refund. */
class Order
{
	static function install()
	{
		$me = new self();
		$orderStatuses = \get_option('lws_woorewards_refund_on_status');
		if( $orderStatuses && is_array($orderStatuses) )
		{
			foreach( array_unique($orderStatuses) as $status )
				\add_action('woocommerce_order_status_'.$status, array($me, 'refund'), 998, 2); // priority late to let someone change amount and wc to save order

			$status = \apply_filters('lws_woorewards_order_events', array('processing', 'completed'));
			foreach (array_unique($status) as $s)
				\add_action('woocommerce_order_status_' . $s, array($me, 'unrefund'), 100, 2);
		}
	}

	/** in case order processed again, let be refundable again */
	function unrefund($orderId, $order)
	{
		\update_post_meta($orderId, 'lws_woorewards_points_refunded', false);
	}

	function refund($orderId, $order)
	{
		if( !\get_post_meta($orderId, 'lws_woorewards_points_refunded', true) )
		{
			if( $order )
			{
				$logs = \LWS\WOOREWARDS\Core\PointStack::queryTrace(array(
					'order_id' => $orderId,
					'blog_id'  => \get_current_blog_id(),
				));

				global $wpdb;
				// remove order processed flag
				if (\apply_filters('lws_woorewards_unflag_refunded_order', true, $order, $logs)) {
					$wpdb->query($wpdb->prepare(
						"UPDATE {$wpdb->postmeta} SET `meta_value`='' WHERE `post_id`=%d AND `meta_key` LIKE 'lws_woorewards_core_pool-%'",
						$order->get_id()
					));
				}

				$sort = array();
				$stacks = array();
				if ($logs) {
					// sum per user, stack, origin, origin2, blog
					foreach ($logs as $log) {
						if ($log->move) {
							$key = $this->getLogKey($log);
							if (isset($sort[$key])) {
								$sort[$key]['ids'][] = $log->trace_id;
								$sort[$key]['points'] += $log->move;
							} else {
								$sort[$key] = array(
									'ids'      => array($log->trace_id),
									'points'   => $log->move,
									'user'     => $log->user_id,
									'stack'    => $log->stack,
									'origin'   => $log->origin,
									'provider' => $log->provider_id,
									'blog'     => $log->blog_id,
								);
							}
							$stacks[$log->stack][$log->user_id] = 0;
						}
					}
				}
				// save
				\update_post_meta($orderId, 'lws_woorewards_points_refunded', array('timestamp' => \time(), 'logs' => \array_values($sort)));

				if ($sort) {
					// refund
					$collection = new \LWS\WOOREWARDS\Collections\PointStacks();
					foreach ($sort as $item) {
						$stack  = $item['stack'];
						$user   = $item['user'];
						$points = $item['points'];

						if ($points) {
							$stacks[$stack][$user] += $points;

							$reason = \LWS\WOOREWARDS\Core\trace::byOrder($order)
								->setReason(array('Refund Order #%s', $order->get_order_number()), 'woorewards-pro')
								->setOrigin($item['origin'])
								->setProvider($item['provider'])
								->setBlog($item['blog']);
							$manager = $collection->create($stack, $user);//new \LWS\WOOREWARDS\Core\PointStack($stack, $userId);
							$manager->sub($points, $reason);
						}
					}
				}

				// let a note in order
				foreach ($stacks as $stack => $value) {
					foreach ($value as $userId => $points) {
						if ($points) {
							$order->add_order_note(sprintf(
								__('<b>%1$s</b> removed in <i>%4$s</i> from customer <i>[%2$d]</i> since order passed to <i>%3$s</i>.', 'woorewards-pro'),
								\LWS_WooRewards::formatPointsWithSymbol($points, ''),
								$userId,
								$order->get_status(),
								$stack
							));
						}
					}
				}

				\do_action('lws_woorewards_points_refunded', $orderId, $order, $sort);
			}
		}
	}

	/** @return (string) a key built on user, stack, origin, origin2, blog. */
	private function getLogKey($log) {
		return implode('|', array(
			$log->user_id,
			$log->stack,
			$log->origin,
			$log->provider_id,
			$log->blog_id,
		));
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Refund Order #%s", 'woorewards-pro');
	}
}
