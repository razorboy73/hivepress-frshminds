<?php
namespace LWS\WOOREWARDS\PRO\Ui\AdminScreens;
// don't call the file directly
if (!defined('ABSPATH')) exit();

class Sponsorship
{
	static function getTab()
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/editlists/sponsoredreward.php';
		$tab = array(
			'id'	=> 'sp_settings',
			'title'	=>  __("Sponsorship & Referral", 'woorewards-pro'),
			'icon'	=> 'lws-icon-handshake',
			'vertnav' => true,
			'groups' => array(
				'sponsorship' => array(
					'id' 	=> 'sponsorship',
					'icon'	=> 'lws-icon-handshake',
					'class'	=> 'half',
					'title'	=> __("Sponsorship & Referral Features", 'woorewards-pro'),
					'text' 	=> __("Here, you'll find the different tools customers can user to sponsor/refer their friends and the reward given to sponsored users.", 'woorewards-pro') .
						__("To reward the sponsors, either use the dedicated wizard or select an appropriate earning method inside a points and rewards system.", 'woorewards-pro'),
					'extra' => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/sponsorship/'),
					'fields' => array(
						'enable' => array(
							'id'    => 'lws_woorewards_event_enabled_sponsorship',
							'title' => __("Enable Sponsorship", 'woorewards-pro'),
							'type'  => 'box',
							'extra' => array(
								'class' => 'lws_checkbox',
								'default' => 'on'
							)
						),
						'enableReferral' => array(
							'id'    => 'lws_woorewards_referral_back_give_sponsorship',
							'type'  => 'box',
							'title' => __("Allow sponsorship via referral link", 'woorewards-pro'),
							'extra' => array(
								'default' => 'on',
								'class' => 'lws_checkbox',
								'help' => __("When a visitor comes from a referral link and registers, he will be sponsored by the user that posted the link.", 'woorewards-pro')
							)
						),
						'enableSocial' => array(
							'id'    => 'lws_woorewards_socialshare_back_give_sponsorship',
							'type'  => 'box',
							'title' => __("Allow sponsorship via social share", 'woorewards-pro'),
							'extra' => array(
								'default' => '',
								'class' => 'lws_checkbox',
								'help' => __("When a visitor comes from a link posted on a social network and registers, he will be sponsored by the user that shared the post.", 'woorewards-pro')
							)
						),
						'max'    => array(
							'id' => 'lws_wooreward_max_sponsorship_count',
							'title' => __("Max sponsorships per customer", 'woorewards-pro'),
							'type' => 'text',
							'extra' => array(
								'pattern' => '\d+',
								'default' => '0',
								'help' => __("Set the maximum sponsorships allowed for users. No restriction on empty value or zero (0).", 'woorewards-pro')
							)
						),
					)
				),
				'sp_reward' => array(
					'id' => 'sponsored',
					'icon'	=> 'lws-icon-present',
					'class'	=> 'half',
					'title' => __("Sponsored Reward", 'woorewards-pro'),
					'text' => __("Define the reward granted to the sponsored customers. <strong>Works only for emailing sponsorship.</strong>", 'woorewards-pro')
						. sprintf(__("If you want to grant a reward for customers sponsored with other methods, please read the %s.", 'woorewards-pro'), '<a href="https://plugins.longwatchstudio.com/docs/woorewards/sponsorship/#reward" target="_blank">' . __("documentation", 'woorewards-pro') . '</a>'),
					'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/sponsorship/#reward'),
					'editlist' => \lws_editlist(
						'Sponsored',
						\LWS\WOOREWARDS\PRO\Ui\Editlists\SponsoredReward::ROW_ID,
						new \LWS\WOOREWARDS\PRO\Ui\Editlists\SponsoredReward(),
						\LWS\Adminpanel\EditList::MDA
					)->setPageDisplay(false)->setCssClass('sponsoredreward')->setRepeatHead(false),
					'function' => function ()
					{
						\wp_enqueue_script('lws-wre-pro-sponsoredreward', LWS_WOOREWARDS_PRO_JS . '/sponsoredreward.js', array('lws-adminpanel-editlist'), LWS_WOOREWARDS_PRO_VERSION, true);
						\wp_enqueue_style('lws-wre-pro-sponsoredreward', LWS_WOOREWARDS_PRO_CSS . '/sponsoredreward.min.css', array('lws-admin-controls'), LWS_WOOREWARDS_PRO_VERSION);
					}
				),
				'sp_mail_widget' => array(
					'id' => 'sponsor_widget_style',
					'icon'	=> 'lws-icon-users-mm',
					'title' => __("Sponsorship Widget", 'woorewards-pro'),
					'text' => __("In this Widget, customers can sponsor their friends.", 'woorewards-pro'),
					'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/sponsorship/#spwidget'),
					'fields' => array(
						'unconnected'    => array(
							'id' => 'lws_wooreward_sponsorship_nouser',
							'title' => __("Text displayed if user not connected", 'woorewards-pro'),
							'type' => 'text',
							'extra' => array(
								'size' => '50',
								'wpml' => "WooRewards - Sponsor Widget - Need log in",
								'placeholder' => __("Please log in if you want to sponsor your friends", 'woorewards-pro')
							)
						),
						'success'    => array(
							'id' => 'lws_wooreward_sponsorship_success',
							'title' => __("Text displayed on success", 'woorewards-pro'),
							'type' => 'text',
							'extra' => array(
								'size' => '50',
								'wpml' => "WooRewards - Sponsor Widget - Success",
								'placeholder' => __("A mail has been sent to your friend about us.", 'woorewards-pro')
							)
						),
						'allow' => array(
							'id'    => 'lws_woorewards_sponsorship_allow_unlogged',
							'title' => __("Allow unlogged users to sponsor", 'woorewards-pro'),
							'type'  => 'box',
							'extra' => array(
								'default' => false,
								'class' => 'lws_checkbox',
								'tooltips' => __("If you enable the following feature, unlogged users will have to enter their email address in addition to sponsored addresses.", 'woorewards-pro')
							)
						),
						'redirect' => array(
							'id'    => 'lws_woorewards_sponsorhip_user_notfound',
							'title' => __("Redirection if user not found", 'woorewards-pro'),
							'type'  => 'lacselect',
							'extra' => array(
								'predefined' => 'page',
								'tooltips' => __("If an unlogged user tries to sponsor some friends, the system will try to find the appropriate user.", 'woorewards-pro')
									. '<br/>' . __("If no user is found, the user will be redirected to the page specified here, inviting them to register. If nothing is specified, they will stay on the same page", 'woorewards-pro')
							)
						),
						array(
							'id' => 'lws_woorewards_sponsor_template',
							'type' => 'stygen',
							'extra' => array(
								'purpose' => 'filter',
								'template' => 'wr_sponsorship',
								'html' => false,
								'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/sponsor.css',
								'subids' => array(
									'lws_woorewards_sponsor_widget_title' => "WooRewards - Sponsor Widget - Title",
									'lws_woorewards_sponsor_widget_submit' => "WooRewards - Sponsor Widget - Button",
									'lws_woorewards_sponsor_widget_placeholder' => "WooRewards - Sponsor Widget - Placeholder",
								)
							)
						)
					)
				),
				'sp_referral_widget' => array(
					'id' => 'referral',
					'icon'	=> 'lws-icon-url',
					'title' => __("Referral Link", 'woorewards-pro'),
					'text' => __("In this Widget, customers get a referral link they can share.", 'woorewards-pro'),
					'extra'    => array('doclink' => 'https://plugins.longwatchstudio.com/docs/woorewards/sponsorship/#splink'),
					'fields' => array(
						'display' => array(
							'id'    => 'lws_woorewards_sponsorship_link_display',
							'title' => __("Default Display", 'woorewards-pro'),
							'type'  => 'lacselect',
							'extra' => array(
								'mode' => 'select',
								'source' => array(
									array('value' => 'link',	'label' => __('Url Link', 'woorewards-pro')),
									array('value' => 'qrcode',	'label' => __('QR Code', 'woorewards-pro')),
									array('value' => 'both',	'label' => __('Both', 'woorewards-pro')),
								),
							)
						),
						'page' => array(
							'id'    => 'lws_woorewards_sponsorship_link_page',
							'title' => __("Destination Page", 'woorewards-pro'),
							'type'  => 'lacselect',
							'extra' => array(
								'help' => __("Select the default destination of the referral link. If left empty, it will redirect to the same page it's placed", 'woorewards-pro'),
								'predefined' => 'page',
							)
						),
						'tinify' => array(
							'id'    => 'lws_woorewards_sponsorship_tinify_enabled',
							'title' => __("Try to shorten the referral URL", 'woorewards-pro'),
							'type'  => 'box',
							'extra' => array(
								'help' => __('Disable that feature if you encounter plugin conflicts or redirection problems. Disable that feature makes bigger and less readable QR codes.', 'woorewards-pro'),
								'class' => 'lws_checkbox',
								'default' => '',
								'id' => 'lws_woorewards_sponsorship_tinify_enabled',
							)
						),
						'tiny' => array(
							'id'    => 'lws_woorewards_sponsorship_short_url',
							'title' => __("Alternative Short Site URL", 'woorewards-pro'),
							'type'  => 'text',
							'extra' => array(
								'help' => __('To make the QR-Code as simple as possible, you can specify a shorter version of your site URL here that will be used as base for the image generation.', 'woorewards-pro'),
								'placeholder' => \site_url(),
							),
							'require' => array('selector' => '#lws_woorewards_sponsorship_tinify_enabled', 'value' => 'on'),
						),
						array(
							'id' => 'lws_woorewards_referral_template',
							'type' => 'stygen',
							'extra' => array(
								'purpose' => 'filter',
								'template' => 'wr_referral',
								'html' => false,
								'css' => LWS_WOOREWARDS_PRO_CSS . '/templates/referral.css',
								'subids' => array(
									'lws_woorewards_referral_widget_message' => "WooRewards - Referral Widget - Header",
								)
							)
						)
					)
				)
			)
		);
		return $tab;
	}
}
