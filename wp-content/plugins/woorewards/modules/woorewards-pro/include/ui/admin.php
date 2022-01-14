<?php

namespace LWS\WOOREWARDS\PRO\Ui;

// don't call the file directly
if (!defined('ABSPATH')) exit();

require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointspoolfilter.php';

/** Create the backend menu and settings pages. */
class Admin
{
	const POOL_OPTION_PREFIX = 'lws-wr-pool-option-';

	public function __construct()
	{
		\LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsPoolFilter::install();

		\add_action('admin_enqueue_scripts', array($this, 'scripts'));
		\add_filter('lws_woorewards_ui_loyalty_tab_get', array($this, 'getLoyaltyPage'));
		\add_filter('lws_adminpanel_pages_' . LWS_WOOREWARDS_PAGE, array($this, 'managePages'));
		\add_filter('lws_woorewards_admin_pool_general_settings', array($this, 'poolGeneralSettings'), 15, 2); // priority to set after
		\add_filter('lws_woorewards_ui_userspoints_filters', array($this, 'userspointsFilters'));

		// grab woocommerce styles if any to appends them on stygen
		\add_filter('woocommerce_enqueue_styles', array($this, 'grabWCStyles'), 0);

		// Add specific support text for configuration help
		\add_filter('lws_adm_support_contents', array($this, 'addSupportText'), 10, 2);

		foreach (array('lws_woorewards_wc_achievements_endpoint_slug', 'lws_woorewards_wc_badges_endpoint_slug', 'lws_woorewards_wc_my_account_endpoint_slug') as $slugOption)
			\add_filter('pre_update_option_' . $slugOption, array($this, 'warnAboutEndpoint404'), 10, 3);

		\add_action('lws_woorewards_after_delete_all', array($this, 'deleteAllData'));
	}

	function warnAboutEndpoint404($value = true, $oldValue = false, $option = false)
	{
		if (\function_exists('\add_rewrite_endpoint')) {
			$key = $value;
			if (!$key) {
				$keys = array(
					'lws_woorewards_wc_my_account_endpoint_slug'   => 'lws_woorewards',
					'lws_woorewards_wc_badges_endpoint_slug'       => 'lws_badges',
					'lws_woorewards_wc_achievements_endpoint_slug' => 'lws_achievements',
				);
				$key = $keys[$option];
			}
			// force flush
			\add_rewrite_endpoint($key, EP_ROOT|EP_PAGES);
			if (!isset($this->flush_rewrite_rules)) {
				$this->flush_rewrite_rules = true;
				\add_action('shutdown', function()use($option){
					\flush_rewrite_rules(true);
				});
			}
		}

		if ($value != $oldValue)
		{
			\lws_admin_add_notice(
				'warnAboutEndpoint404',
				sprintf(
					'<p>%s</p><p>%s</p>',
					__("You just changed a slug in MyAccount tabs.", 'woorewards-pro'),
					__("Sometimes, when trying to go to the Loyalty and Rewards Tab, you will see a “404 – Page does not exist” error. This is a known WordPress permalink issue. To solve it, go to your WordPress administration and go to <strong>Settings → Permalinks</strong>. Once you’re there, scroll to the bottom of the page and click the <strong>Save Changes</strong> button. That will solve the problem.", 'woorewards-pro')
				),
				array('level' => 'info', 'dismissible' => true, 'forgettable' => true)
			);
		}
		return $value;
	}

	public function scripts($hook)
	{
		\wp_register_style('lws-wre-pro-poolssettings', LWS_WOOREWARDS_PRO_CSS . '/poolssettings.min.css', array(), LWS_WOOREWARDS_PRO_VERSION);
		$deps = array('jquery', 'lws-base64', 'lws-tools');
		\wp_register_script('lws-wre-pro-poolssettings', LWS_WOOREWARDS_PRO_JS . '/poolssettings.js', $deps, LWS_WOOREWARDS_PRO_VERSION, true);

		if (false !== strpos($hook, LWS_WOOREWARDS_PAGE))
		{
			\do_action('lws_adminpanel_enqueue_lac_scripts', array('select'));

			\wp_enqueue_script('lws-radio');

			$tab = isset($_GET['tab']) ? $_GET['tab'] : '';
			if (strpos($hook, 'loyalty') !== false)
			{
				foreach ($deps as $dep)
					\wp_enqueue_script($dep);
				\wp_enqueue_script('lws-wre-pro-poolssettings');
				\wp_enqueue_style('lws-wre-pro-poolssettings');
				\wp_enqueue_style('lws-wre-pro-style', LWS_WOOREWARDS_PRO_CSS . '/style.css', array(), LWS_WOOREWARDS_PRO_VERSION);
			}
			else if (false !== strpos($hook, 'settings') && strpos($tab, 'woocommerce') !== false)
			{
				if (\class_exists('\WC_Frontend_Scripts'))
				{
					\WC_Frontend_Scripts::get_styles();
					if (isset($this->wcStyles))
					{
						foreach ($this->wcStyles as $style => $detail)
						{
							\wp_enqueue_style($style, $detail['src'], $detail['deps'], $detail['version'], $detail['media'], $detail['has_rtl']);
						}
					}
				}
			}
			else
			{
				\wp_enqueue_style('lws-wre-userspointsfilters', LWS_WOOREWARDS_PRO_CSS . '/userspointsfilters.css', array(), LWS_WOOREWARDS_PRO_VERSION);
			}

			\wp_enqueue_style('lws-wre-pool-content-edit', LWS_WOOREWARDS_PRO_CSS . '/poolcontentedit.css', array(), LWS_WOOREWARDS_PRO_VERSION);
		}
	}

	function grabWCStyles($scripts)
	{
		if (!isset($this->wcStyles))
			$this->wcStyles = $scripts;
		return $scripts;
	}

	protected function getCurrentPage()
	{
		if( isset($this->currentPage) )
			return $this->currentPage;
		if (isset($_REQUEST['page']) && ($this->currentPage = \sanitize_text_field($_REQUEST['page'])))
			return $this->currentPage;
		if (isset($_REQUEST['option_page']) && ($this->currentPage = \sanitize_text_field($_REQUEST['option_page'])))
			return $this->currentPage;
		return false;
	}

	/** Reorganise pages from the free version to the pro version */
	function managePages($pages)
	{
		$this->standardPages = $pages;
		$proPages = array();
		if (isset($this->standardPages['wr_resume']))
		{
			$proPages['wr_resume'] = $this->standardPages['wr_resume'];
			$proPages['wr_resume']['title'] = __('WooRewards', 'woorewards-pro');
		}

		$proPages['wr_customers'] = $this->getCustomerPage();
		$proPages['wr_loyalty'] = $this->getLoyaltyPage();
		$proPages['wr_wizard'] = $this->getWizardPage();
		$proPages['wr_features'] = $this->getFeaturesPage();
		$proPages['wr_appearance'] = $this->getAppearancePage();
		$proPages['wr_system'] = $this->getSystemPage();
		$proPages['wr_teaser'] = array(
			'id'     => 'wr_teaser',
			'title'  => __('Add-ons', 'woorewards-pro'),
			'teaser' => LWS_WOOREWARDS_UUID,
			'rights' => 'manage_options',
		);
		return $proPages;
	}

	function getCustomerPage()
	{
		$customerPage = $this->standardPages['wr_customers'];

		$customerPage['description'] = array(
			'cast' => 'p',
			__("The customers page lets you track your customers loyalty activity and perform some actions :", 'woorewards-pro'),
			array(
				'tag' => 'ul',
				__("See customers points and history", 'woorewards-pro'),
				__("See customers owned coupons", 'woorewards-pro'),
				__("Add/Subtract Points", 'woorewards-pro'),
				__("Add/Remove Rewards", 'woorewards-pro'),
				__("Add/Remove Badges", 'woorewards-pro'),
				__("Filter by points, activity or inactivity periods", 'woorewards-pro'),
			),
		);
		return $customerPage;
	}

	function getLoyaltyPage($tab = false)
	{
		$description = array(
			array(
				'tag' => 'p',
				__("Points and Rewards systems is the core mechanism of MyRewards.", 'woorewards-pro'),
				__("You can create an infinity of points and rewards systems working together or apart.", 'woorewards-pro'),
				__("For each system, you can set up the following options :", 'woorewards-pro'),
			),
			array(
				'tag' => 'ul',
				array(
					__("How to earn points :", 'woorewards-pro'),
					__("Select how your customers will earn points. More than 20 Methods available", 'woorewards-pro'),
				),
				array(
					__("Rewards :", 'woorewards-pro'),
					__("Set the rewards that your customers will receive for their loyalty", 'woorewards-pro'),
				),
				__("System Type : Use Standard systems or Leveling systems", 'woorewards-pro'),
				__("Currency : Choose how points will be displayed to users", 'woorewards-pro'),
				__("Restricted access : Restrict access to your systems depending on the user role", 'woorewards-pro'),
				__("Points Expiration : Choose between 3 methods for points to expire ... or not", 'woorewards-pro'),
			),
		);

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/pools.php';
		$tabId = 'wr_loyalty';
		$title = __("Points and Rewards", 'woorewards-pro');

		$links = array('' => array('poolfilter' => ''));
		$labels = array('' => _x("All", "Points and rewards system filter", 'woorewards-pro'));
		foreach (\LWS\WOOREWARDS\PRO\Ui\Editlists\Pools::statusList() as $k => $status)
		{
			$links[$k] = array('poolfilter' => $k);
			$labels[$k] = $status;
		}
		$filter = new \LWS\Adminpanel\EditList\FilterSimpleLinks($links, array(), false, $labels);

		$loyaltyPage = array(
			'title' => __("Points and Rewards", 'woorewards-pro'),
			'color' => '#526981',
			'image'		=> LWS_WOOREWARDS_IMG . '/r-loyalty-systems.png',
			'description' => $description,
			'rights'   => 'manage_rewards',
			'id'       => LWS_WOOREWARDS_PAGE . '.loyalty',
			'tabs'   => array(
				$tabId  => array(
					'title'  => $title,
					'id'     => $tabId,
					'hidden' => true,
					'tabs'    => array(
						array(
							'title'  => $title,
							'id'     => 'systems',
							'hidden' => true,
							'groups' => array(
								'systems' => array(
									'id'       => 'systems',
									'title'    => __("Points and Rewards Systems", 'woorewards-pro'),
									'icon'	   => 'lws-icon-present',
									'color'    => '#016087',
									'text'     => array(
										'join' => '<br/>',
										__("Points and Rewards Systems are WooReward's core. This is how your customers <b>earn points and get rewards</b>.", 'woorewards-pro'),
										__("When adding a new system, you'll have two options :", 'woorewards-pro'),
										array(
											'tag' => 'ul',
											__("<b>Standard System</b> : Customers earn points in various ways and can spend their points to unlock rewards.", 'woorewards-pro'),
											__("<b>Leveling System</b> : Customers earn points and unlock levels and rewards as they progress. <b>In a leveling system, customers never spend their points</b>.", 'woorewards-pro'),
										),
									),
									'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/advanced-mechanisms/points-and-rewards-systems/'),
									'editlist' => \lws_editlist(
										\LWS\WOOREWARDS\PRO\Ui\Editlists\Pools::SLUG,
										\LWS\WOOREWARDS\PRO\Ui\Editlists\Pools::ROW_ID,
										new \LWS\WOOREWARDS\PRO\Ui\Editlists\Pools(),
										\LWS\Adminpanel\EditList::DDA,
										$filter
									)
								)
							)
						)
					)
				)
			)
		);

		// build only the required pool page
		$pool = $this->guessCurrentPool($tabId);
		if ($pool)
		{
			$subtab = $pool->getTabId();
			$loyaltyPage['tabs'][$tabId]['tabs'][$subtab] = \apply_filters('lws_woorewards_ui_loyalty_edit_pool_tab', array(
				'title'  => $pool->getOption('display_title'),
				'id'     => $subtab,
				'hidden' => true,
				'groups' => $this->getLoyaltyGroups($pool),
				'delayedFunction' => function () use ($pool)
				{
					echo "<div style='width:50%;'>";
					\do_action('wpml_show_package_language_ui', $pool->getPackageWPML());
					echo "</div>";
				}
			), $pool);
		}

		return $loyaltyPage;
	}

	function getWizardPage()
	{
		$customerPage = $this->standardPages['wr_wizard'];
		$customerPage['description'] = array(
			array(
				'tag' => 'p',
				__("Wizards are made to help you get started or create some specific points and rewards systems more easily.", 'woorewards-pro'),
				__("Select a wizard, follow the setup steps and everything will be generated automatically according to your preferences.", 'woorewards-pro'),
				__("Here are the wizards available :", 'woorewards-pro'),
			),
			array(
				'tag' => 'ul',
				array(
					__("Standard System :", 'woorewards-pro'),
					__("Set up a standard system to let customers win coupons", 'woorewards-pro'),
				),
				array(
					__("Leveling System :", 'woorewards-pro'),
					__("This wizard will help you create a bronze/silver/gold system", 'woorewards-pro'),
				),
				array(
					__("Special Events :", 'woorewards-pro'),
					__("points and rewards systems for special occasions like Christmas", 'woorewards-pro'),
				),
				array(
					__("Double Points :", 'woorewards-pro'),
					__("Create an event where customers can earn twice the points", 'woorewards-pro'),
				),
				array(
					__("Sponsorship :", 'woorewards-pro'),
					__("Sponsors and sponsored are rewarded in this points and rewards system", 'woorewards-pro'),
				),
				array(
					__("Birthday or Anniversary ", 'woorewards-pro'),
					__("Send a special gift on customers birthday or registration anniversary", 'woorewards-pro'),
				),
			)
		);
		return $customerPage;
	}

	function getFeaturesPage()
	{
		$this->standardPages['wr_features']['description'] = array(
			'cast' => 'p',
			__("Activate and set up different MyRewards features in this section : ", 'woorewards-pro'),
			array(
				'tag' => 'ul',
				array(
					__("General Features :", 'woorewards-pro'),
					__("Enable or disable a list of general features that you can decide to use or not", 'woorewards-pro'),
				),
				array(
					__("Sponsorship & Referral :", 'woorewards-pro'),
					__("Set up the sponsorship/referral options", 'woorewards-pro'),
				),
				array(
					__("Badges and Achievements :", 'woorewards-pro'),
					__("Create and manage user badges, achievements and badges rarity", 'woorewards-pro'),
				),
				array(
					__("API :", 'woorewards-pro'),
					__("Set up the API to connect MyRewards with a third party app", 'woorewards-pro'),
				),
			)
		);

		if( (LWS_WOOREWARDS_PAGE . '.settings') != $this->getCurrentPage() )
			return $this->standardPages['wr_features'];

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/socials.php';
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/sponsorship.php';
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/achievements.php';

		$featuresPage = array_merge(
			$this->standardPages['wr_features'],
			array(
				'tabs'     => array(
					'features' => $this->getGeneralFeaturesTab(),
					'sponsorship' => \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Sponsorship::getTab(),
					'badges_achievements' => \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Achievements::getTab(),
					'social' => \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Socials::getTab(),
					'api' => $this->getAPITab(),
				)
			)
		);

		return $featuresPage;
	}

	function getAppearancePage()
	{
		$this->standardPages['wr_appearance']['description'] = array(
			'cast' => 'p',
			__("Set the appearance of everything your customers will see on your website regarding points and rewards systems : ", 'woorewards-pro'),
			array(
				'tag' => 'ul',
				array(
					__("Widgets :", 'woorewards-pro'),
					__("Setup the widgets/shortcodes options and appearance", 'woorewards-pro'),
				),
				array(
					__("Shortcodes :", 'woorewards-pro'),
					__("List of all shortcodes available in MyRewards and how to use them", 'woorewards-pro'),
				),
				array(
					__("WooCommerce :", 'woorewards-pro'),
					__("Additional loyalty information displayed on WooCommerce pages", 'woorewards-pro'),
				),
				array(
					__("Emails :", 'woorewards-pro'),
					__("Customize emails sent to your customers", 'woorewards-pro'),
				),
				array(
					__("Popup :", 'woorewards-pro'),
					__("Setup the popup that your customers see when they unlock a reward", 'woorewards-pro'),
				),
			)
		);

		if( (LWS_WOOREWARDS_PAGE . '.appearance') != $this->getCurrentPage() )
			return $this->standardPages['wr_appearance'];

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/shortcodes.php';
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/widgets.php';
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/woocommerce.php';

		$appearancePage = array_merge(
			$this->standardPages['wr_appearance'],
			array(
				'tabs'     => array(
					'widgets' 	=> \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Widgets::getTab($this->standardPages['wr_appearance']),
					'shortcodes' => \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Shortcodes::getTab(),
					'woocommerce' => \LWS\WOOREWARDS\PRO\Ui\AdminScreens\WooCommerce::getTab($this->standardPages['wr_appearance']),
					'emails' 	=> $this->getEmailsTab(),
					'popup' 	=> $this->getPopupTab(),
					/** Will be added when CE is ready */
					//'templates' => $this->getTemplatesTab(),
				)
			)
		);
		return $appearancePage;
	}

	function getOrderStatusList()
	{
		if (isset($this->orderStatus))
			return $this->orderStatus;

		if (\function_exists('\wc_get_order_statuses'))
		{
			$this->orderStatus = array();
			foreach (\wc_get_order_statuses() as $value => $label)
			{
				if (substr($value, 0, 3) == 'wc-')
					$value = substr($value, 3);
				$this->orderStatus[] = array('value' => $value, 'label' => $label);
			}
		}
		else
		{
			$this->orderStatus = array(
				array('value' => 'pending'   , 'label' => __("Pending payment", 'woorewards-lite')),
				array('value' => 'processing', 'label' => __("Processing", 'woorewards-lite')),
				array('value' => 'on-hold'   , 'label' => __("On hold", 'woorewards-lite')),
				array('value' => 'completed' , 'label' => __("Completed", 'woorewards-lite')),
				array('value' => 'cancelled' , 'label' => __("Cancelled", 'woorewards-lite')),
				array('value' => 'refunded'  , 'label' => __("Refunded", 'woorewards-lite')),
				array('value' => 'failed'    , 'label' => __("Failed", 'woorewards-lite')),
			);
		}
		return $this->orderStatus;
	}

	function getGeneralFeaturesTab()
	{
		$lite = array(
			'settings' => $this->standardPages['wr_features']['tabs']['wc_settings']['groups']['settings'],
		);
		if (isset($this->standardPages['wr_features']['tabs']['wc_settings']['groups']['pointsoncart']))
			$lite['pointsoncart'] = $this->standardPages['wr_features']['tabs']['wc_settings']['groups']['pointsoncart'];
		foreach ($lite as &$grp)
			$grp['class'] = 'half';

		$lite['settings']['fields']['show_cooldown'] = array(
			'id'    => 'lws_woorewards_show_event_cooldown',
			'title' => __("Show Points Cooldown", 'woorewards-pro'),
			'type'  => 'box',
			'extra' => array(
				'class' => 'lws_checkbox',
				'help'  => __("If you set a cooldown on the methods to earn points, select if you want to show the cooldown to customers or not", 'woorewards-pro'),
			)
		);
		$gfTab = array(
			'id'	=> 'general_features',
			'title'	=>  __("General Features", 'woorewards-pro'),
			'icon'	=> 'lws-icon-questionnaire',
			'vertnav' => true,
			'groups' => array_merge($lite, array(
				'a_features' => array(
					'id' 	=> 'advanced_features',
					'title' => __("Advanced Features", 'woorewards-pro'),
					'icon'	=> 'lws-icon-adv-settings',
					'class'	=> 'half',
					'text' 	=> __("The following options are only used in a handful of cases and should only be enabled if you're certain you want to use them", 'woorewards-pro'),
					'fields' => array(
						'enable_multicurrency' => array(
							'id' => 'lws_woorewards_enable_multicurrency',
							'title' => __("Enable multi currency support", 'woorewards-pro'),
							'type' => 'box',
							'extra' => array(
								'class' => 'lws_checkbox',
							)
						),
						'enable_leaderboard' => array(
							'id' => 'lws_woorewards_enable_leaderboard',
							'title' => __("Enable the leaderboard shortcode", 'woorewards-pro'),
							'type' => 'box',
							'extra' => array(
								'class' => 'lws_checkbox',
								'help'	=> 	__("If you enable this shortcode, a new option will be added to the user's My Account page.", 'woorewards-pro'). "<br/>" .
											__("The user will have the possibility to set if he/she accepts to appear on the leaderboard or not.", 'woorewards-pro'),
							)
						),
					)
				),
				'birthday' => array(
					'id' 	=> 'wc_birthday',
					'title' => __("Birthday Settings", 'woorewards-pro'),
					'icon'	=> 'lws-icon-birthday-cake',
					'class'	=> 'half',
					'text' 	=> __("If you decide to give points for customers birthdays, you should check some of the following options to give customers the opportunity to fill in their birthday date.", 'woorewards-pro'),
					'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/points/birthday/'),
					'fields' => array(
						'birthday_checkout' => array(
							'id' => 'lws_woorewards_registration_birthday_field',
							'title' => __("Display a birthday field in the 'checkout' page when the user register at the same time.", 'woorewards-pro'),
							'type' => 'box',
							'extra' => array(
								'class' => 'lws_checkbox',
							)
						),
						'birthday_register' => array(
							'id' => 'lws_woorewards_myaccount_register_birthday_field',
							'title' => __("Display a birthday field in the 'my account register' page.", 'woorewards-pro'),
							'type' => 'box',
							'extra' => array(
								'class' => 'lws_checkbox',
							)
						),
						'birthday_detail' => array(
							'id' => 'lws_woorewards_myaccount_detail_birthday_field',
							'title' => __("Display a birthday field in the 'my account -> details' page.", 'woorewards-pro'),
							'type' => 'box',
							'extra' => array(
								'class' => 'lws_checkbox',
							)
						),
						'birthday_debug' => array(
							'id' => 'lws_woorewards_myaccount_birthday_debug',
							'title' => __("Display Debug information about birthday point earning in user backend profile.", 'woorewards-pro'),
							'type' => 'box',
							'extra' => array(
								'class' => 'lws_checkbox',
								'default' => 'on',
							)
						),
					)
				),
				'refund' => array(
					'id'    => 'refund',
					'icon'	=> 'lws-icon-refund',
					'title' => __("Remove points on order refunds", 'woorewards-pro'),
					'class'	=> 'half',
					'text'  => __("If an order is cancelled or refunded, you have the option to also remove points earned for that order. Select here the order status for which you want to remove loyalty points.", 'woorewards-pro'),
					'fields' => array(
						'refund' => array(
							'id'    => 'lws_woorewards_refund_on_status',
							'title' => __("Order status", 'woorewards-pro'),
							'type'  => 'lacchecklist',
							'extra' => array(
								'source' => $this->getOrderStatusList(),
							)
						),
					)
				),
			)),
		);
		return $gfTab;
	}

	function getAPITab()
	{
		$restPrefix = \trailingslashit(\get_rest_url()) . \LWS\WOOREWARDS\PRO\Core\Rest::getNamespace();
		$restPrefix = "<span class='lws-group-descr-copy lws_ui_value_copy'><div class='lws-group-descr-copy-text content' tabindex='0'>{$restPrefix}</div><div class='lws-group-descr-copy-icon lws-icon lws-icon-copy copy'></div></span>";
		$apiTab = array(
			'id'	=> 'api_settings',
			'title'	=>  __("API", 'woorewards-pro'),
			'icon'	=> 'lws-icon-api',
			'groups' => array(
				'api' => array(
					'id'     => 'api',
					'icon'	=> 'lws-icon-api',
					'title'  => __("REST API", 'woorewards-pro'),
					'text'   => sprintf(__("Define MyRewards REST API settings. API endpoint will be %s", 'woorewards-pro'), $restPrefix),
					'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/api/'),
					'fields' => array(
						'enabled' => array(
							'id'    => 'lws_woorewards_rest_api_enabled',
							'title' => __("Enable REST API", 'woorewards-pro'),
							'type'  => 'box',
							'extra' => array(
								'class' => 'lws_checkbox',
							)
						),
						'wc_auth' => array(
							'id'    => 'lws_woorewards_rest_api_wc_auth',
							'title' => __("Allows authentification by WooCommerce REST API", 'woorewards-pro'),
							'type'  => 'box',
							'extra' => array(
								'default' => 'on',
								'class' => 'lws_checkbox',
							)
						),
					)
				),
				'users' => array(
					'id'     => 'users',
					'icon'	=> 'lws-icon-users-mm',
					'title'  => __("User Permissions", 'woorewards-pro'),
					'text'   => __("Define the website users that can access the different features of the API", 'woorewards-pro'),
					'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/api/'),
					'fields' => array(
						'info' => array(
							'id'    => 'lws_woorewards_rest_api_user_info',
							'title' => __("Users allowed to read general information", 'woorewards-pro'),
							'type'  => 'lacchecklist',
							'extra' => array(
								'predefined' => 'user',
								'tooltips' => __("The checked users can get points and rewards system list.", 'woorewards-pro'),
							)
						),
						'read' => array(
							'id'    => 'lws_woorewards_rest_api_user_read',
							'title' => __("Users allowed to read user information", 'woorewards-pro'),
							'type'  => 'lacchecklist',
							'extra' => array(
								'predefined' => 'user',
								'tooltips' => __("The checked users can get other users point amounts and history.", 'woorewards-pro'),
							)
						),
						'write' => array(
							'id'    => 'lws_woorewards_rest_api_user_write',
							'title' => __("Users allowed to change user information", 'woorewards-pro'),
							'type'  => 'lacchecklist',
							'extra' => array(
								'predefined' => 'user',
								'tooltips' => __("The checked users can add points to other users.", 'woorewards-pro'),
							)
						),
					)
				),
			)
		);

		return $apiTab;
	}

	function getEmailsTab()
	{
		$emailsTab = $this->standardPages['wr_appearance']['tabs']['sty_mails'];
		$emailsTab['vertnav'] = true;
		$emailsTab['groups']['wr_achieved'] = array_merge($emailsTab['groups']['wr_achieved'], array(
			'icon' => 'lws-icon-trophy',
			'extra' => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/emails/achievement-email/')
		));
		$emailsTab['groups']['wr_available_unlockables'] = array_merge($emailsTab['groups']['wr_available_unlockables'], array(
			'icon' => 'lws-icon-questionnaire',
			'extra' => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/emails/reward-choice/')
		));
		$emailsTab['groups']['couponreminder'] = array_merge($emailsTab['groups']['couponreminder'], array(
			'icon' => 'lws-icon-coupon',
			'extra' => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/emails/reward-expiration/')
		));
		$emailsTab['groups']['pointsreminder'] = array_merge($emailsTab['groups']['pointsreminder'], array(
			'icon' => 'lws-icon-calendar',
			'extra' => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/emails/points-expiration/')
		));
		$emailsTab['groups']['wr_sponsored'] = array_merge($emailsTab['groups']['wr_sponsored'], array(
			'icon' => 'lws-icon-users-mm',
			'extra' => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/emails/sponsorship-email/')
		));
		return $emailsTab;
	}

	function getPopupTab()
	{
		$popupTab = array(
			'id'	=> 'reward_popup',
			'title'	=>  __("Popups", 'woorewards-pro'),
			'icon'	=> 'lws-icon-window-add',
			'groups' => array(
				array(
					'id'     => 'claim',
					'icon'	=> 'lws-icon-window-add',
					'title'  => __("Reward Popup", 'woorewards-pro'),
					'text'   => __("Defines the popup options when a user unlocks a reward.", 'woorewards-pro'),
					'fields' => array(
						array(
							'id'    => 'lws_wr_rewardclaim_popup_disable',
							'title' => __("Disable the reward popup", 'woorewards-pro'),
							'type'  => 'box',
							'extra' => array(
								'default' => '',
								'class' => 'lws_checkbox',
							)
						),
						'claim' => array(
							'id'    => 'lws_woorewards_reward_claim_page',
							'title' => __("Redirection page after a reward is unlocked", 'woorewards-pro'),
							'type'  => 'lacselect',
							'extra' => array(
								'predefined' => 'page',
								'tooltips' => __("When a customer clicks a reward redeem button, he will be redirected to that page.", 'woorewards-pro')
									. '<br/>' . __("If WooCommerce is activated, the default is the <b>Loyalty and Rewards</b> tab in the customer my-account frontend page. Otherwise, it is your home page", 'woorewards-pro')
							)
						),
						array(
							'id'    => 'lws_wr_rewardclaim_notice_with_rest',
							'title' => __("Show remaining available rewards after a reward is unlocked", 'woorewards-pro'),
							'type'  => 'box',
							'extra' => array(
								'default' => 'on',
								'class' => 'lws_checkbox',
								'tooltips' => __("When a customer clicks a reward redeem button, he will be redirected to a page with an unlock feedback.", 'woorewards-pro')
									. '<br/>' . __("That popup includes the rest of available rewards.", 'woorewards-pro')
							)
						),
						'popup' => array(
							'id' => 'lws_woorewards_lws_reward_claim',
							'type' => 'stygen',
							'extra' => array(
								'purpose' => 'filter',
								'template' => 'lws_reward_claim',
								'html' => false,
								'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/rewardclaim.css',
								'subids' => array(
									'lws_woorewards_wc_reward_claim_title' => "WooRewards - Reward Claim Popup - Title",
									'lws_woorewards_wc_reward_claim_header' => "WooRewards - Reward Claim Popup - Header",
									'lws_woorewards_wc_reward_claim_stitle' => "WooRewards - Reward Claim Popup - Subtitle",
								),
								'help' =>  __("This popup will show when customers unlock a new reward.", 'woorewards-pro') . "<br/>"
									. __("It can show only the reward unlocked or also the rewards that can still be unlocked .", 'woorewards-pro')
							)
						),
					)
				),
				array(
					'id'     => 'freeproduct',
					'icon'	=> 'lws-icon-window-add',
					'title'  => __("Free Product Popup", 'woorewards-pro'),
					'text'   => __("Defines the popup options when a customer uses a free product coupon with multiple choices.", 'woorewards-pro'),
					'fields' => array(
						'popup' => array(
							'id' => 'lws_woorewards_free_product_template',
							'type' => 'stygen',
							'extra' => array(
								'purpose' => 'filter',
								'template' => 'free_product_template',
								'html' => false,
								'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/freeproduct.css',
								'subids' => array(
									'lws_free_product_popup_title' => "WooRewards Free Product - title",
									'lws_free_product_popup_cancel' => "WooRewards Free Product - cancel button",
									'lws_free_product_popup_validate' => "WooRewards Free Product - validate button",
								),
								'help' =>  __("This popup will show when customers use a free product coupon.", 'woorewards-pro')
							)
						),
					)
				)
			)
		);
		return $popupTab;
	}

	/** @return pool name or false. */
	protected function guessCurrentPool($tabId)
	{
		$ref = false;
		$tab = isset($_REQUEST['tab']) ? trim($_REQUEST['tab']) : '';
		$tabPrefix = $tabId . '.wr_upool_';

		if (strpos($tab, $tabPrefix) === 0)
		{
			$ref = substr($tab, strlen($tabPrefix));
		}
		else if (defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && $_REQUEST['action'] == 'lws_adminpanel_editlist')
		{
			$editlist = isset($_REQUEST['id']) ? trim($_REQUEST['id']) : '';
			foreach (array('UnlockableList-', 'EventList-') as $prefix)
			{
				if (0 === strpos($editlist, $prefix))
				{
					$ref = intval(substr($editlist, strlen($prefix)));
					break;
				}
			}
		}

		$guess = false;
		if ($ref)
		{
			if ($id = max(0, intval($ref)))
				$guess = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('p' => $id, 'deep' => true))->last();

			if (!$guess)
				$guess = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array('name' => $ref, 'deep' => true))->last();
		}
		return $guess;
	}

	protected function getLoyaltyGroups($pool)
	{
		if (empty($pool))
		{
			return array('error' => array(
				'title' => __("Loading failure", 'woorewards-pro'),
				'text'  => __("Seems the points and rewards system does not exists. Try re-activate this plugin. If that problem persists, contact your administrator.", 'woorewards-pro')
			));
		}

		$settingsText = array(
			'cast' => 'p',
			array(
				__("General Settings are used to start or stop your points and rewards system, rename it or change some other basic options.", 'woorewards-pro'),
				__("If you're not sure how to use them, please refer to the dedicated documentation by clicking the book icon on the top right.", 'woorewards-pro'),
			),
			array('tag' => 'strong', __("Don't forget to start your points and rewards system when you've finished your settings.", 'woorewards-pro'))
		);

		$earningText = array(
			'cast' => 'p',
			__("Use this section to define what actions users or customers have to perform in order to earn points in this points and rewards system.", 'woorewards-pro'),
			array('tag' => 'strong', __("You can define as many actions as you want by clicking the 'Add' button.", 'woorewards-pro')),
		);

		$group = array(
			'earning'    => array(
				'id'      => 'wr_loyalty_earning',
				'class'   => 'half',
				'title'   => __("Points", 'woorewards-pro'),
				'image'   => LWS_WOOREWARDS_IMG . '/ls-earning.png',
				'color'   => '#38bebe',
				'text'    => $earningText,
				'extra'   => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/points/'),
				'editlist' => \lws_editlist(
					'EventList-' . $pool->getId(),
					\LWS\WOOREWARDS\Ui\Editlists\EventList::ROW_ID,
					new \LWS\WOOREWARDS\Ui\Editlists\EventList($pool),
					\LWS\Adminpanel\EditList::MDA
				)->setPageDisplay(false)->setCssClass('eventlist')->setRepeatHead(false),
			),
			'spending'   => $this->getPoolRewardsGroup($pool),
			'general'    => array(
				'id'     => 'wr_loyalty_general',
				'image'  => LWS_WOOREWARDS_IMG . '/ls-settings.png',
				'color'  => '#7958a5',
				'class'  => 'half',
				'title'  => __("General Settings", 'woorewards-pro'),
				'text'   => $settingsText,
				'extra'  => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/tutorials/points-and-rewards/'),
				'fields' => \apply_filters('lws_woorewards_admin_pool_general_settings', array(), $pool)
			),
			'expiration' => $this->getPoolPointExpirationGroup($pool),
			'pts_disp'   => $this->getPoolPointDisplayGroup($pool),
			'advanced'	 => $this->getPoolAdvancedGroup($pool),
		);

		return $group;
	}

	protected function getPoolAdvancedGroup($pool)
	{
		$asettingsText = array(
			'join' => '<br/><br/>',
			__("Advanced Settings are used to set special options to change the behavior of the points and rewards system. These options can have a serious impact on the points and rewards system.", 'woorewards-pro'),
			array(
				array('tag' => 'strong', __("Warning :", 'woorewards-pro')),
				__("Be sure of what you're doing before making modifications in this section.", 'woorewards-pro'),
			)
		);

		$group = array(
			'id'		=> 'wr_loyalty_asettings',
			'image'		=> LWS_WOOREWARDS_IMG . '/ls-asettings.png',
			'color'		=> '#6e9684',
			'class'		=> 'half',
			'title'		=> __("Advanced Settings", 'woorewards-pro'),
			'text'		=> $asettingsText,
			'extra'		=> array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/advanced-mechanisms/advanced-settings/'),
			'fields'	=> array(
				'stackid' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'stack',
					'title' => __("Point Reserve", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'id'       => 'lws_wr_pool_pointstack',
						'class'    => 'lws-wr-pool-pointstack',
						'value'    => $pool->getRawStackId(),
						'ajax'     => 'lws_woorewards_pointstack_list',
						'source'   => array(
							array('value' => '', 'label' => sprintf('<i>%s</i>', __("&lt;Create a new reserve&gt;", 'woorewards-pro'))),
						),
						'mode' => 'select',
						'tooltips' => sprintf(
							'<span class="pointstack_help single">%s</span><span class="pointstack_help shared hidden"><span class="text">%s</span> <span class="list">&nbsp;</span></span>',
							__("This System uses its own points reserve.", 'woorewards-pro'),
							__("This System shares its Points between :", 'woorewards-pro')
						)
					)
				),
				'restricted' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'roles',
					'title' => __("Allowed Roles", 'woorewards-pro'),
					'type'  => 'lacchecklist',
					'extra' => array(
						'value'    => $pool->getOption('roles'),
						'ajax'     => 'lws_adminpanel_get_roles',
						'tooltips' => __("If set, only users with at least one of the selected roles can enjoy that points and rewards system. By default, a loyalty system is available for everybody.", 'woorewards-pro')
					)
				),
				'denied' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'denied_roles',
					'title' => __("Denied Roles", 'woorewards-pro'),
					'type'  => 'lacchecklist',
					'extra' => array(
						'value'    => $pool->getOption('denied_roles'),
						'ajax'     => 'lws_adminpanel_get_roles',
						'tooltips' => __("If set, users with at least one of the selected roles won't have access to the Points and Rewards System.", 'woorewards-pro')
					)
				),
				'order' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'loading_order',
					'title' => __("Loading Order", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'value' => $pool->getOption('loading_order'),
						'help'  => __('Force a Points and Rewards System to be loaded/executed before another one. Greater the number is, sooner the Loyalty System is loaded.', 'woorewards-pro'),
					)
				),
			)
		);

		if ($pool->getOption('type') == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING)
		{
			$bestLabel = __("Unlock Best Level <b>Only</b>", 'woorewards-pro');
			$bestOptions = array(
				'off'  => array('value' => 'off', 'label' => _x("Off", "best_unlock settings", 'woorewards-pro')),
				'on'   => array('value' => 'on', 'label' => _x("On", "best_unlock settings", 'woorewards-pro')),
			);

			$group['fields']['confiscation'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'confiscation',
				'title' => __("Lose rewards with points expiration", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'checked' => $pool->getOption('confiscation'),
					'class' => 'lws_checkbox',
					'tooltips' => __("After a points loss due to points expiration, the customer will have to earn all the rewards again. Ignored if points expiration isn’t set.", 'woorewards-pro')
				)
			);

			$group['fields']['clamp_level'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'clamp_level',
				'title' => __("One level at a time", 'woorewards-pro'),
				'type'  => 'box',
				'extra' => array(
					'checked' => $pool->getOption('clamp_level'),
					'default' => false,
					'class' => 'lws_checkbox',
					'tooltips' => __("If checked, customers can't earn more points than the points needed to reach the next level in one time.", 'woorewards-pro')
				)
			);

			$group['fields']['best_unlock'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'best_unlock',
				'title' => $bestLabel,
				'type'  => 'lacselect',
				'extra' => array(
					'value'    => $pool->getOption('best_unlock'),
					'source'   => $bestOptions,
					'id'       => 'reward_best_unlock',
					'mode'     => 'select',
					'default'  => 'off',
					'tooltips' => \lws_array_to_html(array(
						'tag' => 'ul',
						array(
							__("Off :", 'woorewards-pro'),
							__("All levels will be unlocked, even if the user earns enough points in one time to unlock several levels.", 'woorewards-pro'),
						),
						array(
							__("On :", 'woorewards-pro'),
							__("Only the best level will be unlocked. If a user earns enough points to unlock several levels, only the highest will be unlocked", 'woorewards-pro'),
						),
					)),
				)
			);
		}



		return $group;
	}

	protected function getPoolPointExpirationGroup($pool)
	{
		$expirationText = array(
			'join' => '<br/><br/>',
			__("You have access to different points expiration methods. To get more information on those methods, don't hesitate to take a look at the dedicated documentation. Simply click the book Icon on the top right.", 'woorewards-pro'),
			array(
				array('tag' => 'strong', __("Warning :", 'woorewards-pro')),
				__("You should only use one expiration method in your points and rewards system", 'woorewards-pro'),
			)
		);

		return array(
			'id'		=> 'wr_loyalty_expiration',
			'image'		=> LWS_WOOREWARDS_IMG . '/ls-calendar.png',
			'color'		=> '#a4489a',
			'class'		=> 'half',
			'title'		=> __("Points Expiration", 'woorewards-pro'),
			'text'		=> $expirationText,
			'extra'		=> array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/advanced-mechanisms/points-expiration/'),
			'fields'	=> array(
				'lifetime' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'point_timeout',
					'title' => __("Points expiration for inactivity", 'woorewards-pro'),
					'type'  => 'duration',
					'extra' => array(
						'value' => $pool->getOption('point_timeout')->toString(),
						'help' => __("Defines if customers lose their points after an inactivity period", 'woorewards-pro')
					)
				),
				'transactional_expiry' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'transactional_expiry',
					'title' => __("Transactional Points expiration", 'woorewards-pro'),
					'type'  => 'woorewards_periodic_trigger',
					'extra' => array(
						'value' => $pool->getOption('transactional_expiry'),
						'help' => sprintf(
							__("Defines if customers lose unused points periodically. Please read the %s for settings explanation.", 'woorewards-pro'),
							"<a href='https://plugins.longwatchstudio.com/docs/woorewards-4/points-and-rewards-systems/#6-%E2%80%93-points-expiration' target='_blank'>" . __("documentation", 'woorewards-pro') . "</a>"
						),
					)
				)
			)
		);
	}

	protected function getPoolPointDisplayGroup($pool)
	{
		$currencyText = array(
			'join' => '<br/><br/>',
			__("You can change how points are displayed to customers. You can either set a text or an image.", 'woorewards-pro'),
			array(
				array('tag' => 'strong', __("Warning :", 'woorewards-pro')),
				__("If you use multiple languages, labels won't be translated with po/mo files. However, it's possible with WPML.", 'woorewards-pro'),
			),
		);

		return array(
			'id'       => 'wr_pts_disp',
			'image'		=> LWS_WOOREWARDS_IMG . '/ls-currency.png',
			'color'		=> '#a67c52',
			'class'		=> 'half',
			'title'    => __("Points Currency", 'woorewards-pro'),
			'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/advanced-mechanisms/points-currency/'),
			'text' 	   => $currencyText,
			'fields'   => array(
				'point_name' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'point_name_singular',
					'title' => __("Point display name", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'value' => $pool->getOption('point_name_singular'),
						'placeholder' => __("Point", 'woorewards-pro'), //\LWS_WooRewards::getPointSymbol(1),
						'tooltips' => __("Point unit shown to the user.", 'woorewards-pro'),
					)
				),
				'points_name' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'point_name_plural',
					'title' => __("Points display name (plural)", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'value' => $pool->getOption('point_name_plural'),
						'placeholder' => __("Points", 'woorewards-pro'), //\LWS_WooRewards::getPointSymbol(2),
						'tooltips' => __("(Optional) The singular form is used if plural is not set.", 'woorewards-pro'),
					)
				),
				'point_sym' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'symbol',
					'title' => __("Point symbol", 'woorewards-pro'),
					'type'  => 'media',
					'extra' => array(
						'value' => $pool->getOption('symbol'),
						'tooltips' => __("If you set an image, it will replace the above labels.", 'woorewards-pro'),
					)
				),
				'point_format' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'point_format',
					'title' => __("Point name position", 'woorewards-pro'),
					'type'  => 'lacselect',
					'extra' => array(
						'value' => $pool->getOption('point_format'),
						'mode' => 'select',
						'source' => array(
							array('value' => '%1$s %2$s', 'label' => _x("Right", 'Point name position', 'woorewards-pro')),
							array('value' => '%2$s %1$s', 'label' => _x("Left", 'Point name position', 'woorewards-pro')),
						),
					)
				),
				'thousand_sep' => array(
					'id'    => self::POOL_OPTION_PREFIX . 'thousand_sep',
					'title' => __("Thousand Separator", 'woorewards-pro'),
					'type'  => 'text',
					'extra' => array(
						'value' => $pool->getOption('thousand_sep'),
						'tooltips' => __("(Optional) The thousand separator when displaying big numbers.", 'woorewards-pro'),
					)
				),
			),
		);
	}

	protected function getPoolRewardsGroup($pool)
	{
		$group = array(
			'id'     => 'lws_wr_spending_system',
			'class'  => 'half',
			'image'  => LWS_WOOREWARDS_IMG . '/ls-gift.png',
			'color'  => '#526981',
			'title'  => '',
			'text'   => '',
			'extra'  => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards-4/rewards/'),
			'fields' => array(),
		);

		$rewards = array(
			'id'    => 'rewards',
			'type'  => 'editlist',
			'extra' => array(
				'editlist' => \lws_editlist(
					'UnlockableList-' . $pool->getId(),
					\LWS\WOOREWARDS\Ui\Editlists\UnlockableList::ROW_ID,
					new \LWS\WOOREWARDS\Ui\Editlists\UnlockableList($pool),
					\LWS\Adminpanel\EditList::MDA
				)->setPageDisplay(false)->setGroupBy($this->getGroupByLevelSettings($pool))->setCssClass('unlockablelist')->setRepeatHead(false),
			),
		);

		if (\LWS\WOOREWARDS\Core\Pool::T_LEVELLING == $pool->getOption('type'))
		{
			$group['title'] = __('Levels and Rewards', 'woorewards-pro');
			$group['text'] = array(
				'join' => '<br/>',
				array(
					__("In a leveling system, you must ", 'woorewards-pro'),
					array('tag' => 'strong', __("create levels first", 'woorewards-pro')),
				),
				array(
					__("After creating a level, you can add one or more rewards to the level.", 'woorewards-pro'),
					__("If you're not sure how to set up levels and rewards, please refer to the dedicated documentation by clicking the book icon on the top right.", 'woorewards-pro'),
				),
				array(
					array('tag' => 'strong', __("You can define as many levels and rewards as you want", 'woorewards-pro')),
				),
			);

			$group['fields']['rewards'] = $rewards;
		}
		else // T_STANDARD
		{
			$group['title'] = __('Rewards', 'woorewards-pro');
			$group['text'] = array(
				__("In a standard system, you have to choose how customers will spend their points.", 'woorewards-pro'),
				array('tag' => 'strong', __("They can either spend them directly on the cart to get an immediate discount or you can setup various rewards they buy with their points.", 'woorewards-pro')),
				__("If you're not sure how to set up rewards, please refer to the dedicated documentation by clicking the book icon on the top right.", 'woorewards-pro'),
			);

			$group['fields']['mode'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_mode',
				'type'  => 'box',
				'title' => __("Rewards Type", 'woorewards-pro'),
				'extra' => array(
					'id'      => 'direct_reward_mode',
					'class'   => 'lws_switch',
					'value'   => $pool->getOption('direct_reward_mode'),
					'data'    => array(
						'left' => __("Rewards", 'woorewards-pro'),
						'right' => __("Points on Cart", 'woorewards-pro'),
						'colorleft' => '#425981',
						'colorright' => '#5279b1',
					),
				)
			);

			$group['fields']['rate'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_point_rate',
				'type'  => 'text',
				'title' => sprintf(__("Point Value in %s", 'woorewards-pro'), \LWS_WooRewards::isWC() ? \get_woocommerce_currency_symbol() : '?'),
				'extra' => array(
					'value'   => $pool->getOption('direct_reward_point_rate'),
					'help' => __("Each point spent on the cart will decrease the order total of that value", 'woorewards-pro')
				),
				'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
			);

			$group['fields']['max_points'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_max_points_on_cart',
				'title' => __("Max points usage", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'value'       => $pool->getOption('direct_reward_max_points_on_cart'),
					'placeholder' => '',
					'help'        => __("The maximum amount of points that can be used on a single cart", 'woorewards-pro'),
				),
				'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
			);

			$group['fields']['max_percent'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_max_percent_of_cart',
				'title' => __("Max percentage of cart", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'value'       => $pool->getOption('direct_reward_max_percent_of_cart'),
					'placeholder' => '%',
					'help'        => __("The maximum amount a customer can spend in a single cart will be limited to the percentage of the payable total. Leave blank for no limit.", 'woorewards-pro'),
				),
				'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
			);

			$group['fields']['min_grandtotal'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_total_floor',
				'title' => __("Lower Cart Limit", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'value'       => $pool->getOption('direct_reward_total_floor'),
					'placeholder' => \LWS_WooRewards::isWC() ? \get_woocommerce_currency_symbol() : '',
					'help'        => __("If set, customers can't use their points to discount the cart below that limit. Leave empty for no limit.", 'woorewards-pro'),
				),
				'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
			);

			$group['fields']['min_subtotal'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'direct_reward_min_subtotal',
				'title' => __("Minimum Cart Amount", 'woorewards-pro'),
				'type'  => 'text',
				'extra' => array(
					'value'       => $pool->getOption('direct_reward_min_subtotal'),
					'placeholder' => \LWS_WooRewards::isWC() ? \get_woocommerce_currency_symbol() : '',
					'help'        => __("Set a minimum cart amount under which customers can't use their points on the cart. Once the cart total is above that value, customers will be able to use their points. Leave empty for no minimum.", 'woorewards-pro'),
				),
				'require' => array('selector' => '#direct_reward_mode', 'value' => 'on'),
			);

			if ($pool->getOption('type') != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING)
			{
				$bestLabel = __("Automatic Rewards Redeem", 'woorewards-pro');

				$bestOptions = array(
					'off'  => array('value' => 'off', 'label' => _x("Off", "best_unlock settings", 'woorewards-pro')),
					'on'   => array('value' => 'on', 'label' => _x("Unlock best reward only", "best_unlock settings", 'woorewards-pro')),
					'loop' => array('value' => 'and_loop', 'label' => _x("Unlock best reward first", "best_unlock settings", 'woorewards-pro')),
					'raz'  => array('value' => 'use_all_points', 'label' => _x("Unlock best reward and reset points", "best_unlock settings", 'woorewards-pro')),
				);
				$group['fields']['best_unlock'] = array(
					'id'    => self::POOL_OPTION_PREFIX . 'best_unlock',
					'title' => $bestLabel,
					'type'  => 'lacselect',
					'extra' => array(
						'value'    => $pool->getOption('best_unlock'),
						'source'   => $bestOptions,
						'id'       => 'reward_best_unlock',
						'mode'     => 'select',
						'default'  => 'off',
						'tooltips' => \lws_array_to_html(array(
							'tag' => 'ul',
							array(
								__("Off :", 'woorewards-pro'),
								__("The customer has to manually redeem the rewards. A mail is sent each time he earns enough points for at least one of them.", 'woorewards-pro'),
							),
							array(
								__("Best only :", 'woorewards-pro'),
								__("Only the most expensive reward or the highest level the user can afford will be unlocked.", 'woorewards-pro'),
							),
							array(
								__("Best first :", 'woorewards-pro'),
								__("Rewards are unlocked as long as customer can afford it, starting by the most expensive reward (The same reward can be unlocked several time).", 'woorewards-pro'),
							),
							array(
								__("Best and Reset :", 'woorewards-pro'),
								__("Same as 'Best only' but consume all customer's points.", 'woorewards-pro'),
							),
						)),
					),
					'require' => array('selector' => '#direct_reward_mode', 'value' => ''),
				);

			}

			$rewards['require'] = array('selector' => '#direct_reward_mode', 'value' => '');
			$group['fields']['rewards'] = $rewards;
		}

		return $group;
	}

	protected function getGroupByLevelSettings($pool)
	{
		$groupBy = array(
			'key'       => 'cost',
			'activated' => ($pool->getOption('type') == \LWS\WOOREWARDS\Core\Pool::T_LEVELLING),
			'add'       => __("Add level", 'woorewards-pro'),
		);
		$labels = array(
			'group_value' => __("Untitled", 'woorewards-pro'),
			'group_title' => _x("Level Title", "Level Threshold Title edit", 'woorewards-pro'),
			'group_point' => _x("Points Threshold", "edit", 'woorewards-pro'),
			'form_title'  => \esc_attr(__("Title is required.", 'woorewards-pro')),
			'form_point'  => \esc_attr(__("Cost must be a number greater than zero.", 'woorewards-pro')),
			'title_title' => _x("Level Title", "Level Threshold Title edit", 'woorewards-pro'),
			'title_point' => _x("Points Threshold", "edit", 'woorewards-pro'),
		);
		$groupBy['head'] = <<<EOT
<div class='lws-wr-levelling-node-head'>
	<div class='lws-wr-levelling-node-item grouped_title'>
		<div class='lws-wr-levelling-node-value'><span data-name='grouped_title'>{$labels['group_value']}</span></div>
		<div class='lws-wr-levelling-node-label'>{$labels['group_title']}</div>
	</div>
	<div class='lws-wr-levelling-node-item cost'>
		<div class='lws-wr-levelling-node-value'><span data-name='cost'>1</span></div>
		<div class='lws-wr-levelling-node-label'>{$labels['group_point']}</div>
	</div>
</div>
EOT;
		$groupBy['form'] = <<<EOT
<div class='lws-wr-levelling-node-form'>
	<div class='lws-wr-levelling-node-item grouped_title'>
		<div class='lws-wr-levelling-node-value'><input type='text' class='lws-input lws-wr-title-input' name='grouped_title' data-pattern='[^\\s]+' data-pattern-title='{$labels['form_title']}'/></div>
		<div class='lws-wr-levelling-node-label'>{$labels['title_title']}</div>
	</div>
	<div class='lws-wr-levelling-node-item cost'>
		<div class='lws-wr-levelling-node-value'><input name='cost' class='lws-input lws-wr-cost-input' type='text' data-pattern='^\\d*[1-9]\\d*$' data-pattern-title='{$labels['form_point']}'/></div>
		<div class='lws-wr-levelling-node-label'>{$labels['title_point']}</div>
	</div>
</div>
EOT;
		return $groupBy;
	}

	function poolGeneralSettings($fields, \LWS\WOOREWARDS\Core\Pool $pool)
	{
		if (empty($pool->getId()))
		{
			$fields['type'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'type',
				'type'  => 'select',
				'title' => __("Behavior", 'woorewards-pro'),
				'extra' => array(
					'id'      => 'lws-wr-pool-option-type',
					'value'   => $pool->getOption('type'),
					'notnull' => true,
					'options' => array(
						'standard'  => _x("Standard", "Pool Type/behavior", 'woorewards-pro'),
						'levelling' => _x("Levelling", "Pool Type/behavior", 'woorewards-pro')
					),
					'help' => '<ul><li>' . __("<i>Standard behavior</i>: customers can spend points to buy rewards", 'woorewards-pro')
						. '</li><li>' . __("<i>Levelling behavior</i>: rewards are automatically granted to customers since they have enough points (points are never spent).", 'woorewards-pro')
						. '</li></ul>'
				)
			);
		}
		$happening = $pool->getOption('happening') ? true : false;
		$fields['lifestyle'] = 	array(
			'id'    => self::POOL_OPTION_PREFIX . 'happening',
			'type'  => 'box',
			'title' => __("System Type", 'woorewards-pro'),
			'extra' => array(
				'id'      => 'lws_woorewards_system_type',
				'class'   => 'lws_switch',
				'checked' => $happening,
				'data'    => array(
					'left' => __("Permanent", 'woorewards-pro'),
					'right' => __("Event", 'woorewards-pro'),
					'colorleft' => '#7958a5',
					'colorright' => '#5279b1',
				),
			)
		);


		if ($pool->isDeletable())
		{
			$date = $pool->getOption('period_start');
			$fields['period_start'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'period_start',
				'type'  => 'input',
				'title' => __("Start Date", 'woorewards-pro'),
				'extra' => array(
					'id'      => 'lws-wr-pool-option-period-begin',
					'type'  => 'date',
					'value' => empty($date) ? '' : $date->format('Y-m-d'),
					'help'  => __("Before that date, the points and rewards system is disabled but customer can see it.", 'woorewards-pro')
				),
				'require' => array('selector' => '#lws_woorewards_system_type', 'value' => 'on'),
			);

			if ($pool->getOption('type') != \LWS\WOOREWARDS\Core\Pool::T_LEVELLING)
			{
				$date = $pool->getOption('period_mid');
				$fields['period_mid'] = array(
					'id'    => self::POOL_OPTION_PREFIX . 'period_mid',
					'type'  => 'input',
					'title' => __("Point earning end", 'woorewards-pro'),
					'extra' => array(
						'id'      => 'lws-wr-pool-option-period-mid',
						'type'  => 'date',
						'value' => empty($date) ? '' : $date->format('Y-m-d'),
						'help'  => __("After that date, customers can no longer earn points. But they still can spend them for rewards.", 'woorewards-pro')
					),
					'require' => array('selector' => '#lws_woorewards_system_type', 'value' => 'on'),
				);
			}

			$date = $pool->getOption('period_end');
			$fields['period_end'] = array(
				'id'    => self::POOL_OPTION_PREFIX . 'period_end',
				'type'  => 'input',
				'title' => __("End Date", 'woorewards-pro'),
				'extra' => array(
					'id'      => 'lws-wr-pool-option-period-end',
					'type'  => 'date',
					'value' => empty($date) ? '' : $date->format('Y-m-d'),
					'help'  => __("After that date, the points and rewards system will be disabled but customer can see it. Customers keep their remaining points but cannot use them anymore.", 'woorewards-pro')
				),
				'require' => array('selector' => '#lws_woorewards_system_type', 'value' => 'on'),
			);
		}

		return $fields;
	}

	function userspointsFilters($filters)
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsrangefilter.php';
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsactivityfilter.php';

		$filters = array_merge(array(
			'range' => new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsRangeFilter('range'),
			'activity' => new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsActivityFilter('activity')
		), $filters);

		if (!empty(\get_option('lws_woorewards_manage_badge_enable', 'on')))
		{
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsbadgefilter.php';
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsbadgeassignbulkaction.php';
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsbadgeremovebulkaction.php';

			$filters = array_merge(array(
				'badge' => new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsBadgeFilter('badge'),
			), $filters);

			$filters['badge_add'] = new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsBadgeAssignBulkAction('badge_add');
			$filters['badge_rem'] = new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsBadgeRemoveBulkAction('badge_rem');
		}

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsbulkaction.php';
		$filters['points_add'] = new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsBulkAction('points_add');

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsunlockablesba.php';
		$filters['u_redeem'] = new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsUnlockablesBA('u_redeem');
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/userspointsconfiscationba.php';
		$filters['u_revoke'] = new \LWS\WOOREWARDS\PRO\Ui\Editlists\UsersPointsConfiscationBA('u_revoke');

		return $filters;
	}

	function getSystemPage()
	{
		if( (LWS_WOOREWARDS_PAGE . '.system') != $this->getCurrentPage() )
			return $this->standardPages['wr_system'];

		$system = $this->standardPages['wr_system'];

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/migration.php';
		\LWS\WOOREWARDS\PRO\Ui\AdminScreens\Migration::mergeGroups($system['tabs']['data_management']['groups']);

		if (isset($system['tabs']['data_management']['groups']['delete']))
		{
			// move at last pos
			$delete = $system['tabs']['data_management']['groups']['delete'];
			unset($system['tabs']['data_management']['groups']['delete']);
			$system['tabs']['data_management']['groups']['delete'] = $delete;
		}
		return $system;
	}

	// $support = array with 'select' and 'texts'
	function addSupportText($support, $slug)
	{
		if ('woorewards' != $slug)
			return $support;

		$support['texts']['howto'] = <<<EOT
<h2>Setup Help</h2>
<p>In order to help you, we need detailed information about what you're trying to achieve.<br/>
Please provide the following information in your request</p>
<ul>
<li>How will your customers earn points ?</li>
<li>What are the rewards you want to offer ?</li>
<li>Do you plan on using sponsorship ?</li>
<li>Do you plan on using leveling points and rewards systems ?</li>
<li>How do you plan to display loyalty information to your customers ?</li>
</ul>
<h2>Your request</h2>
<p>Please provide information as detailed as possible.</p>
EOT;
		return $support;
	}

	function deleteAllData()
	{
		error_log("[MyRewards-Pro] Delete everything");

		\delete_option('lws_woorewards_pro_version');

		// delete badge posts
		$badges = \get_posts(array(
			'numberposts' => -1,
			'post_type' => 'lws_badge',
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future', 'trash', 'auto-draft', 'inherit'),
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'cache_results'  => false
		));
		foreach ($badges as $badge)
		{
			\wp_delete_post($badge->Id, true);
		}

		global $wpdb;
		$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->base_prefix}lws_webhooks_events`");

		// achievememts
		foreach (array('lws-wre-achievement') as $post_type)
		{
			foreach ($wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='{$post_type}'") as $post_id)
				\wp_delete_post($post_id, true);
		}

		// user meta
		$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'lws_woorewards_%' OR meta_key LIKE 'lws_wre_event_%'");
		$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('lws-loyalty-done-steps','woorewards_special_title','woorewards_special_title_position')");

		// post meta
		$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('lws_woorewards_event_points_sponsorship','woorewards_freeproduct', 'woorewards_permanent','lws_woorewards_auto_apply','woorewards_reminder_done')");

		// mails
		foreach (array('wr_new_reward', 'wr_available_unlockables', 'wr_sponsored', 'couponreminder', 'pointsreminder') as $template)
		{
			\delete_option('lws_mail_subject_' . $template);
			\delete_option('lws_mail_preheader_' . $template);
			\delete_option('lws_mail_title_' . $template);
			\delete_option('lws_mail_header_' . $template);
			\delete_option('lws_mail_template_' . $template);
			\delete_option('lws_mail_bcc_admin_' . $template);
		}

		// clean options
		foreach (array(
			'lws_wooreward_max_sponsorship_count',
			'lws_wre_product_points_preview',
			'lws_wre_cart_points_preview',
		) as $opt)
		{
			\delete_option($opt);
		}
	}
}
