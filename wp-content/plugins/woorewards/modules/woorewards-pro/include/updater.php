<?php
namespace LWS\WOOREWARDS\PRO;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** satic class to manage activation and version updates. */
class Updater
{
	/** @return array[ version => changelog ] */
	function getNotices()
	{
		$notes = array();
/*
		$notes['3.3'] = <<<EOT
<b>MyRewards Pro 3.3</b><br/>
<p>This updates brings a lot of new features to MyRewards.
A whole new section is available to create badges and achievements.
Social medias make their apparition as a new way to earn points.
We've also made significant changes to make the administration panel more mobile friendly.</p>
<ul>
	<li><b>Badges :</b> Create badges that your customers can win</li>
	<li><b>New way to earn points :</b> Get one or more badges</li>
	<li><b>Social Medias :</b> Earn points when sharing pages or products on social medias</li>
	<li><b>Social Medias :</b> Earn points when people visit shared links</li>
	<li><b>Social Medias :</b> Widget to show share links for facebook, twitter, linkedin, pinterest</li>
	<li><b>My Account :</b> Totally reworked my account "Loyalty and Reward" tab</li>
	<li><b>New email :</b> New email to inform customers about points expiration</li>
	<li><b>LWS Admin Panel :</b> Reworked to be more responsive.</li>
</ul>
EOT;

		$notes['3.4'] = <<<EOT
			<b>MyRewards Pro 3.4</b><br/>
			<p>This update brings achievements and roles management to MyRewards.
			A new entry point has been created for achievements.
			We've also made significant changes to add some QOL features</p>
			<b>New features :</b><br/>
			<ul>
				<li>Achievements system : Create achievements and award badges for completion</li>
				<li>Achievements 'My Account' Tab</li>
				<li>Loyalty and Rewards 'My Account' Tab : Possibility to hide customer's history</li>
				<li>Loyalty and Rewards 'My Account' Tab : Possibility to hide customer's history</li>
				<li>Restrict loyalty system by user role</li>
				<li>New reward : assign role to user</li>
				<li>New widget : Display achievements and progress</li>
				<li>New widget : Show methods to earn points</li>
				<li>WPML Compatibility</li>
				<li>Social Share widget/shortcode : Possibility to specify the shared url</li>
				<li>Referral widget/shortcode : Possibility to specify the shared url</li>
				<li>Unlock reward popup has been fully reworked and can now be customized</li>
				<li>Preview points on cart and product have been reworked to show points for different loyalty systems</li>
				<liEarning method 'Product Review': Product purchased requirement becomes optional></li>
				<li>Rewards widget : Add new display type (grid) to display rewards horizontally</li>
			</ul>
EOT;

		$notes['3.11'] = <<<EOT
			<b>MyRewards Pro 3.11</b><br/>
			<p>MyRewards become easier to use:</p>
			<ul>
				<li>Referral is now merged to Sponsorship</li>
			</ul>
EOT;
		$notes['3.12'] = <<<EOT
			<b>MyRewards Pro 3.12</b><br/>
			<p>New features and improvements:</p>
			<ul>
				<li>New method to earn points : publish a content (posts, pages or custom types)</li>
				<li>WC Order Email : Possibility to display a message in woocommerce emails</li>
				<li>New reward : Give points to another loyalty sytem</li>
				<li>New possibilities for unlocking rewards</li>
				<li>Bugfix : Use coupon on cart page</li>
			</ul>
EOT;
*/
		return $notes;
	}

	static function checkUpdate()
	{
		$reload = false;
		$wpInstalling = \wp_installing();
		\wp_installing(true); // should force no cache

		if( defined('LWS_WOOREWARDS_ACTIVATED') && LWS_WOOREWARDS_ACTIVATED && version_compare(($from = get_option('lws_woorewards_pro_version', '0')), ($to = LWS_WOOREWARDS_PRO_VERSION), '<') )
		{
			\wp_suspend_cache_invalidation(false);
			$me = new self();
			$reload = $me->update($from, $to);
			$me->notice($from, $to);
		}

		\wp_installing($wpInstalling);

		if( $reload )
		{
			// be sure to reload pools after update
			\wp_redirect($_SERVER['REQUEST_URI']);
		}
	}

	function notice($fromVersion, $toVersion)
	{
		if( version_compare($fromVersion, '1.0', '>=') )
		{
			$notices = $this->getNotices();
			$text = '';
			foreach($notices as $version => $changelog)
			{
				if( version_compare($fromVersion, $version, '<') && version_compare($version, $toVersion, '<=') ) // from < v <= new
					$text .= "<p>{$changelog}</p>";
			}
			if( !empty($text) )
				\lws_admin_add_notice('woorewards-pro'.'-changelog-'.$toVersion, $text, array('level'=>'info', 'forgettable'=>true, 'dismissible'=>true));
		}
	}

	/** Update
	 * @param $fromVersion previously registered version.
	 * @param $toVersion actual version. */
	function update($fromVersion, $toVersion)
	{
		$reload = false;
		$this->from = $fromVersion;
		$this->to = $toVersion;

		$this->createUserBadgeTable();
		$this->createWebHooksTable();

		if( \version_compare($fromVersion, '3.0.0', '<') )
		{
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/registration.php';

			if( $this->copyStandardV2Rewards() )
				$this->upgradeStandardPool();

			if( !(defined('LWS_WIZARD_SUMMONER') && LWS_WIZARD_SUMMONER) )
				$this->addLevelingPool();
			$this->copyLevellingSettings();
			$this->copyLevellingV2Rewards();
			$this->copyLevellingPoints();

			$this->copySponsoredReward();
			$this->addSponsorPool();

			$this->refactorFreeProductCoupons();

			// stygen option css class names become more explicit
			$this->cssRenamed('lws_woorewards_product_display_stygen_ppp', '-ppp-', 'lws_wre_product_points_preview', '-wre-productpointspreview-');
			$this->cssRenamed('lws_woorewards_cart_display_stygen_cpp', '-cpp-', 'lws_wre_cart_points_preview', '-wre-cartpointspreview-');
			$this->cssRenamed('lws_woorewards_widget_stygen_rpw', '-rpw-', 'lws_woorewards_displaypoints_template', '-displaypoints-');

			$reload = true;
		}

		if( \version_compare($fromVersion, '3.4.0', '<') )
		{
			if( !empty($css = trim(\get_option('lws_woorewards_rewards_template', ''))) && (false === \get_option('lws_woorewards_rewards_use_grid', false)) )
			{
				// already saved, but is it customed?
				$oldDftValues = array(
					'Lmx3c3Nfc2VsZWN0YWJsZS5sd3MtbWFpbi1jb250ZW5ldXJ7d2lkdGg6NjAwcHg7YmFja2dyb3VuZC1jb2xvcjojZmZmO2JvcmRlci1jb2xvcjojZWVlO2JvcmRlci1zdHlsZTpzb2xpZDtib3JkZXItdG9wLXdpZHRoOjFweDtib3JkZXItcmlnaHQtd2lkdGg6MXB4O2JvcmRlci1ib3R0b20td2lkdGg6MXB4O2JvcmRlci1sZWZ0LXdpZHRoOjFweDttYXJnaW4tdG9wOjEwcHg7bWFyZ2luLXJpZ2h0OjBweDttYXJnaW4tYm90dG9tOjEwcHg7bWFyZ2luLWxlZnQ6MHB4O2JvcmRlci1jb2xsYXBzZTpjb2xsYXBzZTt9Ci5sd3NzX3NlbGVjdGFibGUubHdzLXRvcC1jZWxse3BhZGRpbmctdG9wOjIwcHg7cGFkZGluZy1yaWdodDoyMHB4O3BhZGRpbmctYm90dG9tOjIwcHg7cGFkZGluZy1sZWZ0OjIwcHg7YmFja2dyb3VuZC1jb2xvcjojM2ZhOWY1O2NvbG9yOiNmZmZmZmY7Zm9udC1zaXplOjI1cHg7dGV4dC1hbGlnbjpjZW50ZXI7Zm9udC1mYW1pbHk6QXJpYWwsIEhlbHZldGljYSwgc2Fucy1zZXJpZjtmb250LXdlaWdodDozMDA7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1taWRkbGUtY2VsbHt0ZXh0LWFsaWduOmp1c3RpZnk7Y29sb3I6IzY2NjtwYWRkaW5nLXRvcDoyMHB4O3BhZGRpbmctcmlnaHQ6MjBweDtwYWRkaW5nLWJvdHRvbTo1cHg7cGFkZGluZy1sZWZ0OjIwcHg7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1taWRkbGUtY2VsbC1wb2ludHN7dGV4dC1hbGlnbjpjZW50ZXI7Y29sb3I6IzY2NjtwYWRkaW5nLXRvcDo1cHg7cGFkZGluZy1yaWdodDoyMHB4O3BhZGRpbmctYm90dG9tOjVweDtwYWRkaW5nLWxlZnQ6MjBweDtmb250LXNpemU6MjBweDtmb250LWZhbWlseTpBcmlhbCwgSGVsdmV0aWNhLCBzYW5zLXNlcmlmO2ZvbnQtd2VpZ2h0OmJvbGQ7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1saW5re2NvbG9yOiM3ZjBmZmY7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1yZXdhcmRzLXRhYmxle21hcmdpbi10b3A6MTBweDttYXJnaW4tcmlnaHQ6MHB4O21hcmdpbi1ib3R0b206MTBweDttYXJnaW4tbGVmdDowcHg7YmFja2dyb3VuZDojZWVlO3dpZHRoOjEwMCU7Zm9udC1mYW1pbHk6QXJpYWwsIEhlbHZldGljYSwgc2Fucy1zZXJpZjt9Ci5sd3NzX3NlbGVjdGFibGUubHdzLXJld2FyZHMtY2VsbC1pbWd7cGFkZGluZy10b3A6NXB4O3BhZGRpbmctcmlnaHQ6NXB4O3BhZGRpbmctYm90dG9tOjVweDtwYWRkaW5nLWxlZnQ6NXB4O3RleHQtYWxpZ246Y2VudGVyO30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkcy1jZWxsLWxlZnR7cGFkZGluZy10b3A6NXB4O3BhZGRpbmctcmlnaHQ6NXB4O3BhZGRpbmctYm90dG9tOjVweDtwYWRkaW5nLWxlZnQ6NXB4O3RleHQtYWxpZ246anVzdGlmeTt3aWR0aDoxMDAlO30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkcy1jZWxsLXJpZ2h0e3BhZGRpbmctdG9wOjVweDtwYWRkaW5nLXJpZ2h0OjVweDtwYWRkaW5nLWJvdHRvbTo1cHg7cGFkZGluZy1sZWZ0OjVweDt0ZXh0LWFsaWduOmp1c3RpZnk7d2lkdGg6ODBweDt9Ci5sd3NzX3NlbGVjdGFibGUubHdzLXJld2FyZHMtc2Vwe2JvcmRlci10b3Atd2lkdGg6MHB4O2JvcmRlci1yaWdodC13aWR0aDowcHg7Ym9yZGVyLWJvdHRvbS13aWR0aDoxcHg7Ym9yZGVyLWxlZnQtd2lkdGg6MHB4O21hcmdpbi10b3A6MHB4O21hcmdpbi1yaWdodDowcHg7bWFyZ2luLWJvdHRvbToxMHB4O21hcmdpbi1sZWZ0OjBweDtwYWRkaW5nLXRvcDowcHg7cGFkZGluZy1yaWdodDowcHg7cGFkZGluZy1ib3R0b206MHB4O3BhZGRpbmctbGVmdDowcHg7Ym9yZGVyLWNvbG9yOiM5OTk7Ym9yZGVyLXN0eWxlOmRvdHRlZDt9Ci5sd3NzX3NlbGVjdGFibGUubHdzLXJld2FyZC1uYW1le2ZvbnQtc2l6ZToxNXB4O2ZvbnQtZmFtaWx5OkFyaWFsLCBIZWx2ZXRpY2EsIHNhbnMtc2VyaWY7dGV4dC10cmFuc2Zvcm06dXBwZXJjYXNlO30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkLWRlc2N7Zm9udC1zaXplOjE1cHg7Zm9udC1mYW1pbHk6QXJpYWwsIEhlbHZldGljYSwgc2Fucy1zZXJpZjtmb250LXdlaWdodDpib2xkO30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkLWNvc3R7Zm9udC1zaXplOjEycHg7Zm9udC1mYW1pbHk6QXJpYWwsIEhlbHZldGljYSwgc2Fucy1zZXJpZjtmb250LXdlaWdodDp0aGluO2NvbG9yOiM3Nzc7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1yZXdhcmQtbW9yZXtmb250LXNpemU6MTJweDtmb250LWZhbWlseTpBcmlhbCwgSGVsdmV0aWNhLCBzYW5zLXNlcmlmO2ZvbnQtd2VpZ2h0OnRoaW47Y29sb3I6I2Y3Nzt9Ci5sd3NzX3NlbGVjdGFibGUubHdzLXJld2FyZC1yZWRlZW17bWFyZ2luLXRvcDphdXRvO21hcmdpbi1yaWdodDphdXRvO21hcmdpbi1ib3R0b206YXV0bzttYXJnaW4tbGVmdDphdXRvO3BhZGRpbmctdG9wOjEwcHg7cGFkZGluZy1yaWdodDoxMHB4O3BhZGRpbmctYm90dG9tOjEwcHg7cGFkZGluZy1sZWZ0OjEwcHg7Ym9yZGVyLXRvcC1sZWZ0LXJhZGl1czo0cHg7Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6NHB4O2JvcmRlci1ib3R0b20tcmlnaHQtcmFkaXVzOjRweDtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOjRweDtiYWNrZ3JvdW5kLWNvbG9yOiMzZmE5ZjU7Y29sb3I6I2ZmZjtmb250LXdlaWdodDpib2xkO3RleHQtZGVjb3JhdGlvbjpub25lO30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkLXJlZGVlbS1ub3R7bWFyZ2luLXRvcDphdXRvO21hcmdpbi1yaWdodDphdXRvO21hcmdpbi1ib3R0b206YXV0bzttYXJnaW4tbGVmdDphdXRvO3BhZGRpbmctdG9wOjEwcHg7cGFkZGluZy1yaWdodDoxMHB4O3BhZGRpbmctYm90dG9tOjEwcHg7cGFkZGluZy1sZWZ0OjEwcHg7Ym9yZGVyLXRvcC1sZWZ0LXJhZGl1czo0cHg7Ym9yZGVyLXRvcC1yaWdodC1yYWRpdXM6NHB4O2JvcmRlci1ib3R0b20tcmlnaHQtcmFkaXVzOjRweDtib3JkZXItYm90dG9tLWxlZnQtcmFkaXVzOjRweDtiYWNrZ3JvdW5kLWNvbG9yOiM5OTk7Y29sb3I6I2NjYztmb250LXdlaWdodDpib2xkO3RleHQtZGVjb3JhdGlvbjpub25lO30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtYm90dG9tLWNlbGx7cGFkZGluZy10b3A6NXB4O3BhZGRpbmctcmlnaHQ6MjBweDtwYWRkaW5nLWJvdHRvbTo1cHg7cGFkZGluZy1sZWZ0OjIwcHg7YmFja2dyb3VuZC1jb2xvcjojZWVlO2NvbG9yOiM2NjY7Zm9udC1zaXplOjEycHg7dGV4dC1hbGlnbjpjZW50ZXI7Zm9udC1mYW1pbHk6QXJpYWwsIEhlbHZldGljYSwgc2Fucy1zZXJpZjtmb250LXdlaWdodDozMDA7fQoubHdzLXJld2FyZC10aHVtYm5haWx7Zm9udC1zaXplOjVlbTt9Cg==',
					'Lmx3c3Nfc2VsZWN0YWJsZS5sd3MtbWFpbi1jb250ZW5ldXJ7d2lkdGg6NjAwcHg7YmFja2dyb3VuZC1jb2xvcjojZmZmO2JvcmRlci1jb2xvcjojZWVlO2JvcmRlci1zdHlsZTpzb2xpZDtib3JkZXItdG9wLXdpZHRoOjFweDtib3JkZXItcmlnaHQtd2lkdGg6MXB4O2JvcmRlci1ib3R0b20td2lkdGg6MXB4O2JvcmRlci1sZWZ0LXdpZHRoOjFweDttYXJnaW4tdG9wOjEwcHg7bWFyZ2luLXJpZ2h0OjBweDttYXJnaW4tYm90dG9tOjEwcHg7bWFyZ2luLWxlZnQ6MHB4O2JvcmRlci1jb2xsYXBzZTpjb2xsYXBzZTtmb250LWZhbWlseTpBcmlhbCwgSGVsdmV0aWNhLCBzYW5zLXNlcmlmO30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtdG9wLWNlbGx7cGFkZGluZy10b3A6MjBweDtwYWRkaW5nLXJpZ2h0OjIwcHg7cGFkZGluZy1ib3R0b206MjBweDtwYWRkaW5nLWxlZnQ6MjBweDtiYWNrZ3JvdW5kLWNvbG9yOiMzZmE5ZjU7Y29sb3I6I2ZmZmZmZjtmb250LXNpemU6MjVweDt0ZXh0LWFsaWduOmNlbnRlcjtmb250LWZhbWlseTpBcmlhbCwgSGVsdmV0aWNhLCBzYW5zLXNlcmlmO2ZvbnQtd2VpZ2h0OjMwMDt9Ci5sd3NzX3NlbGVjdGFibGUubHdzLW1pZGRsZS1jZWxse3RleHQtYWxpZ246anVzdGlmeTtjb2xvcjojNjY2O3BhZGRpbmctdG9wOjIwcHg7cGFkZGluZy1yaWdodDoyMHB4O3BhZGRpbmctYm90dG9tOjVweDtwYWRkaW5nLWxlZnQ6MjBweDt9Ci5sd3NzX3NlbGVjdGFibGUubHdzLW1pZGRsZS1jZWxsLXBvaW50c3t0ZXh0LWFsaWduOmNlbnRlcjtjb2xvcjojNjY2O3BhZGRpbmctdG9wOjVweDtwYWRkaW5nLXJpZ2h0OjIwcHg7cGFkZGluZy1ib3R0b206NXB4O3BhZGRpbmctbGVmdDoyMHB4O2ZvbnQtc2l6ZToyMHB4O2ZvbnQtZmFtaWx5OkFyaWFsLCBIZWx2ZXRpY2EsIHNhbnMtc2VyaWY7Zm9udC13ZWlnaHQ6Ym9sZDt9Ci5sd3NzX3NlbGVjdGFibGUubHdzLWxpbmt7Y29sb3I6IzdmMGZmZjt9Ci5sd3NzX3NlbGVjdGFibGUubHdzLXJld2FyZHMtdGFibGV7bWFyZ2luLXRvcDoxMHB4O21hcmdpbi1yaWdodDowcHg7bWFyZ2luLWJvdHRvbToxMHB4O21hcmdpbi1sZWZ0OjBweDtiYWNrZ3JvdW5kOiNlZWU7d2lkdGg6MTAwJTtmb250LWZhbWlseTpBcmlhbCwgSGVsdmV0aWNhLCBzYW5zLXNlcmlmO30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkcy1jZWxsLWltZ3twYWRkaW5nLXRvcDo1cHg7cGFkZGluZy1yaWdodDo1cHg7cGFkZGluZy1ib3R0b206NXB4O3BhZGRpbmctbGVmdDo1cHg7dGV4dC1hbGlnbjpjZW50ZXI7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1yZXdhcmRzLWNlbGwtbGVmdHtwYWRkaW5nLXRvcDo1cHg7cGFkZGluZy1yaWdodDo1cHg7cGFkZGluZy1ib3R0b206NXB4O3BhZGRpbmctbGVmdDo1cHg7dGV4dC1hbGlnbjpqdXN0aWZ5O3dpZHRoOmF1dG87fQoubHdzc19zZWxlY3RhYmxlLmx3cy1yZXdhcmRzLWNlbGwtcmlnaHR7cGFkZGluZy10b3A6NXB4O3BhZGRpbmctcmlnaHQ6NXB4O3BhZGRpbmctYm90dG9tOjVweDtwYWRkaW5nLWxlZnQ6NXB4O3RleHQtYWxpZ246anVzdGlmeTt3aWR0aDo4MHB4O30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkcy1zZXB7Ym9yZGVyLXRvcC13aWR0aDowcHg7Ym9yZGVyLXJpZ2h0LXdpZHRoOjBweDtib3JkZXItYm90dG9tLXdpZHRoOjFweDtib3JkZXItbGVmdC13aWR0aDowcHg7bWFyZ2luLXRvcDowcHg7bWFyZ2luLXJpZ2h0OjBweDttYXJnaW4tYm90dG9tOjEwcHg7bWFyZ2luLWxlZnQ6MHB4O3BhZGRpbmctdG9wOjBweDtwYWRkaW5nLXJpZ2h0OjBweDtwYWRkaW5nLWJvdHRvbTowcHg7cGFkZGluZy1sZWZ0OjBweDtib3JkZXItY29sb3I6Izk5OTtib3JkZXItc3R5bGU6ZG90dGVkO30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkLW5hbWV7Zm9udC1zaXplOjE1cHg7Zm9udC1mYW1pbHk6QXJpYWwsIEhlbHZldGljYSwgc2Fucy1zZXJpZjt0ZXh0LXRyYW5zZm9ybTp1cHBlcmNhc2U7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1yZXdhcmQtZGVzY3tmb250LXNpemU6MTVweDtmb250LWZhbWlseTpBcmlhbCwgSGVsdmV0aWNhLCBzYW5zLXNlcmlmO2ZvbnQtd2VpZ2h0OmJvbGQ7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1yZXdhcmQtY29zdHtmb250LXNpemU6MTJweDtmb250LWZhbWlseTpBcmlhbCwgSGVsdmV0aWNhLCBzYW5zLXNlcmlmO2ZvbnQtd2VpZ2h0OnRoaW47Y29sb3I6Izc3Nzt9Ci5sd3NzX3NlbGVjdGFibGUubHdzLXJld2FyZC1tb3Jle2ZvbnQtc2l6ZToxMnB4O2ZvbnQtZmFtaWx5OkFyaWFsLCBIZWx2ZXRpY2EsIHNhbnMtc2VyaWY7Zm9udC13ZWlnaHQ6dGhpbjtjb2xvcjojZjc3O30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkLXJlZGVlbXttYXJnaW4tdG9wOmF1dG87bWFyZ2luLXJpZ2h0OmF1dG87bWFyZ2luLWJvdHRvbTphdXRvO21hcmdpbi1sZWZ0OmF1dG87cGFkZGluZy10b3A6MTBweDtwYWRkaW5nLXJpZ2h0OjEwcHg7cGFkZGluZy1ib3R0b206MTBweDtwYWRkaW5nLWxlZnQ6MTBweDtib3JkZXItdG9wLWxlZnQtcmFkaXVzOjRweDtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czo0cHg7Ym9yZGVyLWJvdHRvbS1yaWdodC1yYWRpdXM6NHB4O2JvcmRlci1ib3R0b20tbGVmdC1yYWRpdXM6NHB4O2JhY2tncm91bmQtY29sb3I6IzNmYTlmNTtjb2xvcjojZmZmO2ZvbnQtZmFtaWx5OkFyaWFsO3RleHQtYWxpZ246Y2VudGVyO2ZvbnQtd2VpZ2h0OmJvbGQ7dGV4dC1kZWNvcmF0aW9uOm5vbmU7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1yZXdhcmQtcmVkZWVtLW5vdHttYXJnaW4tdG9wOmF1dG87bWFyZ2luLXJpZ2h0OmF1dG87bWFyZ2luLWJvdHRvbTphdXRvO21hcmdpbi1sZWZ0OmF1dG87cGFkZGluZy10b3A6MTBweDtwYWRkaW5nLXJpZ2h0OjEwcHg7cGFkZGluZy1ib3R0b206MTBweDtwYWRkaW5nLWxlZnQ6MTBweDtib3JkZXItdG9wLWxlZnQtcmFkaXVzOjRweDtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czo0cHg7Ym9yZGVyLWJvdHRvbS1yaWdodC1yYWRpdXM6NHB4O2JvcmRlci1ib3R0b20tbGVmdC1yYWRpdXM6NHB4O2JhY2tncm91bmQtY29sb3I6Izk5OTtjb2xvcjojY2NjO2ZvbnQtZmFtaWx5OkFyaWFsO3RleHQtYWxpZ246Y2VudGVyO2ZvbnQtd2VpZ2h0OmJvbGQ7dGV4dC1kZWNvcmF0aW9uOm5vbmU7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1ib3R0b20tY2VsbHtwYWRkaW5nLXRvcDo1cHg7cGFkZGluZy1yaWdodDoyMHB4O3BhZGRpbmctYm90dG9tOjVweDtwYWRkaW5nLWxlZnQ6MjBweDtiYWNrZ3JvdW5kLWNvbG9yOiNlZWU7Y29sb3I6IzY2Njtmb250LXNpemU6MTJweDt0ZXh0LWFsaWduOmNlbnRlcjtmb250LWZhbWlseTpBcmlhbCwgSGVsdmV0aWNhLCBzYW5zLXNlcmlmO2ZvbnQtd2VpZ2h0OjMwMDt9Ci5sd3MtcmV3YXJkLXRodW1ibmFpbHtmb250LXNpemU6NWVtO30K',
					'Lmx3c3Nfc2VsZWN0YWJsZS5sd3MtbWFpbi1jb250ZW5ldXIuc3RhbmRhcmR7YmFja2dyb3VuZC1jb2xvcjojZmZmO2JvcmRlci1jb2xvcjojZWVlO2JvcmRlci1zdHlsZTpzb2xpZDtib3JkZXItdG9wLXdpZHRoOjFweDtib3JkZXItcmlnaHQtd2lkdGg6MXB4O2JvcmRlci1ib3R0b20td2lkdGg6MXB4O2JvcmRlci1sZWZ0LXdpZHRoOjFweDttYXJnaW4tdG9wOjEwcHg7bWFyZ2luLXJpZ2h0OjBweDttYXJnaW4tYm90dG9tOjEwcHg7bWFyZ2luLWxlZnQ6MHB4O2JvcmRlci1jb2xsYXBzZTpjb2xsYXBzZTtmb250LWZhbWlseTpBcmlhbCwgSGVsdmV0aWNhLCBzYW5zLXNlcmlmO30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmwtdGl0bGUuc3RhbmRhcmR7Ym9yZGVyLXRvcC13aWR0aDowcHg7Ym9yZGVyLXJpZ2h0LXdpZHRoOjBweDtib3JkZXItYm90dG9tLXdpZHRoOjNweDtib3JkZXItbGVmdC13aWR0aDowcHg7bWFyZ2luLXRvcDowcHg7bWFyZ2luLXJpZ2h0OjBweDttYXJnaW4tYm90dG9tOjEwcHg7bWFyZ2luLWxlZnQ6MHB4O3BhZGRpbmctdG9wOjEwcHg7cGFkZGluZy1yaWdodDowcHg7cGFkZGluZy1ib3R0b206MTBweDtwYWRkaW5nLWxlZnQ6MHB4O2JvcmRlci1jb2xvcjojM2ZhOWY1O2JvcmRlci1zdHlsZTpzb2xpZDtmb250LWZhbWlseTpBcmlhbCwgSGVsdmV0aWNhLCBzYW5zLXNlcmlmO2ZvbnQtc2l6ZToyMHB4O30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmwtYXZhaWxhYmxlLXBvaW50cy5zdGFuZGFyZHt0ZXh0LWFsaWduOnJpZ2h0O2ZvbnQtc2l6ZToyMHB4O2NvbG9yOiMzZmE5ZjU7fQoubHdzc19zZWxlY3RhYmxlLmx3cy13ci1zaW1wbGUtcG9pbnRzLnN0YW5kYXJke2ZvbnQtd2VpZ2h0OmJvbGQ7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1ybC1jb250ZW5ldXIuc3RhbmRhcmR7cGFkZGluZy10b3A6MTBweDtwYWRkaW5nLXJpZ2h0OjBweDtwYWRkaW5nLWJvdHRvbTowcHg7cGFkZGluZy1sZWZ0OjBweDt9Ci5sd3NzX3NlbGVjdGFibGUubHdzLXN1Yi1jb250ZW5ldXIuc3RhbmRhcmR7d2lkdGg6MTAwJTt9Ci5sd3NzX3NlbGVjdGFibGUubHdzLXJld2FyZHMtY2VsbC1pbWd7cGFkZGluZy10b3A6NXB4O3BhZGRpbmctcmlnaHQ6NXB4O3BhZGRpbmctYm90dG9tOjVweDtwYWRkaW5nLWxlZnQ6NXB4O3RleHQtYWxpZ246Y2VudGVyO30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkcy1jZWxsLWxlZnR7cGFkZGluZy10b3A6NXB4O3BhZGRpbmctcmlnaHQ6NXB4O3BhZGRpbmctYm90dG9tOjVweDtwYWRkaW5nLWxlZnQ6NXB4O3RleHQtYWxpZ246anVzdGlmeTt3aWR0aDphdXRvO30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkcy1jZWxsLXJpZ2h0e3BhZGRpbmctdG9wOjVweDtwYWRkaW5nLXJpZ2h0OjVweDtwYWRkaW5nLWJvdHRvbTo1cHg7cGFkZGluZy1sZWZ0OjVweDt0ZXh0LWFsaWduOmNlbnRlcjt3aWR0aDo4MHB4O30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkcy1zZXB7Ym9yZGVyLXRvcC13aWR0aDowcHg7Ym9yZGVyLXJpZ2h0LXdpZHRoOjBweDtib3JkZXItYm90dG9tLXdpZHRoOjFweDtib3JkZXItbGVmdC13aWR0aDowcHg7bWFyZ2luLXRvcDowcHg7bWFyZ2luLXJpZ2h0OjBweDttYXJnaW4tYm90dG9tOjEwcHg7bWFyZ2luLWxlZnQ6MHB4O3BhZGRpbmctdG9wOjBweDtwYWRkaW5nLXJpZ2h0OjBweDtwYWRkaW5nLWJvdHRvbTowcHg7cGFkZGluZy1sZWZ0OjBweDtib3JkZXItY29sb3I6Izk5OTtib3JkZXItc3R5bGU6ZG90dGVkO30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkLW5hbWV7Zm9udC1zaXplOjE1cHg7Zm9udC1mYW1pbHk6QXJpYWwsIEhlbHZldGljYSwgc2Fucy1zZXJpZjt0ZXh0LXRyYW5zZm9ybTp1cHBlcmNhc2U7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1yZXdhcmQtZGVzY3tmb250LXNpemU6MTVweDtmb250LWZhbWlseTpBcmlhbCwgSGVsdmV0aWNhLCBzYW5zLXNlcmlmO2ZvbnQtd2VpZ2h0OmJvbGQ7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1yZXdhcmQtY29zdHtmb250LXNpemU6MTJweDtmb250LWZhbWlseTpBcmlhbCwgSGVsdmV0aWNhLCBzYW5zLXNlcmlmO2ZvbnQtd2VpZ2h0OnRoaW47Y29sb3I6Izc3Nzt9Ci5sd3NzX3NlbGVjdGFibGUubHdzLXJld2FyZC1tb3Jle2ZvbnQtc2l6ZToxMnB4O2ZvbnQtZmFtaWx5OkFyaWFsLCBIZWx2ZXRpY2EsIHNhbnMtc2VyaWY7Zm9udC13ZWlnaHQ6dGhpbjtjb2xvcjojZjc3O30KLmx3c3Nfc2VsZWN0YWJsZS5sd3MtcmV3YXJkLXJlZGVlbXttYXJnaW4tdG9wOmF1dG87bWFyZ2luLXJpZ2h0OmF1dG87bWFyZ2luLWJvdHRvbTphdXRvO21hcmdpbi1sZWZ0OmF1dG87cGFkZGluZy10b3A6MTBweDtwYWRkaW5nLXJpZ2h0OjEwcHg7cGFkZGluZy1ib3R0b206MTBweDtwYWRkaW5nLWxlZnQ6MTBweDtib3JkZXItdG9wLWxlZnQtcmFkaXVzOjRweDtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czo0cHg7Ym9yZGVyLWJvdHRvbS1yaWdodC1yYWRpdXM6NHB4O2JvcmRlci1ib3R0b20tbGVmdC1yYWRpdXM6NHB4O2JhY2tncm91bmQtY29sb3I6IzNmYTlmNTtjb2xvcjojZmZmO2ZvbnQtZmFtaWx5OkFyaWFsO3RleHQtYWxpZ246Y2VudGVyO2ZvbnQtd2VpZ2h0OmJvbGQ7dGV4dC1kZWNvcmF0aW9uOm5vbmU7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1yZXdhcmQtcmVkZWVtLW5vdHttYXJnaW4tdG9wOmF1dG87bWFyZ2luLXJpZ2h0OmF1dG87bWFyZ2luLWJvdHRvbTphdXRvO21hcmdpbi1sZWZ0OmF1dG87cGFkZGluZy10b3A6MTBweDtwYWRkaW5nLXJpZ2h0OjEwcHg7cGFkZGluZy1ib3R0b206MTBweDtwYWRkaW5nLWxlZnQ6MTBweDtib3JkZXItdG9wLWxlZnQtcmFkaXVzOjRweDtib3JkZXItdG9wLXJpZ2h0LXJhZGl1czo0cHg7Ym9yZGVyLWJvdHRvbS1yaWdodC1yYWRpdXM6NHB4O2JvcmRlci1ib3R0b20tbGVmdC1yYWRpdXM6NHB4O2JhY2tncm91bmQtY29sb3I6Izk5OTtjb2xvcjojY2NjO2ZvbnQtZmFtaWx5OkFyaWFsO3RleHQtYWxpZ246Y2VudGVyO2ZvbnQtd2VpZ2h0OmJvbGQ7dGV4dC1kZWNvcmF0aW9uOm5vbmU7fQoubHdzc19zZWxlY3RhYmxlLmx3cy1ib3R0b20tY2VsbHtwYWRkaW5nLXRvcDo1cHg7cGFkZGluZy1yaWdodDoyMHB4O3BhZGRpbmctYm90dG9tOjVweDtwYWRkaW5nLWxlZnQ6MjBweDtiYWNrZ3JvdW5kLWNvbG9yOiNlZWU7Y29sb3I6IzY2Njtmb250LXNpemU6MTJweDt0ZXh0LWFsaWduOmNlbnRlcjtmb250LWZhbWlseTpBcmlhbCwgSGVsdmV0aWNhLCBzYW5zLXNlcmlmO2ZvbnQtd2VpZ2h0OjMwMDt9Ci5sd3MtcmV3YXJkLXRodW1ibmFpbHtmb250LXNpemU6NWVtO30K',
				);
				if( !in_array($css, $oldDftValues) )
				{
					\update_option('lws_woorewards_rewards_use_grid', ''); // keep old fashion
				}
				else
				{
					\delete_option('lws_woorewards_rewards_template'); // reset style
					\update_option('lws_woorewards_rewards_use_grid', 'on');
				}
			}
		}

		//~ if( \version_compare($fromVersion, '3.5.0', '<') )
			//~ \update_option('lws_woorewards_redirect_to_licence', 2);

		if( \version_compare($fromVersion, '3.10.0', '<') )
			$this->createSponsoredByMetas();

		if( \version_compare($fromVersion, '3.10.2', '<') )
			$this->mergeReferralToSponsorship();

		if( \version_compare($fromVersion, '3.11.0', '<') )
		{
			if( !\get_option('lws_woorewards_refund_on_status') )
				\update_option('lws_woorewards_refund_on_status', array('cancelled', 'refunded', 'failed'));
		}

		if( \version_compare($fromVersion, '3.13.1', '<') )
		{
			// default become : all active pools
			if( !\get_option('lws_woorewards_product_potential_pool') )
				\update_option('lws_woorewards_product_potential_position', 'not_displayed');
			if( !\get_option('lws_woorewards_cart_potential_pool') )
				\update_option('lws_woorewards_cart_potential_position', 'not_displayed');
		}

		if( \version_compare($fromVersion, '3.13.4', '<') )
		{
			$this->to3_13_4();
		}

		if (\version_compare($fromVersion, '4.0.0', '<'))
		{
			$this->to4_0();
			$reload = true;
		}

		if (\version_compare($fromVersion, '4.2.0', '<')) {
			$this->updateCooldownKeys();
		}

		if (\version_compare($fromVersion, '4.2.5', '<')) {
			$this->to4_2_5();
		}

		if (\version_compare($fromVersion, '4.2.10', '<')) {
			$this->to4_2_10();
		}

		if (\version_compare($fromVersion, '4.6.1.1', '<')) {
			$this->switchOffFacebook();
		}

		update_option('lws_woorewards_pro_version', LWS_WOOREWARDS_PRO_VERSION);
		return $reload;
	}

	/** Since Facebook API bugs @see https://developers.facebook.com/support/bugs/199217515444932/
	 *	We hide this feature if not already setup and validated.
	 *	A small link allow to switch ON again at bottom of social settings page. */
	protected function switchOffFacebook()
	{
		if (false === \get_option('lws_woorewards_facebook_settings_hidden', false)) {
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/ui/adminscreens/socials.php';
			list($verif, $confirmed) = \LWS\WOOREWARDS\PRO\Ui\AdminScreens\Socials::getVerifiedStatus('facebook');
			// never confirmed, so never will be
			\update_option('lws_woorewards_facebook_settings_hidden', $confirmed ? '' : 'on');
		}
	}

	protected function updateCooldownKeys()
	{
		global $wpdb;
		$sql = <<<EOT
UPDATE {$wpdb->postmeta} as m
INNER JOIN {$wpdb->posts} as p ON p.ID=m.post_id AND p.post_type='lws-wre-event'
SET m.meta_key='wre_event_cooldown'
WHERE m.meta_key='wre_event_visit_cooldown'
EOT;
		$wpdb->query($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
	}

	protected function to4_2_5()
	{
		global $wpdb;
		// SponsoredRegistration trigger flag: once per sponsor => once per couple {sponsor, sponsored}
		$sql = <<<EOT
INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value)
SELECT um.user_id as sponsor_id, um.meta_key as event_key, sp.user_id as sponsored
FROM {$wpdb->usermeta} as um
INNER JOIN {$wpdb->usermeta} as sp ON um.user_id=sp.meta_value AND sp.meta_key='lws_woorewards_sponsored_by'
WHERE um.meta_key LIKE 'lws_woorewards_pro_events_sponsoredregistration-%'
EOT;
		$wpdb->query($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPressDotOrg.sniffs.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
	}

	protected function to4_0()
	{
		global $wpdb;

		// too soon to call convenience \LWS_WooRewards_Pro::getBuyablePools())
		$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
			'post_status' => array('publish', 'private')
		));
		$buyables = $pools->filter(function($item){return $item->isBuyable();})->asArray();

		// count of buyable unlockable per point stack
		$uCounts = array();
		foreach( $buyables as $pool )
			$uCounts[$pool->getStackId()] = 0;
		foreach( $buyables as $pool )
			$uCounts[$pool->getStackId()] += $pool->getUnlockables()->count();

		// Manual redeem Option merged into auto unlock settings
		// Odd/Specific behavior when only 1 reward is replaced by an explicit option
		// default behavior is always like old option 'force choice'='on'
		foreach( $buyables as $pool )
		{
			if( \LWS\WOOREWARDS\Core\Pool::T_STANDARD != $pool->getOption('type') )
				continue;
			if( \get_post_meta($pool->getId(), 'wre_pool_force_choice', true) )
				continue;
			$stack = $pool->getStackId();
			if( isset($uCounts[$stack]) && $uCounts[$stack] != 1 )
				continue;
			// single reward => auto unlock is now an explicit option
			\update_post_meta($pool->getId(), 'wre_pool_best_unlock', 'loop');
		}

		// first order only option replaced by affected order
		$wpdb->query("UPDATE {$wpdb->postmeta} SET `meta_value`='1' WHERE `meta_key`='wre_event_first_order_only' AND `meta_value`='on'");
		$wpdb->query("UPDATE {$wpdb->postmeta} SET `meta_key`='_affected_orders' WHERE `meta_key`='wre_event_first_order_only'");

		// event pool-name to event id
		$sql = <<<EOT
SELECT p.post_name, m.post_id
FROM {$wpdb->postmeta} as m
INNER JOIN {$wpdb->posts} as e ON m.post_id=e.ID
INNER JOIN {$wpdb->posts} as p ON p.ID=e.post_parent
WHERE m.`meta_key`='wre_event_type'
AND m.`meta_value` = 'lws_woorewards_pro_events_postcomment'
EOT;
		$events = $wpdb->get_results($sql);
		foreach( $events as $event )
		{
			$up = <<<EOT
UPDATE {$wpdb->usermeta}
SET `meta_key`='lws_wre_event_comment_{$event->post_id}'
WHERE `meta_key`='lws_wre_event_comment_{$event->post_name}'
EOT;
			$wpdb->query($up);
		}
	}

	protected function to3_13_4()
	{
		global $wpdb;

		/// take only part before timeshift of '2020-04-21T08:29:14+00:00' and get the timestamp
		$sql = <<<EOT
UPDATE {$wpdb->postmeta} SET meta_value=UNIX_TIMESTAMP(LEFT(meta_value, 19))
WHERE meta_key='woorewards_reminder_done' AND meta_value LIKE '%T%'
EOT;
		$wpdb->query($sql);

		/// rename coupon meta
		$sql = <<<EOT
SELECT m.post_id
FROM {$wpdb->postmeta} as m
INNER JOIN {$wpdb->posts} as p ON p.ID=m.post_id AND post_type='shop_coupon'
WHERE m.meta_key='woorewards_permanent'
EOT;
		$coupons = $wpdb->get_col($sql);
		if( $coupons )
		{
			foreach( $coupons as $postId )
				\update_post_meta($postId, 'lws_woorewards_auto_apply', 'on');
		}
	}

	protected function mergeReferralToSponsorship()
	{
		global $wpdb;
		$origin = array(
			'lws_woorewards_pro_events_referralregister'   => 'lws_woorewards_pro_events_sponsoredregistration',
			'lws_woorewards_pro_events_referralfirstorder' => 'lws_woorewards_pro_events_sponsoredfirstorder',
		);
		foreach( $origin as $src => $dst )
		{
			$existants = array(
				'sponsor'  => $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='wre_event_type' AND meta_value='{$dst}'"),
				'referral' => $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='wre_event_type' AND meta_value='{$src}'"),
			);

			if( $existants['sponsor'] )
			{
				$existants['sponsor'] = implode(',', array_map('intval', $existants['sponsor']));
				$value = \esc_sql(serialize(array('sponsor')));
				$wpdb->query("INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
SELECT ID, 'woorewards_sponsorship_origin', '{$value}' FROM {$wpdb->posts} WHERE ID IN ({$existants['sponsor']})");
			}
			if( $existants['referral'] )
			{
				$existants['referral'] = implode(',', array_map('intval', $existants['referral']));
				$value = \esc_sql(serialize(array('referral')));
				$wpdb->query("INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
SELECT ID, 'woorewards_sponsorship_origin', '{$value}' FROM {$wpdb->posts} WHERE ID IN ({$existants['referral']})");
			}
		}

		$type = array(
			'lws_woorewards_pro_events_referralregister'   => 'lws_woorewards_pro_events_sponsoredregistration',
			'lws_woorewards_pro_events_referralfirstorder' => 'lws_woorewards_pro_events_sponsoredfirstorder',
		);
		foreach( $type as $src => $dst )
		{
			$wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value='{$dst}' WHERE meta_key='wre_event_type' AND meta_value='{$src}'");
		}
	}

	protected function createSponsoredByMetas()
	{
		global $wpdb;
		$sql = <<<EOT
INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value)
SELECT u.ID as sponsored_id, 'lws_woorewards_sponsored_by', m.user_id as sponsor_id
FROM {$wpdb->usermeta} as m
INNER JOIN {$wpdb->users} as u ON m.meta_value=u.user_email
WHERE meta_key='lws_wooreward_used_sponsorship'
EOT;
		$wpdb->query($sql);
	}

	protected function createWebHooksTable()
	{
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$table = <<<EOT
CREATE TABLE `{$wpdb->lwsWebhooksEvents}` (
	`id` BIGINT(20) NOT NULL AUTO_INCREMENT,
	`creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Event date',
	`user_id` BIGINT(20) NOT NULL,
	`remote_user_id` VARCHAR(64) NULL DEFAULT NULL COMMENT 'An identifier for the user on the social network',
	`network` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'Social network name: facebook, instagram...',
	`event` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'Social event: like, follow, share...',
	`origin` VARCHAR(64) NULL DEFAULT NULL COMMENT 'Object the event as a post id. With user_id, are used to test event unicity',
	`data` TEXT NULL DEFAULT NULL COMMENT 'The event raw content',
	PRIMARY KEY `id`  (`id`),
	KEY `user_id` (`user_id`),
	KEY `origin` (`origin`)
	) {$charset_collate};
EOT;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		ob_start(array(get_class(), 'log')); // dbDelta could write on standard output
		dbDelta($table);
		ob_end_flush();
	}

	/** A special table store link between badge and user */
	protected function createUserBadgeTable()
	{
		global $wpdb;
		$table = $wpdb->prefix.'lws_wr_userbadge';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			`ub_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			`user_id` bigint(20) UNSIGNED NOT NULL DEFAULT '0',
			`badge_id` bigint(20) UNSIGNED NOT NULL DEFAULT '0',
			`origin` tinytext NOT NULL DEFAULT '' COMMENT 'eg. unlockable post id (max 255 char)',
			`assign_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY `ub_id`  (`ub_id`),
			KEY `user_id` (`user_id`),
			KEY `badge_id` (`badge_id`)
			) $charset_collate;";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		ob_start(array(get_class(), 'log')); // dbDelta could write on standard output
		dbDelta( $sql );
		ob_end_flush();
	}

	public static function log($msg)
	{
		if( !empty($msg) )
			error_log($msg);
	}

	protected function cssRenamed($optFrom, $cssFrom, $optTo, $cssTo)
	{
		if( !empty($css = \get_option($optFrom, '')) && false === \get_option($optTo, false) )
		{
			$css = str_replace($cssFrom, $cssTo, base64_decode($css));
			\update_option($optTo, base64_encode($css));
		}
	}

	/** default options obtain:
	 * * order amount category filter,
	 * * order completed min amount */
	protected function upgradeStandardPool()
	{
		$minAmount = @intval(\get_option('lws_woorewards_rewards_order_minamount', 0));
		$whiteList = \get_option('lws_woorewards_reward_product_whiteList', '');
		if( empty($whiteList) && $minAmount <= 0 )
			return; // lets default options

		if( !isset($this->stdPool) )
		{
			$this->stdPool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
				'numberposts' => 1,
				'meta_query'  => array(
					array('key' => 'wre_pool_prefab', 'value' => 'yes', 'compare' => 'LIKE'),
					array('key' => 'wre_pool_type', 'value' => \LWS\WOOREWARDS\Core\Pool::T_STANDARD, 'compare' => 'LIKE')
				)
			))->last();
		}
		if( empty($this->stdPool) )
			return; // an error should occured in free version updater since default pool is missing

		if( $minAmount > 0 )
		{
			foreach( $this->stdPool->getEvents()->filterByType('lws_woorewards_events_ordercompleted') as $orderEvent )
				$orderEvent->setMinAmount($orderEvent)->save();
		}

		if( !empty($whiteList) )
		{
			if( !is_array($whiteList) )
				$whiteList = explode(',',$whiteList);
			foreach( $this->stdPool->getEvents()->filterByType('lws_woorewards_events_orderamount') as $orderEvent )
				$orderEvent->setProductCategories($whiteList)->save();
		}
	}

	/** From v2 standalone reward from post.
	 * Could be any of:
	 * coupon (percent, fix or free product) or title or custom.
	 *
	 * note that 'reward_singular' coupon (not cumulable,
	 * means previous coupon removed) setting is ignored
	 * since that option (individually) does not exist anymore.
	 * But permanent become not cumulable (forced).
	 *
	 * @return Unlockable instance. */
	protected function createUnlockableFromV2Post($post)
	{
		$unlock = false;
		$percent = true;

		switch( \get_post_meta($post->ID, 'reward_type', true) )
		{
			case 'coupon':
				$percent = false;
				// no break since fix and percent are the same unlockable
			case 'percent':
				$unlock = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
				$unlock->setInPercent($percent);
				$unlock->setValue(intval(\get_post_meta($post->ID, 'reward_coupon_value', true))); // v2 did not support float

				$unlock->setPermanent(!empty(\get_post_meta($post->ID, 'reward_permanent', true)));
				$unlock->setIndividualUse(!empty(\get_post_meta($post->ID, 'reward_single', true)));
				$unlock->setExcludeSaleItems(!empty(\get_post_meta($post->ID, 'no_sale', true)));

				if( !empty($expiry = intval(\get_post_meta($post->ID, 'reward_expiry_days', true))) )
					$unlock->setTimeout("P{$expiry}D");
				if( ($min = intval(\get_post_meta($post->ID, 'reward_min_buy', true))) > 0 )
					$unlock->setOrderMinimumAmount($min);
				break;

			case 'product':
				$unlock = new \LWS\WOOREWARDS\PRO\Unlockables\FreeProduct();
				$unlock->setProductsIds(array(\get_post_meta($post->ID, 'reward_product_id', true)));

				$unlock->setIndividualUse(!empty(\get_post_meta($post->ID, 'reward_single', true)));
				$unlock->setExcludeSaleItems(!empty(\get_post_meta($post->ID, 'no_sale', true)));

				if( !empty($expiry = intval(\get_post_meta($post->ID, 'reward_expiry_days', true))) )
					$unlock->setTimeout("P{$expiry}D");
				if( ($min = intval(\get_post_meta($post->ID, 'reward_min_buy', true))) > 0 )
					$unlock->setOrderMinimumAmount($min);
				break;

			case 'title':
				$unlock = new \LWS\WOOREWARDS\PRO\Unlockables\UserTitle();
				$unlock->setUserTitle(\get_post_meta($post->ID, 'reward_title', true));
				$unlock->setPosition(\get_post_meta($post->ID, 'reward_position', true));
				break;

			case 'custom':
				$unlock = new \LWS\WOOREWARDS\PRO\Unlockables\CustomReward();
				$unlock->setTodo(\get_post_meta($post->ID, 'reward_custom_adm', true));
				$unlock->setDescription(\get_post_meta($post->ID, 'reward_custom', true));
				break;
		}

		if( $unlock )
		{
			$unlock->setTitle($post->post_title);
			$unlock->setCost(\absint(\get_post_meta($post->ID, 'reward_point_step', true)));
			if( !empty($mediaId = intval(\get_post_meta($post->ID, 'reward_media_id', true))) )
				$unlock->setThumbnail($mediaId);
		}
		return $unlock;
	}

	/** Copy sponsored teaser reward from v2. */
	protected function copySponsoredReward()
	{
		// sponsored, find the post
		$args = array(
			'numberposts' => 1,
			'post_type' => 'lws-sponsored-reward',
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'cache_results'  => false
		);
		if( !empty($posts = \get_posts($args)) )
		{
			$unlock = $this->createUnlockableFromV2Post($posts[0]); // coupon: percent, fix or free product
			if( empty($unlock) )
			{
				\lws_admin_add_notice(
					'up-lws-sponsored-reward',
					__("Failed update sponsorship setting: migration to new version ignored. Please check them manually.", 'woorewards-pro'),
					array(
						'level' => 'warning',
						'once' => false,
						'forgettable' => true
					)
				);
			}
			else
			{
				// save it
				$dummy = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create('dummy')->last();
				$unlock->id = $posts[0]->ID; // overwrite v2
				$unlock->save($dummy);
				\update_post_meta($unlock->getId(), 'wre_sponsored_reward', 'yes');
			}
		}
	}

	/** Create sponsor rewarding system.
	 *
	 * v2 reward for sponsor in addition to points.
	 * v3 customer do not unlock reward out of a pool,
	 * so create a dedicated pool if required.
	 * (note sponsored is a special case since reward must be created with customer account) */
	protected function addSponsorPool()
	{
		if( !empty(\get_option('lws_wooreward_sponsor_reward_enabled', '')) )
		{
			// sponsor, find the post
				$args = array(
				'numberposts' => 1,
				'post_type' => 'lws-sponsor-reward',
				'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'cache_results'  => false
			);

			if( !empty($posts = \get_posts($args)) )
			{
				$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate();
				$pool = $pools->load(array(
					'numberposts' => 1,
					'meta_query' => array(
						array('key' => 'wre_pool_sponsor', 'value' => 'yes', 'compare' => 'LIKE'),
						array('key' => 'wre_pool_type', 'value' => \LWS\WOOREWARDS\Core\Pool::T_STANDARD, 'compare' => 'LIKE')
					)
				))->last();

				if( empty($pool) )
				{
					// create the pool
					$pool = $pools->create('lws_sponsor_reward')->last();
					$pool->setOptions(array(
						'type'      => \LWS\WOOREWARDS\Core\Pool::T_STANDARD,
						'private'   => true, // enabled but should not be displayed on front
						'title'     => __("Sponsor reward", 'woorewards-pro')
						// that special case has no 'whitelist'
					));
				}

				// add point maker (1 to 1)
				if( $pool->getEvents()->count() == 0 )
					$pool->addEvent(new \LWS\WOOREWARDS\PRO\Events\SponsoredFirstOrder(), 1);

				// add single (auto-unlock) reward (1 to 1)
				if( $pool->getUnlockables()->count() == 0 )
				{
					$unlock = $this->createUnlockableFromV2Post($posts[0]);  // coupon (percent, fix or free product) or title or custom
					if( empty($unlock) )
					{
						\lws_admin_add_notice(
							'up-lws-sponsor-reward',
							sprintf(__("Failed to update sponsorship setting: migration to new version ignored. Please check '%s' reward system.", 'woorewards-pro'), $pool->getOption('title')),
							array(
								'level' => 'warning',
								'once' => false,
								'forgettable' => true
							)
						);
					}
					else
					{
						$unlock->id = $posts[0]->ID; // overwrite v2
						$pool->addUnlockable($unlock, 1);
					}
				}

				$pool->save();
				if( !empty($pool->getId()) )
				{
					\clean_post_cache($pool->getId());
					\update_post_meta($pool->getId(), 'wre_pool_sponsor', 'yes');
				}
			}
		}
	}

	/** If pro was activated in v2 and standard already configured,
	 * translate that reward settings to v3
	 * @return false if settings should not be overwritten by v2 traces. */
	protected function copyStandardV2Rewards()
	{
		if( !isset($this->stdPool) )
		{
			$this->stdPool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->load(array(
				'numberposts' => 1,
				'meta_query'  => array(
					array('key' => 'wre_pool_prefab', 'value' => 'yes', 'compare' => 'LIKE'),
					array('key' => 'wre_pool_type', 'value' => \LWS\WOOREWARDS\Core\Pool::T_STANDARD, 'compare' => 'LIKE')
				)
			))->last();
		}
		if( empty($this->stdPool) )
			return false; // an error should occured in free version updater since default pool is missing

		// Was v2 already pro? Or is it a fresh v3 pro activation?
		$backup = $this->stdPool->getUnlockables()->asArray();
		foreach( $backup as $old )
		{
			$post = \get_post($old->getId());
			if( !empty($post) && $post->post_date != $post->post_modified )
			{
				// user take time to change v3 free version settings
				// we cannot overwrite them even if a v2.pro has been installed once
				return false;
			}
		}

		$error = false;
		$args = array(
			'post_type' => 'lws-reward',
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'cache_results'  => false
		);

		foreach( ($posts = \get_posts($args)) as $post )
		{
			if( !empty($unlock = $this->createUnlockableFromV2Post($post)) )
			{
				$unlock->id = $post->ID; // overwrite v2
				$unlock->save($this->stdPool);
			}
			else
				$error = __("Failed to migrate all Purchase System Rewards. Please check them manually.", 'woorewards-pro');
		}

		if( !empty($posts) && !empty(intval(\get_option('lws_woorewards_free_reward_settings', '0'))) )
		{
			// A v2.pro was already installed, so simple options already converted to post
			foreach( $backup as $old )
				$old->delete(); // remove the v3.free default settings
		}

		if( !empty($error) )
		{
			\lws_admin_add_notice(
				'up-lws-reward',
				$error,
				array(
					'level' => 'warning',
					'once' => false,
					'forgettable' => true
				)
			);
		}

		return true;
	}

	/** If pro was activated in v2 and standard already configured,
	 * translate that reward settings to v3 */
	protected function copyLevellingV2Rewards()
	{
		if( !isset($this->lvlPool) )
			return; // addLevelingPool() should be called first

		$error = false;
		$args = array(
			'post_type' => 'lws-loyalty',
			'post_status' => array('publish', 'private', 'draft', 'pending', 'future'),
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'cache_results'  => false
		);

		foreach( \get_posts($args) as $post )
		{
			if( !empty($unlock = $this->createUnlockableFromV2Post($post)) )
			{
				$unlock->id = $post->ID; // overwrite v2
				$unlock->save($this->lvlPool);
			}
			else
				$error = __("Failed to migrate all Loyalty System Rewards. Please check them manually.", 'woorewards-pro');
		}

		if( !empty($error) )
		{
			\lws_admin_add_notice(
				'up-lws-loyalty',
				$error,
				array(
					'level' => 'warning',
					'once' => false,
					'forgettable' => true
				)
			);
		}
	}

	/** If pro was activated in v2 and levelling already configured,
	 * translate that settings to v3 */
	protected function copyLevellingSettings()
	{
		if( !isset($this->lvlPool) )
			return; // addLevelingPool() should be called first
		$enabled = false;

		// is order amount points
		if( $this->lvlPool->getEvents()->filterByType('lws_woorewards_events_orderamount')->count() == 0 )
		{
			$spend = absint(\get_option('lws_woorewards_loyalty_order_money_spend', 0));
			$pts = absint(\get_option('lws_woorewards_loyalty_order_money_points', 0));
			if( !empty($spend) && !empty($pts) )
			{
				$event = new \LWS\WOOREWARDS\PRO\Events\OrderAmount();
				$event->setDenominator($spend);
				$event->setMultiplier($pts);
				$event->save($this->lvlPool);
				$enabled = true;
			}
		}

		// is order complete points
		if( $this->lvlPool->getEvents()->filterByType('lws_woorewards_events_ordercompleted')->count() == 0 )
		{
			if( !empty(\get_option('lws_woorewards_event_enabled_order', 'on')) )
			{
				$pts = intval(\get_option('lws_woorewards_event_points_order', '0'));
				if( $pts > 0 )
				{
					$event = new \LWS\WOOREWARDS\PRO\Events\OrderCompleted();
					$event->setMultiplier($pts);
					$event->save($this->lvlPool);
					$enabled = true;
				}
			}
		}

		// is product review points
		if( $this->lvlPool->getEvents()->filterByType('lws_woorewards_pro_events_productreview')->count() == 0 && $this->lvlPool->getEvents()->filterByType('lws_woorewards_events_productreview')->count() == 0)
		{
			if( !empty(\get_option('lws_woorewards_event_enabled_review', 'on')) )
			{
				$pts = intval(\get_option('lws_woorewards_event_points_review', '0'));
				if( $pts > 0 )
				{
					$event = new \LWS\WOOREWARDS\Events\ProductReview();
					$event->setMultiplier($pts);
					$event->save($this->lvlPool);
					$enabled = true;
				}
			}
		}

		// is do sponsor points
		if( $this->lvlPool->getEvents()->filterByType('lws_woorewards_pro_events_sponsorship')->count() == 0 )
		{
			if( !empty(\get_option('lws_woorewards_event_enabled_sponsorship', 'on')) )
			{
				$pts = intval(\get_option('lws_woorewards_event_points_sponsorship', '0'));
				if( $pts > 0 )
				{
					$event = new \LWS\WOOREWARDS\PRO\Events\Sponsorship();
					$event->setMultiplier($pts);
					$event->save($this->lvlPool);
					$enabled = true;
				}
			}
		}

		if( $enabled )
		{
			$this->lvlPool->setOption('enabled', true);
			$this->lvlPool->save(false, false);
		}
	}

	/** change instant point total meta_key */
	protected function copyLevellingPoints()
	{
		global $wpdb;
		$tmeta = $wpdb->usermeta;
		$mkey = \LWS\WOOREWARDS\Core\PointStack::MetaPrefix . \LWS\WOOREWARDS\Core\Pool::T_LEVELLING;
		$wpdb->query("UPDATE $tmeta SET meta_key='{$mkey}' WHERE meta_key='lws-loyalty'");
	}

	/** Add a second prefab pool if not exists. */
	protected function addLevelingPool()
	{
		$pools = \LWS\WOOREWARDS\Collections\Pools::instanciate();
		$this->lvlPool = $pools->load(array(
			'numberposts' => 1,
			'meta_query' => array(
				array('key' => 'wre_pool_prefab', 'value' => 'yes', 'compare' => 'LIKE'),
				array('key' => 'wre_pool_type', 'value' => \LWS\WOOREWARDS\Core\Pool::T_LEVELLING, 'compare' => 'LIKE')
			)
		))->last();

		if( empty($this->lvlPool) )
		{
			// create the default pool for free version
			$this->lvlPool = $pools->create(\LWS\WOOREWARDS\Core\Pool::T_LEVELLING)->last();
			$this->lvlPool->setOptions(array(
				'type'      => \LWS\WOOREWARDS\Core\Pool::T_LEVELLING,
				'disabled'  => true,
				'title'     => __("Levelling System", 'woorewards-pro'),
				'whitelist' => array(\LWS\WOOREWARDS\Core\Pool::T_LEVELLING)
			));

			$this->lvlPool->save();
			if( !empty($this->lvlPool->getId()) ) // not deletable
			{
				\clean_post_cache($this->lvlPool->getId());
				\update_post_meta($this->lvlPool->getId(), 'wre_pool_prefab', 'yes');
			}
		}
	}

	/// dbDelta could write on standard output @see releaseLog()
	protected function grabLog()
	{
		ob_start(function($msg){
			if( !empty($msg) )
				error_log($msg);
		});
	}

	/// @see grabLog()
	protected function releaseLog()
	{
		ob_end_flush();
	}

	/** v2 invented a 'discount_type' for free product instead using relevant configuration.
	 * That function transforms that old coupons to a more WooCommerce compliant setting.
	 * Make that kind of coupon easier to manage (avoid a lot of admin hook) */
	protected function refactorFreeProductCoupons()
	{
		global $wpdb;

		// coupon_amount should be missing
		$metas = array(
			'coupon_amount' => '100',
		);
		foreach( $metas as $key => $value )
		{
			$wpdb->query(<<<EOT
INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
SELECT post_id, '$key', '$value' FROM {$wpdb->postmeta}
WHERE meta_key='woorewards_freeproduct' AND meta_value='yes'
EOT
			);
		}

		/** restriction should be wrong and type was a invented one */
		$metas = array_merge($metas, array(
			'limit_usage_to_x_items' => '1',
			'discount_type' => 'percent'
		));
		foreach( $metas as $key => $value )
		{
			$wpdb->query(<<<EOT
REPLACE INTO {$wpdb->postmeta} (meta_id, post_id, meta_key, meta_value)
SELECT o.meta_id, o.post_id, o.meta_key, '$value' FROM {$wpdb->postmeta} as o
INNER JOIN {$wpdb->postmeta} as v ON o.post_id=v.post_id AND v.meta_key='woorewards_freeproduct' AND v.meta_value='yes'
WHERE o.meta_key='$key'
EOT
			);
		}

		/** dedicated meta for product restriction was not used */
		$wpdb->query(<<<EOT
REPLACE INTO {$wpdb->postmeta} (meta_id, post_id, meta_key, meta_value)
SELECT o.meta_id, o.post_id, o.meta_key, p.meta_value FROM {$wpdb->postmeta} as o
INNER JOIN {$wpdb->postmeta} as p ON o.post_id=p.post_id AND p.meta_key='woorewards_product_id'
INNER JOIN {$wpdb->postmeta} as v ON o.post_id=v.post_id AND v.meta_key='woorewards_freeproduct' AND v.meta_value='yes'
WHERE o.meta_key='product_ids'
EOT
		);
	}

	protected function to4_2_10()
	{
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$tinyUrls = <<<EOT
CREATE TABLE `{$wpdb->base_prefix}lws_wr_tinyurls` (
	`shorturl` VARCHAR(256) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
	`longurl` TEXT NOT NULL,
	`longref` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
	PRIMARY KEY `shorturl`  (`shorturl`),
	KEY `longref` (`longref`)
) {$charset_collate};
EOT;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$this->grabLog();
		dbDelta($tinyUrls);
		$this->releaseLog();
	}
}
