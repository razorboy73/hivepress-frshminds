<?php

namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if (!defined('ABSPATH')) exit();


/** Earn points for each money spend on an order. */
class OrderCompleted extends \LWS\WOOREWARDS\Events\OrderCompleted
implements \LWS\WOOREWARDS\PRO\Events\I_CartPreview
{
	use \LWS\WOOREWARDS\PRO\Events\T_Order;
	use \LWS\WOOREWARDS\PRO\Events\T_SponsorshipOrigin;

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'min_amount'] = $this->getMinAmount();
		$data[$prefix . 'after_discount'] = $this->getAfterDiscount() ? 'on' : '';
		$data = $this->filterSponsorshipData($data, $prefix);
		return $this->filterData($data, $prefix);
	}

	function getForm($context = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);
		$form .= $this->getFieldsetBegin(2, __("Options", 'woorewards-pro'));

		// min amount
		$label = _x("Minimum order amount", "Place an order event", 'woorewards-pro');
		$tooltip = __("Uses the Order Subtotal as reference.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'><input type='text' id='{$prefix}min_amount' name='{$prefix}min_amount' placeholder='5' pattern='\\d*(\\.|,)?\\d*' /></div>";

		// compute points after discount
		$label   = _x("Use amount after discount", "Place an order event", 'woorewards-pro');
		$tooltip = __("If set, the Minimum order amount will be calculated from the cart total instead of the cart subtotal.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'><input class='lws_checkbox' type='checkbox' id='{$prefix}after_discount' name='{$prefix}after_discount'/></div>";

		$form .= $this->getFieldsetEnd(2);
		$form =  $this->filterForm($form, $prefix, $context);
		return $this->filterSponsorshipForm($form, $prefix, $context, 10);
	}

	function submit($form = array(), $source = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'after_discount' => 's',
				$prefix . 'min_amount' => 'f'
			),
			'defaults' => array(
				$prefix . 'after_discount' => '',
				$prefix . 'min_amount' => ''
			),
			'labels'   => array(
				$prefix . 'after_discount' => __("After Discount", 'woorewards-pro'),
				$prefix . 'min_amount'   => __("Minimum order amount", 'woorewards-pro')
			)
		));
		if (!(isset($values['valid']) && $values['valid']))
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if ($valid === true)
			$valid = $this->optSponsorshipSubmit($prefix, $form, $source);
		if ($valid === true && ($valid = $this->optSubmit($prefix, $form, $source)) === true)
		{
			$this->setMinAmount($values['values'][$prefix . 'min_amount']);
			$this->setAfterDiscount(boolval($values['values'][$prefix . 'after_discount']));
		}
		return $valid;
	}

	/** Compute points on final order amount,
	 *	after fees and discounts applied.
	 *  @return bool */
	public function getAfterDiscount()
	{
		return isset($this->afterDiscount) && $this->afterDiscount;
	}

	public function setAfterDiscount($yes = true)
	{
		$this->afterDiscount = $yes;
		return $this;
	}

	function getDescription($context = 'backend')
	{
		$descr = parent::getDescription($context);
		if (($min = $this->getMinAmount()) > 0.0)
		{
			$dec = \absint(\apply_filters('wc_get_price_decimals', \get_option('woocommerce_price_num_decimals', 2)));
			$descr .= sprintf(__(" (amount greater than %s)", 'woorewards-pro'), \number_format_i18n($min, $dec));
		}
		return $descr;
	}

	function getClassname()
	{
		return 'LWS\WOOREWARDS\Events\OrderCompleted';
	}

	function getMinAmount()
	{
		return isset($this->minAmount) ? $this->minAmount : 0;
	}

	public function setMinAmount($amount = 0)
	{
		$this->minAmount = max(0.0, floatval(str_replace(',', '.', $amount)));
		return $this;
	}

	protected function _fromPost(\WP_Post $post)
	{
		$this->setMinAmount(\get_post_meta($post->ID, 'wre_event_min_amount', true));
		$this->setAfterDiscount(boolval(\get_post_meta($post->ID, 'wre_event_after_discount',   true)));
		$this->optFromPost($post);
		$this->optSponsorshipFromPost($post);
		return parent::_fromPost($post);
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_min_amount', $this->getMinAmount());
		\update_post_meta($id, 'wre_event_after_discount',   $this->getAfterDiscount());
		$this->optSponsorshipSave($id);
		$this->optSave($id);
		return parent::_save($id);
	}

	function orderDone($order)
	{
		if (!$this->acceptOrder($order->order))
			return $order;
		if (!$this->isValidOriginByOrder($order->order))
			return $order;
		if(!$this->isValidCurrency($order->order))
			return $order;

		if ($this->getMinAmount() > 0.0)
		{
			$inc_tax = !empty(\get_option('lws_woorewards_order_amount_includes_taxes', ''));
			if ($this->getAfterDiscount())
			{
				$amount = $order->order->get_total('edit');
				if (!$inc_tax)
					$amount -= $order->order->get_total_tax('edit'); // remove shipping tax too
			}
			else
			{
				$amount = floatval($order->order->get_subtotal());
				if ($inc_tax)
					$amount += $order->order->get_total_tax('edit');
			}
			if ($amount < $this->getMinAmount())
				return $order;
		}
		return parent::orderDone($order);
	}

	function getPointsForProduct(\WC_Product $product)
	{
		return 0;
	}

	function getPointsForCart(\WC_Cart $cart)
	{
		if (!$this->acceptOrigin('checkout'))
			return 0;
		if (!$this->acceptCart($cart))
			return 0;
		if (!$this->isValidCurrentSponsorship())
			return 0;
		if (!$this->isValidCurrency())
			return 0;

		if ($this->getMinAmount() > 0.0)
		{
			$inc_tax = !empty(\get_option('lws_woorewards_order_amount_includes_taxes', ''));
			if ($this->getAfterDiscount())
			{
				$amount = $cart->get_total('edit');
				if (!$inc_tax)
					$amount -= $cart->get_total_tax('edit'); // remove shipping tax too
			}
			else
			{
				$amount = $cart->get_subtotal();
				if ($inc_tax)
					$amount += $cart->get_total_tax('edit');
			}
			$amount = $this->roundPrice($amount);
			if ($amount < $this->getMinAmount())
				return 0;
		}
		return $this->getMultiplier();
	}

	function getPointsForOrder(\WC_Order $order)
	{
		if (!$this->acceptOrder($order))
			return 0;
		if (!$this->isValidOriginByOrder($order))
			return 0;
		if(!$this->isValidCurrency($order))
			return 0;

		if ($this->getMinAmount() > 0.0)
		{
			$inc_tax = !empty(\get_option('lws_woorewards_order_amount_includes_taxes', ''));
			if ($this->getAfterDiscount())
			{
				$amount = $order->get_total('edit');
				if (!$inc_tax)
					$amount -= $order->get_total_tax('edit'); // remove shipping tax too
			}
			else
			{
				$amount = floatval($order->get_subtotal());
				if ($inc_tax)
					$amount += $order->get_total_tax('edit');
			}
			if ($amount < $this->getMinAmount())
				return 0;
		}
		return $this->getMultiplier();
	}

	function roundPrice($price)
	{
		return \wc_format_decimal(\floatval($price), \wc_get_price_decimals());
	}
}
