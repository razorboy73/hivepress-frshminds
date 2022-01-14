<?php
namespace LWS\WOOREWARDS\PRO\Migration\Methods;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();
require_once LWS_WOOREWARDS_PRO_INCLUDES . '/migration/methods/metakey.php';

class HostPlugin extends \LWS\WOOREWARDS\PRO\Migration\Methods\MetaKey
{
	/** @return (array) the json that will be send,
	 * An array with each entries as {email, points} */
	public function export($value, $arg)
	{
		return parent::export('hp_woo_rewards_points', $arg);
	}

	/** @return (string) human readable name */
	public function getTitle()
	{
		return __("HostPlugin", 'woorewards-pro');
	}
}
