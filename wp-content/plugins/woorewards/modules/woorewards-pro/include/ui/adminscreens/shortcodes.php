<?php

namespace LWS\WOOREWARDS\PRO\Ui\AdminScreens;
// don't call the file directly
if (!defined('ABSPATH')) exit();

class Shortcodes
{
	static function getTab()
	{
		$tab = array(
			'id' 	=> 'sty_shortcodes',
			'title'	=>  __("Shortcodes", 'woorewards-pro'),
			'icon'	=> 'lws-icon-shortcode',
			'vertnav' => true,
			'groups' => array(
				'shortcodes'  => self::getGroupMain(),
				'widgets'     => self::getGroupWidget(),
				'woocommerce' => self::getGroupWC(),
				'sponsorship' => self::getGroupSponsorship(),
				'social'      => self::getGroupSocial(),
			)
		);
		if (\get_option('lws_woorewards_enable_leaderboard'))
			$tab['groups']['shortcodes']['fields']['leaderboard'] = self::getFieldLeaderBoard();
		return $tab;
	}

	static function getGroupMain()
	{
		return array(
			'id'	=> 'shortcodes',
			'title'	=> __('Shortcodes', 'woorewards-pro'),
			'icon'	=> 'lws-icon-shortcode',
			'text'	=> __("In this section, you will find various shortcodes you can use on your website. These shortcodes don't have a widget counterpart.", 'woorewards-pro'),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/shortcodes/shortcodes/'),
			'fields' => array(
				'simplepoints'    => array(
					'id' => 'lws_woorewards_sc_simple_points',
					'title' => __("Simple Points Display", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_simple_points system="set the name of your system here"]',
						'description' =>  __("This simple shortcode is used to display the points for a specific points and rewards system with no decoration.", 'woorewards-pro') . "<br/>" .
							__("This is very convenient if you want to display points within a phrase for example.", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'system',
								'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
							),
						),
						'flags' => array('current_user_id'),
					)
				),
				'pointsvalue'    => array(
					'id' => 'lws_woorewards_sc_points value',
					'title' => __("Points Value Display", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_points_value system="set the name of your system here"]',
						'description' =>  __("This simple shortcode is used to display how much his points are worth.", 'woorewards-pro') . "<br/>" .
							__("This only works if your points and rewards system is set to points on cart.", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'system',
								'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
							),
							array(
								'option' => 'text',
								'desc' => __("(Optional) The text displayed before the points value.", 'woorewards-pro'),
							),
							array(
								'option' => 'raw',
								'desc' => __("(Optional) If set to true, the result will be displayed as a simple text. Otherwise, it will be wrapped in stylable elements", 'woorewards-pro'),
							),
						),
						'flags' => array('current_user_id'),
					)
				),
				'username'    => array(
					'id' => 'lws_woorewards_sc_username',
					'title' => __("User Name and Title", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_user_name title="yes" raw="no"]',
						'description' =>  __("This shortcode displays the user name and title", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'title',
								'desc' => __("Shows the title if user unlocked a title reward.", 'woorewards-pro'),
							),
							array(
								'option' => 'raw',
								'desc' => __("Defines if the name and title are put in stylable elements or not.", 'woorewards-pro'),
							),
						),
						'flags' => array('current_user_id'),
					)
				),
				'pointsexpiration'    => array(
					'id' => 'lws_woorewards_sc_pointsexpiration',
					'title' => __("Points Expiration", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_points_expiration]',
						'description' =>  __("This shortcode displays the date where user points will expire. It only works for inactivity points expiration.", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'system',
								'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, <b>all</b> systems will be displayed.", 'woorewards-pro'),
							),
							array(
								'option' => 'force',
								'desc' => __("(Optional) If set, the points will be shown even if the user currently doesn’t have access to the points and rewards system.", 'woorewards-pro'),
							),
							array(
								'option' => 'format',
								'desc' => __("Set the date format to display the date. Set this to 'days' to display a number of days instead of a date", 'woorewards-pro'),
							),
							array(
								'option' => 'raw',
								'desc' => __("Defines if the date is put in stylable elements or not.", 'woorewards-pro'),
							),
						),
						'flags' => array('current_user_id'),
					)
				),
				'transactions'    => array(
					'id' => 'lws_woorewards_sc_transactionalpointsexpiration',
					'title' => __("Transactional Points expiration", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_points_transactions system="set the name of your system here" force="true" columns=""]',
						'description' =>  __("This shortcode shows to customers the points they have on a points and rewards system.", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'system',
								'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column.", 'woorewards-pro'),
							),
							array(
								'option' => 'force',
								'desc' => __("(Optional) If set, the points will be shown even if the user currently doesn’t have access to the points and rewards system.", 'woorewards-pro'),
							),
							array(
								'option' => 'columns',
								'desc' => sprintf(
									__('(Optional) The list of columns to display in: %1$s. Defaults is %2$s.', 'woorewards-pro'),
									'<i>system, points, expiry, reason, date</i>',
									'<i>points, expiry</i>'
								),
							),
							array(
								'option' => 'titles',
								'desc' => __('(Optional) The list of columns titles. If not provided, default titles are used. Set it empty for no header at all.', 'woorewards-pro'),
							),
							array(
								'option' => 'tiles',
								'desc' => sprintf(
									__('(Optional) This replaces the simple table by a tiles display. Value should be %1$s / %2$s. Default is %3$s.', 'woorewards-pro'),
									'<i>yes</i>', '<i>no</i>', '<i>tiles="no"</i>'
								),
							),
						),
						'flags' => array('current_user_id'),
					)
				),
				'nickname'    => array(
					'id' => 'lws_woorewards_sc_username',
					'title' => __("User Nickname and Title", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_nickname title="yes" raw="no"]',
						'description' =>  __("This shortcode displays the user nickname (if any) and title", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'title',
								'desc' => __("Shows the title if user unlocked a title reward.", 'woorewards-pro'),
							),
							array(
								'option' => 'raw',
								'desc' => __("Defines if the nickname and title are put in stylable elements or not.", 'woorewards-pro'),
							),
						),
						'flags' => array('current_user_id'),
					)
				),
				'nextlevel'    => array(
					'id' => 'lws_woorewards_sc_next_level',
					'title' => __("Points to next level", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_next_level_points system="set the name of your system here"]',
						'description' =>  __("This shortcode displays the points needed to reach the next level/reward of a points and rewards system.", 'woorewards-pro') . "<br/>" .
							__("Use it to motivate customers to reach a higher level.", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'system',
								'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
							),
							array(
								'option' => 'prefix',
								'desc' => __("(Optional) The text displayed before the points needed.", 'woorewards-pro'),
							),
							array(
								'option' => 'suffix',
								'desc' => __("(Optional) The text displayed after the points needed.", 'woorewards-pro'),
							),
							array(
								'option' => 'currency',
								'desc' => __("(Optional)  If set, the points will be displayed with the points and rewards system's currency.", 'woorewards-pro'),
							),
						),
						'flags' => array('current_user_id'),
					)
				),
				'pointsoncart' => array(
					'id' => 'wr_points_on_cart',
					'title' => __("Points on Cart Tool", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => "[wr_points_on_cart system='system_name']",
						'description' =>  __("This shortcode is used to display the Points on Cart tool.", 'woorewards-pro') . "<br/>" .
							__("You can customize its appearance in the WooCommerce Tab.", 'woorewards-pro'),
						'options'   => array(
							array(
								'option' => 'system',
								'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
							),
						),
					)
				),
				'maxpointsoncart' => array(
					'id' => 'wr_max_points_on_cart',
					'title' => __("Maximum Point Amount on Cart", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => "[wr_max_points_on_cart system='system_name' raw='']",
						'description' =>  __("This shortcode will show the maximum quantity of Point that could be used on this cart.", 'woorewards-pro'),
						'options'   => array(
							array(
								'option' => 'system',
								'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
							),
							array(
								'option' => 'raw',
								'desc' => __("(Optional) If set, the amount will be a simple text. Otherwise, it will be presented inside a stylable element", 'woorewards-pro'),
							),
						),
					)
				),
				'userlevel'    => array(
					'id' => 'lws_woorewards_sc_userlevel',
					'title' => __("User Level", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_user_level system="set the name of your system here" nolevel="No level message"]',
						'description' =>  __("This shortcode displays the current user level in a leveling points and rewards system.", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'system',
								'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
							),
							array(
								'option' => 'nolevel',
								'desc' => __("The text displayed if the user didn't reach the first level", 'woorewards-pro'),
							),
						),
						'flags' => array('current_user_id'),
					)
				),
				'progressbar'    => array(
					'id' => 'lws_woorewards_sc_progressbar',
					'title' => __("Progress Bar", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_progress_bar system="set the name of your system here" header="Your Current Progress"]',
						'description' =>  __("This shortcode displays a progress bar for a points and rewards system.", 'woorewards-pro') . "<br/>" .
							__("This is very useful to incentivise customers to reach a higher level.", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'system',
								'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
							),
							array(
								'option' => 'header',
								'desc' => __("The text displayed before the progress bar", 'woorewards-pro'),
							),
						),
						'flags' => array('current_user_id'),
					)
				),
				'progressstyle' => array(
					'id' => 'lws_woorewards_progressbar_template',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'progressbar_template',
						'html' => false,
						'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/progressbar.css',
					)
				),
				'history'    => array(
					'id' => 'lws_woorewards_sc_history',
					'title' => __("Points History", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => "[wr_show_history system='system_name1,system_name2' count='15']",
						'description' =>  __("This shortcode displays a user's points history for one or several points and rewards systems.", 'woorewards-pro'),
						'options'     => array(
							array(
								'option' => 'system',
								'desc' => __("(Optional) The points and rewards systems you want to display (comma separated). You can find this value in <strong>MyRewards → points and rewards systems</strong>, in the <b>Shortcode Attribute</b> column.", 'woorewards-pro'),
							),
							array(
								'option' => 'count',
								'desc' => __("(Optional) The number of rows displayed. Default is 15.", 'woorewards-pro'),
							),
						),
						'flags' => array('current_user_id'),
					)
				),
				'historystyle' => array(
					'id' => 'lws_woorewards_history_template',
					'type' => 'stygen',
					'extra' => array(
						'purpose' => 'filter',
						'template' => 'history_template',
						'html' => false,
						'css' => LWS_WOOREWARDS_CSS . '/templates/history.css',
					)
				),
			),
		);
	}

	static function getGroupWidget()
	{
		return array(
			'id'	=> 'widgets',
			'title'	=> __('Widgets Shortcodes', 'woorewards-pro'),
			'icon'	=> 'lws-icon-components',
			'text'	=> __("In this section, you will find shortcodes that also exist as widgets.", 'woorewards-pro') . "<br/>"
				. __("In some cases, shortcodes provide extra options that aren't available in widgets.", 'woorewards-pro'),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/shortcodes/widget-shortcodes/'),
			'fields' => array(
				'showpoints'    => array(
					'id' => 'lws_woorewards_sc_show_points',
					'title' => __("Display Points", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_show_points system="set the name of your system here" force="true" title="your title" more_details_url="more details button url"]',
						'description' =>  __("This shortcode shows to customers the points they have on a points and rewards system.", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'system',
								'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
							),
							array(
								'option' => 'force',
								'desc' => __("(Optional) If set, the points will be shown even if the user currently doesn’t have access to the points and rewards system.", 'woorewards-pro'),
							),
							array(
								'option' => 'title',
								'desc' => __("(Optional) The text displayed before the points.", 'woorewards-pro'),
							),
							array(
								'option' => 'more_details_url',
								'desc' => __("(Optional) An url linking to a page with more details on the points and rewards systems.", 'woorewards-pro'),
							),
							array(
								'option' => 'show_currency',
								'desc' => __("(Optional) If set, the number of points displayed will show the points currency.", 'woorewards-pro'),
							),
						),
						'flags' => array('current_user_id'),
						'style_url' => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'sty_widgets'), \admin_url('admin.php'))) . '#lws_group_targetable_showpoints',
					)
				),
				'coupons'    => array(
					'id' => 'lws_woorewards_sc_shop_coupons',
					'title' => __("Owned Coupons", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_shop_coupons header=”Here is a list of your coupons”]',
						'description' =>  __("This shortcode shows to customers the woocommerce coupons they currently have.", 'woorewards-pro'),
						'options'   => array(
							array(
								'option' => 'header',
								'desc' => __("The text displayed before the coupons.", 'woorewards-pro'),
							),
						),
						'style_url' => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'sty_widgets'), \admin_url('admin.php'))) . '#lws_group_targetable_coupons',
					)
				),
				'showrewards'    => array(
					'id' => 'lws_woorewards_sc_show_rewards',
					'title' => __("Rewards", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_show_rewards system="set the name of your system here" shared="true" force="true" title="your title" granted="all|only|excluded"]',
						'description' =>  __("This shortcode shows to customers the rewards they can earn in a points and rewards system.", 'woorewards-pro'),
						'options'   => array(
							array(
								'option' => 'system',
								'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
							),
							array(
								'option' => 'title',
								'desc' => __("The text displayed before the rewards.", 'woorewards-pro'),
							),
							array(
								'option' => 'shared',
								'desc' => __("If systems share the same points pool, you can show the rewards of all shared systems together. <strong>Warning</strong> : This only works if systems use the same type (Standard or Leveling).", 'woorewards-pro'),
							),
							array(
								'option' => 'force',
								'desc' => __("If set, the points will be shown even if the user currently doesn’t have access to the points and rewards system.", 'woorewards-pro'),
							),
							array(
								'option' => 'granted',
								'desc' => __("Select the rewards you want to show to customers : ", 'woorewards-pro')
									. "<ul><li><strong>all</strong> : " . __("All rewards of the selected points and rewards system", 'woorewards-pro') . "</li>"
									. "<li><strong>only</strong> : " . __("Only for logged users – Show rewards they can unlock with their points", 'woorewards-pro') . "</li>"
									. "<li><strong>excluded</strong> : " . __("Only for logged users – Show rewards for which users don’t have enough points", 'woorewards-pro') . "</li></ul>",
							),
						),
						'style_url' => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'sty_widgets'), \admin_url('admin.php'))) . '#lws_group_targetable_stdrewards',
					)
				),
				'earning'    => array(
					'id' => 'lws_woorewards_sc_earning_points',
					'title' => __("Actions to earn points", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_events system="set the name of your system here" shared="true" force="true" header="your header" text="your custom text"]',
						'description' =>  __("This shortcode shows to customers the actions to perform in order to earn points.", 'woorewards-pro'),
						'options'   => array(
							array(
								'option' => 'system',
								'desc' => __("The points and rewards system you want to display. You can find this value in <strong>MyRewards → Points and Rewards</strong>, in the <b>Shortcode Attribute</b> column. If you don’t set this value, nothing will be displayed.", 'woorewards-pro'),
							),
							array(
								'option' => 'shared',
								'desc' => __("If systems share the same points pool, you can show the rewards of all shared systems together. <strong>Warning</strong> : This only works if systems use the same type (Standard or Leveling).", 'woorewards-pro'),
							),
							array(
								'option' => 'force',
								'desc' => __("If set, the points will be shown even if the user currently doesn’t have access to the points and rewards system.", 'woorewards-pro'),
							),
							array(
								'option' => 'header',
								'desc' => __(" The text displayed over the earning methods list.", 'woorewards-pro'),
							),
							array(
								'option' => 'text',
								'desc' => __("This text is set to explain to customers what the information displayed is about.", 'woorewards-pro'),
							),
						),
						'style_url' => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'sty_widgets'), \admin_url('admin.php'))) . '#lws_group_targetable_events',
					)
				),
				'badges'    => array(
					'id' => 'lws_woorewards_sc_badges',
					'title' => __("Badges", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[lws_badges header=”<my Own Title>” display=”all|owned”]',
						'description' =>  __("This shortcode shows badges to customers.", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'header',
								'desc' => __("The text displayed before the badges.", 'woorewards-pro'),
							),
							array(
								'option' => 'display',
								'desc' => __("Select if you want to show all existing badges (all) or only the ones owned by the customer (owned)", 'woorewards-pro'),
							),
						),
						'flags' => array('current_user_id'),
						'style_url' => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'sty_widgets'), \admin_url('admin.php'))) . '#lws_group_targetable_badges',
					)
				),
				'achievements'    => array(
					'id' => 'lws_woorewards_sc_achievements',
					'title' => __("Achievements", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[lws_achievements header=”<my Own Title>” display=”all|owned”]',
						'description' =>  __("This shortcode shows existing achievements.", 'woorewards-pro'),
						'options' => array(
							array(
								'option' => 'header',
								'desc' => __("The text displayed before the achievements.", 'woorewards-pro'),
							),
							array(
								'option' => 'display',
								'desc' => __("Select if you want to show all existing achievements (all) or only the ones unlocked by the customer (owned)", 'woorewards-pro'),
							),
						),
						'flags' => array('current_user_id'),
						'style_url' => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'sty_widgets'), \admin_url('admin.php'))) . '#lws_group_targetable_achievements',
					)
				),
			)
		);
	}

	static function getGroupWC()
	{
		return array(
			'id'	=> 'woocommerce',
			'title'	=> __('WooCommerce Shortcodes', 'woorewards-pro'),
			'icon'	=> 'lws-icon-cart-2',
			'text'	=> __("In this section, you will find shortcodes specific to WooCommerce.", 'woorewards-pro'),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/shortcodes/woocommerce-shortcodes/'),
			'fields' => array(
				'user_loyalties'    => array(
					'id' => 'lws_woorewards_sc_user_loyalties',
					'title' => __("Loyalty and Rewards", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_user_loyalties]',
						'description' =>  __("This shortcode shows all loyalty and rewards information visible in WooCommerce's 'My Account' page.", 'woorewards-pro'),
						'style_url' => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'wc_settings'), \admin_url('admin.php'))) . '#lws_group_targetable_myaccountlarview',
					)
				),
				'cart_coupons'    => array(
					'id' => 'lws_woorewards_sc_cart_coupons',
					'title' => __("Cart Coupons", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_cart_coupons_view]',
						'description' =>  __("This shortcode shows coupons owned by the user and proposes a button to apply them on the cart.", 'woorewards-pro'),
						'style_url' => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'wc_settings'), \admin_url('admin.php'))) . '#lws_group_targetable_cartcouponsview',
					)
				),
				'cart_preview'    => array(
					'id' => 'lws_woorewards_sc_cart_preview',
					'title' => __("Cart Points Preview", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_cart_points_preview]',
						'description' =>  __("This shortcode shows the points the customers can get if he validates his actual cart.", 'woorewards-pro'),
						'style_url' => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'wc_settings'), \admin_url('admin.php'))) . '#lws_group_targetable_cartpointspreview',
					)
				),
				'product_preview'    => array(
					'id' => 'lws_woorewards_sc_product_previews',
					'title' => __("Product Points Preview", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[wr_product_points_preview id="product_id"]',
						'description' =>  __("This shortcode shows the points the customers can get if he purchases the actual product.", 'woorewards-pro'),
						'options'   => array(
							array(
								'option' => 'id',
								'desc' => __("The product id. If not specified, the shortcode will try to find the actual product from the page.", 'woorewards-pro'),
							),
						),
						'style_url' => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'wc_settings'), \admin_url('admin.php'))) . '#lws_group_targetable_productpointspreview',
					)
				),
			)
		);
	}

	static function getGroupSponsorship()
	{
		return array(
			'id'	=> 'sponsorship',
			'title'	=> __('Sponsorship Shortcodes', 'woorewards-pro'),
			'icon'	=> 'lws-icon-handshake',
			'text'	=> __("In this section, you will find shortcodes relative to sponsorship.", 'woorewards-pro'),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/shortcodes/sponsorship-shortcodes/'),
			'fields' => array(
				'email_sponsorship'    => array(
					'id' => 'lws_woorewards_sc_email_sponsorship',
					'title' => __("Email Sponsorthip", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[lws_sponsorship header="your header" button="Send" unlogged="true"]',
						'description' =>  __("This shortcode shows to customers a sponsorship email form.", 'woorewards-pro'),
						'options'   => array(
							array(
								'option' => 'header',
								'desc' => __("The text displayed before the email sponsorship.", 'woorewards-pro'),
							),
							array(
								'option' => 'button',
								'desc' => __("The text displayed in the Submit button", 'woorewards-pro'),
							),
							array(
								'option' => 'unlogged',
								'desc' => __('(Optional) If set, unlogged users will be able to use the email sponsorship.', 'woorewards-pro'),
							)
						),
						'style_url' => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.settings', 'tab' => 'sp_settings'), \admin_url('admin.php'))) . '#lws_group_targetable_sponsor_widget_style',
					)
				),
				'link_sponsorship'    => array(
					'id' => 'lws_woorewards_sc_link_sponsorship',
					'title' => __("Sponsorthip Link", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => '[lws_sponsorship_link header="your header" display="both|qrcode|link" url="your_url"]',
						'description' =>  __("This shortcode shows to customers a sponsorship/referral link or QR Code.", 'woorewards-pro'),
						'options'   => array(
							array(
								'option' => 'header',
								'desc' => __("The text displayed before the sponsorship link or QR Code.", 'woorewards-pro'),
							),
							array(
								'option' => 'display',
								'desc' => __("Select the display type you want to show to customers : ", 'woorewards-pro')
									. "<ul><li><strong>both</strong> : " . __("QR Code and Link will be displayed", 'woorewards-pro') . "</li>"
									. "<li><strong>qrcode</strong> : " . __("Only the QR Code will be displayed", 'woorewards-pro') . "</li>"
									. "<li><strong>link</strong> : " . __("Only the Link will be displayed", 'woorewards-pro') . "</li></ul>",
							),
							array(
								'option' => 'url',
								'desc' => __("By default, the shortcode shares the url of the page it’s displayed on. You can override that setting by setting an url in this option.", 'woorewards-pro'),
							),
						),
						'style_url' => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.settings', 'tab' => 'sp_settings'), \admin_url('admin.php'))) . '#lws_group_targetable_referral',
					)
				),
			)
		);
	}

	static function getFieldLeaderBoard()
	{
		return array(
			'id' => 'lws_woorewards_sc_leaderboard',
			'title' => __("Leaderboard", 'woorewards-pro'),
			'type' => 'shortcode',
			'extra' => array(
				'shortcode' => "[wr_leaderboard system='system_name' count='15']",
				'description' =>  __("This shortcode displays a leaderboard of your customers for a specific Points and Rewards System", 'woorewards-pro'),
				'options'   => array(
					array(
						'option' => 'system',
						'desc' => __("The points and rewards system for which you want to display the leaderboard. You can find this value in <strong>MyRewards → points and rewards systems</strong>, in the <b>Shortcode Attribute</b> column.", 'woorewards-pro'),
					),
					array(
						'option' => 'count',
						'desc' => __("(Optional) The number of rows displayed. Default is 15.", 'woorewards-pro'),
					),
					array(
						'option' => 'columns',
						'desc' => array(
							array(
								'tag' => 'p', 'join' => '<br/>',
								__("(Optional) The Columns to display (comma separated). <b>The order in which you specify the columns will be the grid columns order</b>.", 'woorewards-pro'),
								__(" If not specified, the leaderboard will display the rank, user nickname and points total of the users.", 'woorewards-pro'),
								__(" Here are the different options available :", 'woorewards-pro'),
							),
							array(
								'tag' => 'ul',
								array(
									"rank :",
									__("The user's rank in the leadeboard.", 'woorewards-pro'),
								), array(
									"user_nickname :",
									__("The user's display name.", 'woorewards-pro'),
								), array(
									"points :",
									__("The user's points in the points and rewards system.", 'woorewards-pro'),
								), array(
									"badges :",
									__("The badges owned by the customer. This will display the badges images and their titles on mouseover.", 'woorewards-pro'),
								), array(
									"last_badge :",
									__("The last badge earned by the customer. This will display the badge image and its title on mouseover.", 'woorewards-pro'),
								), array(
									"achievements :",
									__("The achievements unlocked by the customer. This will display the unlocked badges images and their titles on mouseover.", 'woorewards-pro'),
								), array(
									"user_title :",
									__("Displays the user title if he earned one.", 'woorewards-pro'),
								), array(
									"title_date :",
									__("Displays when the user earned his/her title.", 'woorewards-pro'),
								)
							)
						),
					),
					array(
						'option' => 'columns_headers',
						'desc' => __("(Optional) The column headers (comma separated). <b>Must be specified if you specified the columns option</b>. The headers must respect the same order than the ones of the previous option.", 'woorewards-pro'),
					),
					array(
						'option' => 'badge_ids',
						'desc' => __("(Optional) Restriction to specific badges (comma separated). By default, all badges can be displayed in the relevant columns. You can restrict that to a specific list of badges.", 'woorewards-pro'),
					),
					array(
						'option' => 'achievement_ids',
						'desc' => __("(Optional) Restriction to specific achievements (comma separated). By default, all achievements can be displayed in the relevant columns. You can restrict that to a specific list of achievements.", 'woorewards-pro'),
					),
				),
			)
		);
	}

	static function getGroupSocial()
	{
		$groups = array(
			'id'	=> 'socials',
			'title'	=> __('Social networks', 'woorewards-pro'),
			'icon'	=> 'lws-icon-network-communication',
			'text'	=> __("In this section, you will find various shortcodes relative to social networks.", 'woorewards-pro'),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/tutorials/configure-facebook-instagram/'),
			'fields' => array(
				'share' => array(
					'id'    => 'lws_woorewards_sc_social',
					'title' => __("Social Share", 'woorewards-pro'),
					'type'  => 'shortcode',
					'extra' => array(
						'shortcode' => '[lws_social_share header="your own header" text="your text" url="specific url"]',
						'description' =>  __("Use this shortcode to display the social share widget on your pages.", 'woorewards-pro'),
						'options'   => array(
							array(
								'option' => 'header',
								'desc' => __("The text displayed before the coupons.", 'woorewards-pro'),
							),
							array(
								'option' => 'text',
								'desc' => __("The text displayed before the share buttons.", 'woorewards-pro'),
							),
							array(
								'option' => 'url',
								'desc' => __("(Optional) By default, the widget shares the url of the page it’s displayed on. You can override that setting by setting an url in this option.", 'woorewards-pro'),
							),
						),
						'style_url' => \esc_attr(\add_query_arg(array('page' => LWS_WOOREWARDS_PAGE . '.appearance', 'tab' => 'sty_widgets'), \admin_url('admin.php'))) . '#lws_group_targetable_social_share',
					)
				),
				'connect' => array(
					'id' => 'wr_social_connect',
					'title' => __("Connect social account", 'woorewards-pro'),
					'type' => 'shortcode',
					'extra' => array(
						'shortcode' => "[wr_social_connect networks='facebook']",
						'description' =>  array(
							'join' => '<br/>',
							__("This shortcode displays a button to let your customers connect their social network account to your site.", 'woorewards-pro'),
							__("Required if you want to give points for <i>likes</i> or <i>comments</i> on your social network page.", 'woorewards-pro'),
							__("Button stays hidden if customer's social network account is already connected.", 'woorewards-pro'),
						),
						'options'   => array(
							array(
								'option' => 'networks',
								'desc'   => __("The names of social network for which you want a button to appear.", 'woorewards-pro'),
							),
						)
					)
				),
			)
		);
		if (!\get_option('lws_woorewards_wh_fb_sdk_embedded')) {
			$groups['fields']['connect']['extra']['description'][] = array(
				'tag' => 'span style="color:red;"',
				__("For this shortcode to work, you have to enabled Facebook SDK in Features → Social Media.", 'woorewards-pro'),
			);
		}

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/socials.php';
		list($verif, $confirmed) = \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Socials::getVerifiedStatus('facebook');
		if (!$confirmed) {
			$groups['fields']['connect']['extra']['description'][] = array(
				'tag' => 'span style="color:red;"',
				__("Your settings have never been verified. Please check them in Features > Social Media.", 'woorewards-pro'),
			);
		}
		return $groups;
	}
}
