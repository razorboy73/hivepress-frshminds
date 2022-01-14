<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/** Earn point the first time a customer complete an order. */
class FirstOrder extends \LWS\WOOREWARDS\Events\FirstOrder
implements \LWS\WOOREWARDS\PRO\Events\I_CartPreview
{
	use \LWS\WOOREWARDS\PRO\Events\T_Order;

	function getClassname()
	{
		return 'LWS\WOOREWARDS\Events\FirstOrder';
	}

	function getPointsForProduct(\WC_Product $product)
	{
		return 0;
	}

	function getPointsForCart(\WC_Cart $cart)
	{
		$user = \LWS\Adminpanel\Tools\Conveniences::getCustomer(\wp_get_current_user(), $cart);
		if( $this->getOrderCount($user) > 0 )
			return 0;
		return $this->getMultiplier();
	}

	function getPointsForOrder(\WC_Order $order)
	{
		$customer = \LWS\Adminpanel\Tools\Conveniences::getCustomer(false, $order);
		if( $this->getOrderCount($customer) > 1 )
			return 0;
		return $this->getMultiplier();
	}
}
