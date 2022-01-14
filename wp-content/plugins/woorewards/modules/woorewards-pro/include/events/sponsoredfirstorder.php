<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Sponsor Earns points for the first time sponsored places an order. */
class SponsoredFirstOrder extends \LWS\WOOREWARDS\Abstracts\Event
{
	use \LWS\WOOREWARDS\PRO\Events\T_SponsorshipOrigin;

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-shop',
			'short' => __("The customer will earn points when a person he sponsored placed an order. You can restrict this to the first sponsored order.", 'woorewards-pro'),
			'help'  => __("This method will only reward the sponsor, not the sponsored", 'woorewards-pro'),
		));
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'first_order_only'] = $this->isFirstOrderOnly() ? 'on' : '';
		$data[$prefix.'guest'] = $this->isGuestAllowed() ? 'on' : '';
		$data[$prefix.'min_amount'] = $this->getMinAmount();
		$data[$prefix.'event_priority'] = $this->getEventPriority();
		return $this->filterSponsorshipData($data, $prefix);
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);

		$label = __("Priority", 'woorewards-pro');
		$tooltip = __("Customer orders will run by ascending priority value.", 'woorewards-pro');
		$str = <<<EOT
		<div class='field-help'>$tooltip</div>
		<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>
	<div class='lws-$context-opt-input value'>
		<input type='text' id='{$prefix}event_priority' name='{$prefix}event_priority' placeholder='10' size='5' />
	</div>
EOT;
		$phb0 = $this->getFieldsetPlaceholder(false, 0);
		$form = str_replace($phb0, $str.$phb0, $form);

		$form .= $this->getFieldsetBegin(2, __("Options", 'woorewards-pro'));

		// First Order Only
		$label   = _x("First order only", "Sponsored Order Event", 'woorewards-pro');
		$tooltip = __("If checked, only the first order placed by each sponsored customer will give points.", 'woorewards-pro');
		$checked = $this->isFirstOrderOnly() ? 'checked' : '';
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'><input class='lws_checkbox' type='checkbox' id='{$prefix}first_order_only' name='{$prefix}first_order_only' $checked/></div>";

		// Allow guest order
		$label   = _x("Guest order", "Sponsored Order Event", 'woorewards-pro');
		$tooltip = __("By default, customer must be registered. Check that option to accept guests. Customer will be tested on billing email.", 'woorewards-pro');
		$checked = $this->isGuestAllowed() ? 'checked' : '';
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'><input class='lws_checkbox' type='checkbox' id='{$prefix}guest' name='{$prefix}guest' $checked/></div>";

		// Minimum order amount
		$label = _x("Minimum order amount", "Sponsored Order Event", 'woorewards-pro');
		$tooltip = __("Uses the Order Subtotal as reference.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'><input type='text' id='{$prefix}min_amount' name='{$prefix}min_amount' placeholder='5' pattern='\\d*(\\.|,)?\\d*' /></div>";

		$form .= $this->getFieldsetEnd(2);
		return $this->filterSponsorshipForm($form, $prefix, $context, 10);
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'first_order_only' => 's',
				$prefix.'guest' => 's',
				$prefix.'min_amount' => 'f',
				$prefix.'event_priority'   => 'd',
			),
			'defaults' => array(
				$prefix.'first_order_only' => '',
				$prefix.'guest' => '',
				$prefix.'min_amount' => '',
				$prefix.'event_priority'   => $this->getEventPriority(),
			),
			'labels'   => array(
				$prefix.'first_order_only' => __("First order only", 'woorewards-pro'),
				$prefix.'guest' => __("Guest order", 'woorewards-pro'),
				$prefix.'min_amount'   => __("Minimum order amount", 'woorewards-pro'),
				$prefix.'event_priority'   => __("Event Priority", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true && ($valid = $this->optSponsorshipSubmit($prefix, $form, $source)) === true )
		{
			$this->setFirstOrderOnly($values['values'][$prefix.'first_order_only']);
			$this->setGuestAllowed($values['values'][$prefix.'guest']);
			$this->setMinAmount($values['values'][$prefix.'min_amount']);
			$this->setEventPriority  ($values['values'][$prefix.'event_priority']);
		}
		return $valid;
	}

	public function setFirstOrderOnly($yes=false)
	{
		$this->firstOrderOnly = boolval($yes);
		return $this;
	}

	function isFirstOrderOnly()
	{
		return isset($this->firstOrderOnly) ? $this->firstOrderOnly : true;
	}

	public function setGuestAllowed($yes)
	{
		$this->guestAllowed = boolval($yes);
		return $this;
	}

	function isGuestAllowed()
	{
		return isset($this->guestAllowed) ? $this->guestAllowed : false;
	}

	function getMinAmount()
	{
		return isset($this->minAmount) ? $this->minAmount : 0;
	}

	public function setMinAmount($amount=0)
	{
		$this->minAmount = max(0.0, floatval(str_replace(',', '.', $amount)));
		return $this;
	}

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		$firstOnly = \get_post_meta($post->ID, 'wre_event_first_order_only', false); // backward compatibility, option introduced on 3.6.0
		$this->setFirstOrderOnly( empty($firstOnly) ? 'on' : reset($firstOnly) );
		$this->setGuestAllowed(\get_post_meta($post->ID, 'wre_event_guest', true));
		$this->setMinAmount(\get_post_meta($post->ID, 'wre_event_min_amount', true));
		$this->setEventPriority($this->getSinglePostMeta($post->ID, 'wre_event_priority', $this->getEventPriority()));
		$this->optSponsorshipFromPost($post);
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_first_order_only', $this->isFirstOrderOnly() ? 'on' : '');
		\update_post_meta($id, 'wre_event_guest', $this->isGuestAllowed() ? 'on' : '');
		\update_post_meta($id, 'wre_event_min_amount', $this->getMinAmount());
		\update_post_meta($id, 'wre_event_priority', $this->getEventPriority());
		$this->optSponsorshipSave($id);
		return $this;
	}

	function getDescription($context='backend')
	{
		$descr = parent::getDescription($context);
		if( ($min = $this->getMinAmount()) > 0.0 )
		{
			$dec = \absint(\apply_filters('wc_get_price_decimals', \get_option( 'woocommerce_price_num_decimals', 2)));
			$descr .= sprintf(__(" (amount greater than %s)", 'woorewards-pro'), \number_format_i18n($min, $dec));
		}
		if( $this->isFirstOrderOnly() )
		{
			$descr .= __(" (first order only)", 'woorewards-pro');
		}
		return $descr;
	}

	/** @return a human readable type for UI */
	public function getDisplayType()
	{
		return _x("Sponsored orders", "getDisplayType", 'woorewards-pro');
	}

	function getEventPriority()
	{
		return isset($this->eventPriority) ? \intval($this->eventPriority) : 101;
	}

	public function setEventPriority($priority)
	{
		$this->eventPriority = \intval($priority);
		return $this;
	}

	/** Add hook to grab events and add points. */
	protected function _install()
	{
		\add_filter('lws_woorewards_wc_order_done_'.$this->getPoolName(), array($this, 'orderDone'), $this->getEventPriority());
	}

	function orderDone($order)
	{
		$sponsorship = new \LWS\WOOREWARDS\PRO\Core\Sponsorship();
		$this->sponsorship = $sponsorship->getUsersFromOrder($order->order, $this->isGuestAllowed());

		if( !$this->sponsorship->sponsor_id )
			return $order;
		if( !$this->isValidOrigin($this->sponsorship->origin) )
			return $order;
		if( $order->amount < $this->getMinAmount() )
			return $order;

		if( $this->isFirstOrderOnly() )
		{
			$orderId = $order->order->get_id();
			if( $this->sponsorship->sponsored_id && \LWS\WOOREWARDS\PRO\Core\Sponsorship::getOrderCountById($this->sponsorship->sponsored_id, $orderId) > 0 )
				return $order;
			if( \LWS\WOOREWARDS\PRO\Core\Sponsorship::getOrderCountByEMail($this->sponsorship->sponsored_email, $orderId) > 0 )
				return $order;
		}

		if( $points = \apply_filters('trigger_'.$this->getType(), 1, $this, $order->order) )
		{
			$reason = \LWS\WOOREWARDS\Core\Trace::byOrder($order->order)
				->setProvider($order->order->get_customer_id('edit'))
				->setReason(
					array(
						"Sponsored friend %s order #%s completed",
						$this->sponsorship->sponsored_email,
						$order->order->get_order_number()
					),
					LWS_WOOREWARDS_PRO_DOMAIN
				);

			$this->addPoint($this->sponsorship->sponsor_id, $reason, $points);
		}
		return $order;
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__("Sponsored friend %s order #%s completed", 'woorewards-pro');
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array_merge(parent::getCategories(), array(
			'woocommerce' => __("WooCommerce", 'woorewards-pro'),
			'sponsorship' => __("Available for sponsored", 'woorewards-pro')
		));
	}
}
