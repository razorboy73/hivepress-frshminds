<?php
namespace LWS\WOOREWARDS\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Earn point the first time a customer complete an order. */
class FirstOrder extends \LWS\WOOREWARDS\Abstracts\Event
{
	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-shop',
			'short' => __("The customer will receive points when placing his first order.", 'woorewards-lite'),
			'help'  => __("Triggered only for registered customers", 'woorewards-lite'),
		));
	}

	public function getDisplayType()
	{
		return _x("Place a first order", "getDisplayType", 'woorewards-lite');
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'event_priority'] = $this->getEventPriority();
		return $data;
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);

		$label = __("Priority", 'woorewards-lite');
		$tooltip = __("Customer orders will run by ascending priority value.", 'woorewards-lite');
		$str = <<<EOT
		<div class='field-help'>$tooltip</div>
		<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>
		<div class='lws-$context-opt-input value'>
			<input type='text' id='{$prefix}event_priority' name='{$prefix}event_priority' placeholder='10' size='5' />
		</div>
EOT;

		$phb0 = $this->getFieldsetPlaceholder(false, 0);
		$form = str_replace($phb0, $str.$phb0, $form);

		return $form;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'event_priority'   => 'd',
			),
			'defaults' => array(
				$prefix.'event_priority'   => $this->getEventPriority(),
			),
			'labels'   => array(
				$prefix.'event_priority'   => __("Event Priority", 'woorewards-lite'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setEventPriority  ($values['values'][$prefix.'event_priority']);
		}
		return $valid;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setEventPriority($this->getSinglePostMeta($post->ID, 'wre_event_priority', $this->getEventPriority()));
		return $this;
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_priority', $this->getEventPriority());
		return $this;
	}

	function getEventPriority()
	{
		return isset($this->eventPriority) ? \intval($this->eventPriority) : 100;
	}

	public function setEventPriority($priority)
	{
		$this->eventPriority = \intval($priority);
		return $this;
	}

	protected function _install()
	{
		\add_filter('lws_woorewards_wc_order_done_'.$this->getPoolName(), array($this, 'orderDone'), $this->getEventPriority());
	}

	function orderDone($order)
	{
		$userId = \LWS\Adminpanel\Tools\Conveniences::getCustomerId(false, $order->order);
		if (!$userId)
			return $order;

		if( $this->getOrderCount($userId, $order->order_id) > 0 )
			return $order;

		if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $order->order) )
		{
			$reason = \LWS\WOOREWARDS\Core\Trace::byOrder($order->order)
				->setProvider($order->order->get_customer_id('edit'))
				->setReason(array("First customer order #%s", $order->order->get_order_number()), 'woorewards-lite');

			$this->addPoint($userId, $reason, $points);
		}
		return $order;
	}

	/**	@param $user (int|WP_User) */
	protected function getOrderCount($user, $exceptOrderId = false)
	{
		if (!$user)
			return 0;

		$userId = 0;
		$email = false;
		if (\is_object($user)) {
			$userId = (int)$user->ID;
			$email = $user->user_email;
		} elseif (\is_numeric($user)) {
			$userId = \intval($user);
			$user = \get_user_by('ID', $userId);
			if ($user && $user->exists())
				$email = $user->user_email;
		} else { // assume a string email
			$email = $user;
		}

		global $wpdb;
		$query = \LWS\Adminpanel\Tools\Request::from($wpdb->posts, 'p');
		$query->where('p.post_type="shop_order"');
		$query->select('COUNT(p.ID)');

		$where = array();
		if ($userId) {
			$query->leftJoin($wpdb->postmeta, 'c', 'p.ID=c.post_id  AND c.meta_key="_customer_user"');
			$where[] = 'c.meta_value = %d';
			$query->arg($userId);
		}
		if ($email) {
			$query->leftJoin($wpdb->postmeta, 'm', 'p.ID=m.post_id  AND m.meta_key="_billing_email"');
			$where[] = 'm.meta_value = %s';
			$query->arg($email);
		}
		if ($where) {
			$where['condition'] = 'OR';
			$query->where($where);
		}

		if ($exceptOrderId)
			$query->where('p.ID <> %d')->arg(\intval($exceptOrderId));

		return \intval($query->getVar());
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", 'woorewards-lite'),
			'order' => __("Order", 'woorewards-lite')
		));
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("First customer order #%s", 'woorewards-lite');
	}
}
