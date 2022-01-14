<?php

namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if (!defined('ABSPATH')) exit();


/** Earn points for each money spend on an order. */
class OrderAmount extends \LWS\WOOREWARDS\Events\OrderAmount
implements \LWS\WOOREWARDS\PRO\Events\I_CartPreview
{
	use \LWS\WOOREWARDS\PRO\Events\T_ExcludedProducts;
	use \LWS\WOOREWARDS\PRO\Events\T_Order;
	use \LWS\WOOREWARDS\PRO\Events\T_SponsorshipOrigin;

	function getDescription($context = 'backend')
	{
		$descr = parent::getDescription($context);
		if (($min = $this->getMinAmount()) > 0.0)
		{
			$dec = \absint(\apply_filters('wc_get_price_decimals', \get_option('woocommerce_price_num_decimals', 2)));
			$descr .= sprintf(__(" (amount greater than %s)", 'woorewards-pro'), \number_format_i18n($min, $dec));
		}

		if ($this->getAfterDiscount())
		{
			$descr .= (' ' . _x("(after discount)", "Earning method description", 'woorewards-pro'));
		}
		else
		{
			$categories = $this->getProductCategories();
			if (!empty($categories))
			{
				$msg = _n("Limited to category %s", "Limited to categories %s", count($categories), 'woorewards-pro');
				$descr .= sprintf('<br/>' . $msg, $this->getProductCategoriesNames($categories, $context));
			}
			$negCat = $this->getProductExcludedCategories();
			if (!empty($negCat))
			{
				$msg = _n("Exclude category %s", "Exclude categories %s", count($negCat), 'woorewards-pro');
				$descr .= sprintf('<br/>' . $msg, $this->getProductCategoriesNames($negCat, $context));
			}
		}

		if ($this->getShipping())
		{
			$descr .= '<br/>';
			$descr .= __("Include shipping", 'woorewards-pro');
		}
		return $descr;
	}

	protected function getProductCategoriesNames($categories, $context = 'backend', $sep = ', ')
	{
		$names = array();
		foreach ($categories as $cat) {
			$term = \get_term($cat, 'product_cat');
			if (!$term || is_wp_error($term)) {
				$names[] = _x("Unknown", "Unknown category", 'woorewards-pro');
			} else {
				$url = ($context == 'backend' ? \esc_attr(\get_edit_tag_link($cat, 'product_cat')) : '');
				if (!$url) {
					if ($context == 'raw')
						$names[] = $term->name;
					else
						$names[] = "<b>{$term->name}</b>";
				} else {
					$names[] = "<a href='{$url}'>{$term->name}</a>";
				}
			}
		}
		return \implode($sep, $names);
	}

	function getForm($context = 'editlist')
	{
		\wp_enqueue_script('lws_woorewards_orderamount', LWS_WOOREWARDS_PRO_JS . '/orderamount.js', array('jquery'), LWS_WOOREWARDS_PRO_VERSION, true);
		\wp_enqueue_style('lws_woorewards_orderamount', LWS_WOOREWARDS_PRO_CSS . '/orderamount.css', array(), LWS_WOOREWARDS_PRO_VERSION);

		$prefix = $this->getDataKeyPrefix();
		$form = parent::getForm($context);

		// shipping included
		$label   = _x("Include shipping amount", "Order Amount Event", 'woorewards-pro');
		$optform = "<div class='lws-$context-opt-title label'>$label</div>";
		$optform .= "<div class='lws-$context-opt-input value'><input class='lws_checkbox' type='checkbox' id='{$prefix}include_shipping' name='{$prefix}include_shipping'/></div>";

		// threshold effect applied
		$label   = _x("Threshold effect", "Order Amount Event", 'woorewards-pro');
		$tooltip = __("If checked, customers will earn points for each multiple of this amount. If unchecked, points earned will be rounded to the closest value.", 'woorewards-pro');
		$optform .= "<div class='field-help'>$tooltip</div>";
		$optform .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$optform .= "<div class='lws-$context-opt-input value'><input class='lws_checkbox' type='checkbox' id='{$prefix}threshold_effect' name='{$prefix}threshold_effect'/></div>";

		// Minimum order amount
		$label = _x("Minimum order amount", "Order Amount Event", 'woorewards-pro');
		$tooltip = __("Uses the Order Subtotal as reference.", 'woorewards-pro');
		$optform .= "<div class='field-help'>$tooltip</div>";
		$optform .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$optform .= "<div class='lws-$context-opt-input value'><input type='text' id='{$prefix}min_amount' name='{$prefix}min_amount' placeholder='5' pattern='\\d*(\\.|,)?\\d*' /></div>";

		$phb2 = $this->getFieldsetPlaceholder(false, 2);
		$form = str_replace($phb2, $optform . $phb2, $form);

		$form .= $this->getFieldsetBegin(3, __("Allow / Deny Categories", 'woorewards-pro'), 'span2 lws_woorewards_orderamount_after_discount_relative');

		// restriction by product category
		$label   = _x("Allowed categories", "Order Amount Event", 'woorewards-pro');
		if ($context == 'achievements')
			$tooltip = __("If left empty, all bought products are taken into account. If you set one or more categories, only the products which belong to these categories will be used.", 'woorewards-pro');
		else
			$tooltip = __("If left empty, all bought products can give loyalty points. If you set one or more categories, only the products which belong to these categories will award points.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'product_cat', array(
			'comprehensive' => true,
			'predefined' => 'taxonomy',
			'spec' => array('taxonomy' => 'product_cat'),
			'value' => $this->getProductCategories()
		));
		$form .= "</div>";

		// restriction by product category blacklist
		$label   = _x("Denied categories", "Order Amount Event", 'woorewards-pro');
		if ($context == 'achievements')
			$tooltip = __("If left empty, all bought products are taken into account. If you set one or more categories, the products which belong to these categories will <b>not</b> be used.", 'woorewards-pro');
		else
			$tooltip = __("If left empty, all bought products can give loyalty points. If you set one or more categories, the products which belong to these categories will <b>not</b> award points.", 'woorewards-pro');
		$form .= "<div class='field-help'>$tooltip</div>";
		$form .= "<div class='lws-$context-opt-title label'>$label<div class='bt-field-help'>?</div></div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix . 'product_neg_cat', array(
			'comprehensive' => true,
			'predefined' => 'taxonomy',
			'spec' => array('taxonomy' => 'product_cat'),
			'value' => $this->getProductCategories()
		));
		$form .= "</div>";

		$form .= $this->getFieldsetEnd(3);
		$form =  $this->filterForm($form, $prefix, $context);
		return $this->filterSponsorshipForm($form, $prefix, $context, 10);
	}

	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = parent::getData();
		$data[$prefix . 'product_cat'] = base64_encode(json_encode($this->getProductCategories()));
		$data[$prefix . 'product_neg_cat'] = base64_encode(json_encode($this->getProductExcludedCategories()));
		$data[$prefix . 'threshold_effect'] = $this->getThresholdEffect() ? 'on' : '';
		$data[$prefix . 'min_amount'] = $this->getMinAmount();
		$data = $this->filterSponsorshipData($data, $prefix);
		return $this->filterData($data, $prefix);
	}

	function submit($form = array(), $source = 'editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix . 'product_cat' => array('D'),
				$prefix . 'product_neg_cat' => array('D'),
				$prefix . 'threshold_effect' => 's',
				$prefix . 'min_amount' => 'f',
			),
			'defaults' => array(
				$prefix . 'product_cat' => array(),
				$prefix . 'product_neg_cat' => array(),
				$prefix . 'threshold_effect' => '',
				$prefix . 'min_amount' => '',
			),
			'labels'   => array(
				$prefix . 'product_cat'   => __("Product category", 'woorewards-pro'),
				$prefix . 'product_neg_cat'   => __("Excluded category", 'woorewards-pro'),
				$prefix . 'min_amount'   => __("Minimum order amount", 'woorewards-pro'),
			)
		));
		if (!(isset($values['valid']) && $values['valid']))
			return isset($values['error']) ? $values['error'] : false;

		$valid = parent::submit($form, $source);
		if ($valid === true)
			$valid = $this->optSponsorshipSubmit($prefix, $form, $source);
		if ($valid === true && ($valid = $this->optSubmit($prefix, $form, $source)) === true)
		{
			$this->setThresholdEffect(boolval($values['values'][$prefix . 'threshold_effect']));
			if ($this->getAfterDiscount())
			{
				$this->setProductCategories(array());
				$this->setProductExcludedCategories(array());
			}
			else
			{
				$this->setProductCategories($values['values'][$prefix . 'product_cat']);
				$this->setProductExcludedCategories($values['values'][$prefix . 'product_neg_cat']);
			}
			$this->setMinAmount($values['values'][$prefix . 'min_amount']);
		}
		return $valid;
	}

	function getProductCategories()
	{
		return isset($this->productCategories) ? $this->productCategories : array();
	}

	/** @param $categories (array|string) as string, it should be a json base64 encoded array. */
	function setProductCategories($categories = array())
	{
		if (!is_array($categories))
			$categories = @json_decode(@base64_decode($categories));
		if (is_array($categories))
			$this->productCategories = $categories;
		return $this;
	}

	function getProductExcludedCategories()
	{
		return isset($this->productExcludedCategories) ? $this->productExcludedCategories : array();
	}

	/** @param $categories (array|string) as string, it should be a json base64 encoded array. */
	function setProductExcludedCategories($categories = array())
	{
		if (!is_array($categories))
			$categories = @json_decode(@base64_decode($categories));
		if (is_array($categories))
			$this->productExcludedCategories = $categories;
		return $this;
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
		$this->setProductCategories(\get_post_meta($post->ID, 'wre_event_product_cat', true));
		$this->setProductExcludedCategories(\get_post_meta($post->ID, 'wre_event_product_neg_cat', true));
		$this->setMinAmount(\get_post_meta($post->ID, 'wre_event_min_amount', true));
		$this->optFromPost($post);
		$this->optSponsorshipFromPost($post);
		return parent::_fromPost($post);
	}

	protected function _save($id)
	{
		\update_post_meta($id, 'wre_event_product_cat', $this->getProductCategories());
		\update_post_meta($id, 'wre_event_product_neg_cat', $this->getProductExcludedCategories());
		\update_post_meta($id, 'wre_event_min_amount', $this->getMinAmount());
		$this->optSponsorshipSave($id);
		$this->optSave($id);
		return parent::_save($id);
	}

	function getClassname()
	{
		return 'LWS\WOOREWARDS\Events\OrderAmount';
	}

	/** Override to add information when context is 'view'. */
	public function getMultiplier($context='edit')
	{
		$mul = parent::getMultiplier('edit');
		if( $context == 'view' && $mul > 0.0 )
		{
			$points = \LWS_WooRewards::formatPointsWithSymbol($mul, $this->getPoolName());
			$amount = $this->getDenominator();
			$currency = $this->getCurrency();
			if(\LWS_WooRewards::isWC()){
				if($currency){
					$amount = \wc_price($amount, array('currency' => $currency));
				}else{
					$amount = \wc_price($amount);
				}
			}else{
				$amount = \number_format_i18n($amount, 2);
			}
			$mul = $points . ' / ' . $amount;
		}
		return $mul;
	}

	/** override */
	function orderDone($order)
	{
		if (!$this->acceptOrder($order->order))
			return $order;
		if (!$this->isValidOriginByOrder($order->order))
			return $order;
		if(!$this->isValidCurrency($order->order))
			return $order;

		if ($order->amount < $this->getMinAmount())
			return $order;

		return parent::orderDone($order);
	}

	/** override to take care of order content: product categories
	 * order amount is sum of accepted product prices.
	 *
	 * Shipping is still added whatever category or not */
	function getOrderAmount(&$order, $round = true)
	{
		$amount = 0;
		if ($this->getAfterDiscount())
		{
			$amount = $order->order->get_total('edit');
			$inc_tax = !empty(\get_option('lws_woorewards_order_amount_includes_taxes', ''));
			if (!$inc_tax)
				$amount -= $order->order->get_total_tax('edit'); // remove shipping tax too

			if (!$this->getShipping()) // remove shipping and shipping tax if not already done with the rest of taxes
			{
				$amount -= floatval($order->order->get_shipping_total('edit'));
				if ($inc_tax)
					$amount -= floatval($order->order->get_shipping_tax('edit'));
			}
			$amount = max(0, $amount);
		}
		else
		{
			$categories = $this->getProductCategories();
			$exclude = $this->getProductExcludedCategories();
			$noEarning = $this->getExclusionFromOrder($order->order);

			if (empty($categories) && empty($exclude) && empty($noEarning))
			{
				$amount = parent::getOrderAmount($order, false);
			}
			else
			{
				$inc_tax = !empty(\get_option('lws_woorewards_order_amount_includes_taxes', ''));
				foreach ($order->items as $item)
				{
					$product = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getProductFromOrderItem($order->order, $item->item);
					if ($product)
					{
						if (!empty($exclude) && $this->isProductInCategory($product, $exclude))
							continue;
						if (!empty($categories) && !$this->isProductInCategory($product, $categories))
							continue;

						if (empty($noEarning))
							$amount += $item->amount;
						else
						{
							$qty = $item->item->get_quantity();
							$qty = $this->useExclusion($noEarning, $product, $qty);
							if ($qty > 0)
							{
								if ($inc_tax)
									$amount += floatval(\wc_get_price_including_tax($product)) * $qty;
								else
									$amount += floatval(\wc_get_price_excluding_tax($product)) * $qty;
							}
						}
					}
				}

				// should be strange, but since option is still available, we Must use it if checked
				if ($this->getShipping()) // add shipping if required, whatever category
				{
					$amount += floatval($order->order->get_shipping_total('edit'));
					if ($order->inc_tax)
						$amount += floatval($order->order->get_shipping_tax('edit'));
				}
			}
		}
		if ($amount < $this->getMinAmount())
			$amount = 0;
		return $round ? $this->roundPrice($amount) : $amount;
	}

	private function isProductInCategory($product, $whiteList, $taxonomy = 'product_cat')
	{
		$product_cats = \wc_get_product_cat_ids($product->get_id());
		if (!empty($parentId = $product->get_parent_id()))
			$product_cats = array_merge($product_cats, \wc_get_product_cat_ids($parentId));

		// If we find an item with a cat in our allowed cat list, the product is valid.
		return !empty(array_intersect($product_cats, $whiteList));
	}

	function getPointsForProduct(\WC_Product $product)
	{
		if (!$this->acceptOrigin('checkout'))
			return 0;
		if (!$this->acceptCart(\WC()->cart))
			return 0;
		if (!$this->isValidCurrentSponsorship())
			return 0;
		if(!$this->isValidCurrency())
			return 0;

		$valid = true;
		if (!empty($categories = $this->getProductCategories()) && !$this->isProductInCategory($product, $categories))
			$valid = false;
		if (!empty($exclude = $this->getProductExcludedCategories()) && $this->isProductInCategory($product, $exclude))
			$valid = false;

		$price = 0;
		if ($valid)
		{
			if (count($product->get_children()) > 1)
			{
				$prices = array();
				foreach ($product->get_children() as $varId)
				{
					if ($variation = wc_get_product($varId))
					{
						// Hide out of stock variations if 'Hide out of stock items from the catalog' is checked.
						if (!$variation || !$variation->exists() || ('yes' === \get_option('woocommerce_hide_out_of_stock_items') && !$variation->is_in_stock()))
							continue;

						if (\method_exists($variation, 'variation_is_visible'))
						{
							// Filter 'woocommerce_hide_invisible_variations' to optionally hide invisible variations (disabled variations and variations with empty price).
							if (\apply_filters('woocommerce_hide_invisible_variations', true, $product->get_id(), $variation) && !$variation->variation_is_visible())
								continue;
						}

						if (!empty(\get_option('lws_woorewards_order_amount_includes_taxes', '')))
							$prices[] = floatval(\wc_get_price_including_tax($variation));
						else
							$prices[] = floatval(\wc_get_price_excluding_tax($variation));
					}
				}
				if (!empty($prices))
				{
					$min = min($prices);
					$max = max($prices);
					$price = ($min != $max) ? array($min, $max) : $max;
				}
			}
			else
			{
				if (!empty(\get_option('lws_woorewards_order_amount_includes_taxes', '')))
					$price = floatval(\wc_get_price_including_tax($product));
				else
					$price = floatval(\wc_get_price_excluding_tax($product));
			}
		}

		if (is_array($price))
		{
			$mul = $this->getMultiplier();
			foreach ($price as &$p)
			{
				$p = $this->roundPrice($p);
				$p = intval(round($this->getPointsForAmount($p) * $mul));
			}
			return $price;
		}
		else
		{
			$price = $this->roundPrice($price);
			return intval(round($this->getPointsForAmount($price) * $this->getMultiplier()));
		}
	}

	function getPointsForCart(\WC_Cart $cart)
	{
		if (!$this->acceptOrigin('checkout'))
			return 0;
		if (!$this->acceptCart($cart))
			return 0;
		if (!$this->isValidCurrentSponsorship())
			return 0;
		if(!$this->isValidCurrency())
			return 0;

		$inc_tax = !empty(\get_option('lws_woorewards_order_amount_includes_taxes', ''));
		$amount = 0;

		if ($this->getAfterDiscount())
		{
			$amount = $cart->get_total('edit');
			if (!$inc_tax)
				$amount -= $cart->get_total_tax('edit'); // remove shipping tax too

			if (!$this->getShipping()) // remove shipping and shipping tax if not already done with the rest of taxes
			{
				$amount -= floatval($cart->get_shipping_total('edit'));
				if ($inc_tax)
					$amount -= floatval($cart->get_shipping_tax('edit'));
			}
			$amount = max(0, $amount);
		}
		else
		{
			$categories = $this->getProductCategories();
			$exclude = $this->getProductExcludedCategories();
			$noEarning = $this->getExclusionFromCart($cart);

			if (empty($categories) && empty($exclude) && empty($noEarning))
			{
				$amount = floatval($cart->get_subtotal());
				if ($inc_tax)
					$amount += floatval($cart->get_subtotal_tax());
			}
			else foreach ($cart->get_cart() as $item)
			{
				$pId = (isset($item['variation_id']) && $item['variation_id']) ? $item['variation_id'] : (isset($item['product_id']) ? $item['product_id'] : false);
				if ($pId && !empty($product = \wc_get_product($pId)))
				{
					if (!empty($exclude) && $this->isProductInCategory($product, $exclude))
						continue;
					if (!empty($categories) && !$this->isProductInCategory($product, $categories))
						continue;

					$oriQty = $qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
					$qty = $this->useExclusion($noEarning, $product, $qty);
					if ($qty > 0)
					{
						if ($oriQty == $qty && isset($item['line_total']) && (!$inc_tax || isset($item['line_tax'])))
						{
							// get already computed price
							$amount += $item['line_total'];
							if ($inc_tax)
								$amount += $item['line_tax'];
						}
						else
						{
							if ($inc_tax)
								$amount += floatval(\wc_get_price_including_tax($product)) * $qty;
							else
								$amount += floatval(\wc_get_price_excluding_tax($product)) * $qty;
						}
					}
				}
			}

			if ($this->getShipping())
			{
				$amount += floatval($cart->get_shipping_total('edit'));
				if ($inc_tax)
					$amount += floatval($cart->get_shipping_tax('edit'));
			}
		}
		if ($amount < $this->getMinAmount())
			$amount = 0;
		$amount = $this->roundPrice($amount);
		return intval(round($this->getPointsForAmount($amount) * $this->getMultiplier()));
	}

	function getPointsForOrder(\WC_Order $order)
	{
		if (!$this->acceptOrder($order))
			return 0;
		if (!$this->isValidOriginByOrder($order))
			return 0;
		if(!$this->isValidCurrency($order))
			return 0;

		$inc_tax = !empty(\get_option('lws_woorewards_order_amount_includes_taxes', ''));
		$amount = 0;
		if ($this->getAfterDiscount())
		{
			$amount = $order->get_total('edit');
			if (!$inc_tax)
				$amount -= $order->get_total_tax('edit'); // remove shipping tax too

			if (!$this->getShipping()) // remove shipping and shipping tax if not already done with the rest of taxes
			{
				$amount -= floatval($order->get_shipping_total('edit'));
				if ($inc_tax)
					$amount -= floatval($order->get_shipping_tax('edit'));
			}
			$amount = max(0, $amount);
		}
		else
		{
			$categories = $this->getProductCategories();
			$exclude = $this->getProductExcludedCategories();
			$noEarning = $this->getExclusionFromOrder($order);

			if (empty($categories) && empty($exclude) && empty($noEarningà))
			{
				$amount = floatval($order->get_subtotal());
				if ($inc_tax)
					$amount = floatval($order->get_total());
			}
			else foreach ($order->get_items() as $item)
			{
				$pId = (isset($item['variation_id']) && $item['variation_id']) ? $item['variation_id'] : (isset($item['product_id']) ? $item['product_id'] : false);
				if ($pId && !empty($product = \wc_get_product($pId)))
				{
					if (!empty($exclude) && $this->isProductInCategory($product, $exclude))
						continue;
					if (!empty($categories) && !$this->isProductInCategory($product, $categories))
						continue;

					$oriQty = $qty = isset($item['quantity']) ? intval($item['quantity']) : 1;
					$qty = $this->useExclusion($noEarning, $product, $qty);
					if ($qty > 0)
					{
						if ($oriQty == $qty)
						{
							// get already computed price
							$amount += $item->get_subtotal();
							if ($inc_tax)
								$amount += $item->get_subtotal_tax();
						}
						else
						{
							if ($inc_tax)
								$amount += floatval(\wc_get_price_including_tax($product)) * $qty;
							else
								$amount += floatval(\wc_get_price_excluding_tax($product)) * $qty;
						}
					}
				}
			}

			if ($this->getShipping())
			{
				$amount += floatval($order->get_shipping_total('edit'));
				if ($inc_tax)
					$amount += floatval($order->get_shipping_tax('edit'));
			}
		}
		if ($amount < $this->getMinAmount())
			$amount = 0;
		$amount = $this->roundPrice($amount);
		return intval(round($this->getPointsForAmount($amount) * $this->getMultiplier()));
	}
}
