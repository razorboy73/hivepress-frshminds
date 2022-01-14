<?php
namespace LWS\WOOREWARDS\PRO\Ui\AdminScreens;
// don't call the file directly
if (!defined('ABSPATH')) exit();

class Widgets
{
	static function getTab(&$page)
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/socials.php';
		$showPoints = $page['tabs']['sty_widgets']['groups']['showpoints'];
		$mediaSizes = array();

		$tab = array(
			'id' 	=> 'sty_widgets',
			'title'	=>  __("Widgets", 'woorewards-pro'),
			'icon'	=> 'lws-icon-components',
			'vertnav' => true,
			'groups' => array(
				'showpoints' => array(
					'id'	=> $showPoints['id'],
					'title'	=> $showPoints['title'],
					'icon'	=> 'lws-icon-star-empty',
					'text'	=> $showPoints['text'],
					'extra'	=> $showPoints['extra'],
					'fields' => array(
						'spunconnected' => $showPoints['fields']['spunconnected'],
						'showpoints' => $showPoints['fields']['showpoints'],
					)
				),
				'coupons' => array(
					'id'	=> 'coupons',
					'title'	=> __('Owned Coupons', 'woorewards-pro'),
					'icon'	=> 'lws-icon-coupon',
					'text'	=> __("In this Widget, customers can see the WooCommerce coupons they own.", 'woorewards-pro'),
					'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/widgets/owned-coupons/'),
					'fields' => array(
						'clunconnected'    => array(
							'id' => 'lws_wooreward_wc_coupons_nouser',
							'title' => __("Text displayed if user not connected", 'woorewards-pro'),
							'type' => 'text',
							'extra' => array(
								'size' => '50',
								'placeholder' => __("Please log in to see the coupons you have", 'woorewards-pro'),
							)
						),
						'ifemptycoupon' => array(
							'id' => 'lws_wooreward_wc_coupons_empty',
							'title' => __("Text displayed if no coupon available", 'woorewards-pro'),
							'type' => 'text',
							'extra' => array(
								'size' => '50',
								'placeholder' => __("No coupon available", 'woorewards-pro'),
							)
						),
						'couponslist' => array(
							'id' => 'lws_woorewards_wc_coupons_template',
							'type' => 'stygen',
							'extra' => array(
								'purpose' => 'filter',
								'template' => 'wc_shop_coupon',
								'html' => false,
								'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/coupons.css',
								'subids' => array('lws_woorewards_wc_coupons_template_head' => "WooRewards - Coupons Widget - Header")
							)
						),
					)
				),
				'stdrewards' => array(
					'id'	=> 'stdrewards',
					'title'	=> __('Standard System Rewards', 'woorewards-pro'),
					'icon'	=> 'lws-icon-present',
					'text'	=> __("In this Widget, customers can see the Rewards they can unlock in a Standard points and rewards system.", 'woorewards-pro') . "<br/>"
						. sprintf(__("If you change the 'Reward Cost' text, use %s to display the reward cost (eg : 100 Points)", 'woorewards-pro'), "<span style='font-weight:bold;color:#366'>[rw_cost]</span>") . "<br/>"
						. sprintf(__("If you change the 'Need More Points' text, use %s to display the reward cost and %s to display the points still needed", 'woorewards-pro'), "<span style='font-weight:bold;color:#366'>[rw_cost]</span>", "<span style='font-weight:bold;color:#366'>[rw_more]</span>"),
					'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/widgets/rewards/'),
					'fields' => array(
						'stdusegrid' => array(
							'id'    => 'lws_woorewards_rewards_use_grid',
							'title' => __("Use grid display instead of table display", 'woorewards-pro'),
							'type'  => 'box',
							'extra' => array(
								'default' => 'on',
								'class' => 'lws_checkbox',
								'help' => __("Until MyRewards 3.4, this widget used html tables to display rewards.", 'woorewards-pro') . "<br/>"
									. __("If you've set up the widget before that version, checking that box will force you to style it again", 'woorewards-pro')
							)
						),
						'stdimagesize' => array(
							'id' => 'lws_woorewards_rewards_image_size',
							'title' => __("Image Size", 'woorewards-pro'),
							'type'  => 'lacselect',
							'extra' => array(
								'id'       => 'lws_woorewards_rewards_image_size',
								'maxwidth' => '500px',
								'ajax' => 'lws_adminpanel_get_media_sizes',
								'mode' => 'select',
								'help' => __("Default size should be lws_wr_thumbnail. If you change css to enlarge the images, you should set a larger default image size preventing you from getting blurred images.", 'woorewards-pro'),
 							)
						),
						'stdrewards' => array(
							'id' => 'lws_woorewards_rewards_template',
							'type' => 'stygen',
							'extra' => array(
								'purpose' => 'filter',
								'template' => 'rewards_template',
								'html' => false,
								'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/' . (empty(\get_option('lws_woorewards_rewards_use_grid', 'on')) ? 'rewards.css' : 'gridrewards.css'),
								'subids' => array(
									'lws_woorewards_rewards_widget_unlock' => "WooRewards - Rewards Widget - Unlock Button",
									'lws_woorewards_rewards_widget_locked' => "WooRewards - Rewards Widget - Locked Button",
									'lws_woorewards_rewards_widget_cost' => "WooRewards - Rewards Widget - Reward Cost",
									'lws_woorewards_rewards_widget_more' => "WooRewards - Rewards Widget - More Points Needed",
								),
							)
						),
					)
				),
				'levrewards' => array(
					'id'	=> 'levrewards',
					'title'	=> __('Leveling System Rewards', 'woorewards-pro'),
					'icon'	=> 'lws-icon-g-chart',
					'text'	=> __("In this Widget, customers can see the Rewards they can win in a Levelling points and rewards system.", 'woorewards-pro'),
					'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/widgets/rewards/'),
					'fields' => array(
						'levrewards' => array(
							'id' => 'lws_woorewards_loyalties_template',
							'type' => 'stygen',
							'extra' => array(
								'purpose' => 'filter',
								'template' => 'loyalties_template',
								'html' => false,
								'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/loyalties.css',
								'help' => __("In this Widget, customers can see the Rewards they can win in a Levelling points and rewards system.", 'woorewards-pro') . "<br/>"
							)
						),
					)
				),
				'events' => array(
					'id'	=> 'events',
					'title'	=> __('Earning Points', 'woorewards-pro'),
					'icon'	=> 'lws-icon-trend-up',
					'text'	=> __("In this Widget, customers can see what they need to do in order to earn points", 'woorewards-pro'),
					'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/widgets/points/'),
					'fields' => array(
						'events' => array(
							'id' => 'lws_woorewards_events_template',
							'type' => 'stygen',
							'extra' => array(
								'purpose' => 'filter',
								'template' => 'events_template',
								'html' => false,
								'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/events.css',
								'subids' => array(
									'lws_woorewards_events_widget_message' => "WooRewards - Earning methods - Header",
									'lws_woorewards_events_widget_text' => "WooRewards - Earning methods - Description",
								),
							)
						),
					)
				),
				'badges' => array(
					'id' => 'badges',
					'icon'	=> 'lws-icon-cockade',
					'title' => __("Badges", 'woorewards-pro'),
					'text' => __("In this Widget, customers can see the badges available and the ones they own", 'woorewards-pro'),
					'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/widgets/badges/'),
					'fields' => array(
						'badges' => array(
							'id' => 'lws_woorewards_badges_template',
							'type' => 'stygen',
							'extra' => array(
								'purpose' => 'filter',
								'template' => 'badges_template',
								'html' => false,
								'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/badges.css',
								'subids' => array(
									'lws_woorewards_badges_widget_message' => "WooRewards - Badges - Title",
								),
							)
						),
					)
				),
				'achievements' => array(
					'id' => 'achievements',
					'icon'	=> 'lws-icon-trophy',
					'title' => __("Achievements", 'woorewards-pro'),
					'text' => __("In this Widget, customers can see the achievements and their progress", 'woorewards-pro'),
					'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/widgets/achievements/'),
					'fields' => array(
						'achievements' => array(
							'id' => 'lws_woorewards_achievements_template',
							'type' => 'stygen',
							'extra' => array(
								'purpose' => 'filter',
								'template' => 'achievements_template',
								'html' => false,
								'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/achievements.css',
								'subids' => array(
									'lws_woorewards_achievements_widget_message' => "WooRewards - Achievements - Title",
								),
							)
						),
					)
				),
				//'social_connect' => \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Socials::getGroupSocialConnect(),
				'social_share'   => \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Socials::getGroupSocialShare(),
			)
		);
		return $tab;
	}
}
