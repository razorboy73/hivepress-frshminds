<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Sponsor earns points for each money spend by Sponsored on an order.
 *	Extends usual order amount to only change point destination. */
class SponsoredOrderAmount extends \LWS\WOOREWARDS\PRO\Events\OrderAmount
{

	function getInformation()
	{
		return array_merge(parent::getInformation(), array(
			'icon'  => 'lws-icon-coins',
			'short' => __("The customer will earn points when a sponsored spends money on your shop.", 'woorewards-pro'),
			'help'  => __("This method will only reward the sponsor, not the sponsored", 'woorewards-pro'),
		));
	}

	function getClassname()
	{
		return \get_class($this);
	}

	public function getDisplayType()
	{
		return _x("Sponsored spends money", "getDisplayType", 'woorewards-pro');
	}

	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = ($placeholder = $this->getFieldsetPlaceholder(true, 2));

		// Allow guest order
		$label   = _x("Guest order", "Sponsored Order Event", 'woorewards-pro');
		$tooltip = __("By default, customer must be registered. Check that option to accept guests. Customer will be tested on billing email.", 'woorewards-pro');
		$checked = $this->isGuestAllowed() ? 'checked' : '';
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'><input class='lws_checkbox' type='checkbox' id='{$prefix}guest' name='{$prefix}guest' $checked/></div>";

		return str_replace($placeholder, $form, parent::getForm($context));
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix.'guest'] = $this->isGuestAllowed() ? 'on' : '';
		return $data;
	}

	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'guest' => 's',
			),
			'defaults' => array(
				$prefix.'guest' => '',
			),
			'labels'   => array(
				$prefix.'guest' => __("Guest order", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if( $valid === true )
		{
			$this->setGuestAllowed($values['values'][$prefix.'guest']);
		}
		return $valid;
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

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	protected function _fromPost(\WP_Post $post)
	{
		parent::_fromPost($post);
		$this->setGuestAllowed(\get_post_meta($post->ID, 'wre_event_guest', true));
		return $this;
	}

	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	protected function _save($id)
	{
		parent::_save($id);
		\update_post_meta($id, 'wre_event_guest', $this->isGuestAllowed() ? 'on' : '');
		return $this;
	}

	/** override */
	function orderDone($order)
	{
		$sponsorship = new \LWS\WOOREWARDS\PRO\Core\Sponsorship();
		$this->sponsorship = $sponsorship->getUsersFromOrder($order->order, $this->isGuestAllowed());

		if( !$this->sponsorship->sponsor_id )
			return $order;
		if( !$this->acceptOrder($order->order) )
			return $order;
		if(!$this->isValidCurrency($order->order))
			return $order;
		return parent::orderDone($order);
	}

	protected function isTheFirst(&$order)
	{
		$orderId = $order->order->get_id();
		if( $this->sponsorship->sponsored_id && \LWS\WOOREWARDS\PRO\Core\Sponsorship::getOrderCountById($this->sponsorship->sponsored_id, $orderId) > 0 )
			return false;

		if( \LWS\WOOREWARDS\PRO\Core\Sponsorship::getOrderCountByEMail($this->sponsorship->sponsored_email, $orderId) > 0 )
			return false;

		return true;
	}

	/** @param $order (WC_Order)
	 * @return (int) user ID */
	function getPointsRecipient($order)
	{
		if( $this->sponsorship && $this->sponsorship->sponsor_id )
			return $this->sponsorship->sponsor_id;
		else
			return false;
	}

	/** @param $order (WC_Order)
	 * @param $amount (float) computed amount
	 * @return (\LWS\WOOREWARDS\Core\Trace) a reason for history */
	function getPointsReason($order, $amount)
	{
		$price = \wp_kses(\wc_price($amount, array('currency' => $order->get_currency())), array());
		return \LWS\WOOREWARDS\Core\Trace::byOrder($order)
			->setProvider($order->get_customer_id('edit'))
			->setReason(array(
					'Sponsored friend %3$s spent %1$s from order #%2$s',
					$price,
					$order->get_order_number(),
					$order->get_billing_email()
				), LWS_WOOREWARDS_PRO_DOMAIN
			);
	}

	/* The sponsor and sponsored will never see that value, so it should always return 0 */
	function getPointsForProduct(\WC_Product $product)
	{
		return 0;
	}

	/** Never call, only to have poedit/wpml able to extract the sentance. */
	private function poeditDeclare()
	{
		__('Sponsored friend %3$s spent %1$s from order #%2$s', 'woorewards-pro');
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array(
			\LWS\WOOREWARDS\Core\Pool::T_STANDARD  => __("Standard", 'woorewards-pro'),
			\LWS\WOOREWARDS\Core\Pool::T_LEVELLING => __("Levelling", 'woorewards-pro'),
			'achievement' => __("Achievement", 'woorewards-pro'),
			'custom'      => __("Events", 'woorewards-pro'),
			'woocommerce' => __("WooCommerce", 'woorewards-pro'),
			'sponsorship' => __("Available for sponsored", 'woorewards-pro')
		);
	}
}
