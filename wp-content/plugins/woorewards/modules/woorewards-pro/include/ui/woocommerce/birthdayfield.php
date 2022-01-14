<?php
namespace LWS\WOOREWARDS\PRO\Ui;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Add a user birthday field. */
class BirthdayField
{
	static function register()
	{
		$registration = \get_option('lws_woorewards_registration_birthday_field');
		$detail       = \get_option('lws_woorewards_myaccount_detail_birthday_field');
		$myaccount    = \get_option('lws_woorewards_myaccount_register_birthday_field');

		if( $registration || $detail || $myaccount )
		{
			$me = new self();

			if( $registration )
			{
				\add_filter('woocommerce_checkout_fields', array($me, 'checkout'));
			}
			if( $detail )
			{
				\add_action('woocommerce_edit_account_form', array($me, 'myaccountDetailForm'));
				\add_action('woocommerce_save_account_details', array($me, 'myaccountDetailSave'));
			}
			if( $myaccount )
			{
				\add_action('woocommerce_register_form', array($me, 'myaccountRegisterForm'));
				\add_filter('woocommerce_process_registration_errors', array($me, 'myaccountRegisterValidation'), 10, 4);
				\add_action('woocommerce_created_customer', array($me, 'myaccountRegisterSave'), 10, 1);
			}

			\add_action('show_user_profile', array($me, 'showProfileBirthday'));
			\add_action('edit_user_profile', array($me, 'showProfileBirthday'));
			\add_action('personal_options_update', array($me, 'saveProfileBirthday'));
			\add_action('edit_user_profile_update', array($me, 'saveProfileBirthday'));
		}
	}

	protected function getDefaultBirthdayMetaKey()
	{
		return 'billing_birth_date';
	}

	function checkout($fields)
	{
		$fields['account'][$this->getDefaultBirthdayMetaKey()] = array(
			'type'        => 'date',
			'label'       => __("Birthday", 'woorewards-pro'),
			'required'    => false
		);
		return $fields;
	}

	function myaccountRegisterForm()
	{
		$field = $this->getDefaultBirthdayMetaKey();
		$label = __("Birthday", 'woorewards-pro');

		echo "<p class='woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide'>";
		echo "<label for='{$field}'>$label</label>";
		echo "<input type='date' class='woocommerce-Input woocommerce-Input--text input-text' name='{$field}' id='{$field}' />";
		echo "</p>";
	}

	function myaccountRegisterValidation($validation_error, $username, $password, $email)
	{
		$birthday = $this->grabBirthdayFromPost();
		if( false === $birthday ){
			$field = $this->getDefaultBirthdayMetaKey();
			$validation_error->add($field, __("Invalid date format for birthday", 'woorewards-pro'), 'birthday');
		}
		return $validation_error;
	}

	function myaccountRegisterSave($userId)
	{
		$birthday = $this->grabBirthdayFromPost();
		\update_user_meta($userId, $this->getDefaultBirthdayMetaKey(), $birthday);
	}

	function myaccountDetailForm()
	{
		$userId = \get_current_user_id();
		$field = $this->getDefaultBirthdayMetaKey();
		$label = __("Birthday", 'woorewards-pro');
		$value = \esc_attr(\get_user_meta($userId, $field, true));

		echo "<p class='woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide'>";
		echo "<label for='{$field}'>{$label}</label>";
		echo "<input type='date' class='woocommerce-Input woocommerce-Input--text input-text' name='{$field}' id='{$field}' value='{$value}' />";
		echo "</p><div class='clear'></div>";
	}

	function myaccountDetailSave($userId)
	{
		$birthday = $this->grabBirthdayFromPost();
		if( $birthday !== false )
			\update_user_meta($userId, $this->getDefaultBirthdayMetaKey(), $birthday);
		else
			\wc_add_notice(__("Invalid date format for birthday", 'woorewards-pro'), 'error');
	}

	function grabBirthdayFromPost()
	{
		$field = $this->getDefaultBirthdayMetaKey();
		$birthday = !empty($_POST[$field]) ? \wc_clean($_POST[$field]): '';
		if( !empty($birthday) )
		{
			if( empty(\date_create($birthday)) )
			{
				\wc_add_notice(__("Invalid date format for birthday", 'woorewards-pro'), 'error');
				$birthday = false;
			}
		}
		return $birthday;
	}

	function showProfileBirthday($user)
	{
		$field = $this->getDefaultBirthdayMetaKey();
		$label = __("Birthday", 'woorewards-pro');
		$value = \esc_attr(\get_user_meta($user->ID, $field, true));
		echo <<<EOT
<table class="form-table">
	<tr>
		<th><label for='{$field}'>{$label}</label></th>
		<td><input type='date' name='{$field}' id='{$field}' value='{$value}' /></td>
	</tr>
</table>
EOT;

		if ($value && \get_option('lws_woorewards_myaccount_birthday_debug', 'on')) {
			$dates = array();
			foreach (\LWS_WooRewards_Pro::getLoadedPools()->asArray() as $pool) {
				$events = $pool->getEvents()->filterByType('lws_woorewards_pro_events_birthday')->asArray();

				if ($events) {
					$valid = ($pool->isActive() && $pool->userCan($user));
					$name = $pool->getOption('title');
					foreach ($events as $e) {
						if ($d = $e->getDatesForUser($user)) {
							$d->valid = ($valid && $e->getMultiplier()); // event not installed if no point gain set
							$d->name = $name;
							if (count($events) > 1)
								$d->name .= (' - ' . $e->getTitle());
							$dates[] = $d;
						}
					}
				}
			}

			if ($dates)
			{
				\usort($dates, function($a, $b){ // valid first, next by date ASC
					if ($a->valid != $b->valid)
						return ($a->valid ? -1 : 1);
					if ($a == $b)
						return 0;
					return ($a < $b ? -1 : 1);
				});
				$label = __("Next Birthday point earning", 'woorewards-pro');
				$help  = __("Dates below are estimates only, triggers can vary depending on CRON settings. In addition, point earning could be prevented by changes in User Status or Loyalty System settings.", 'woorewards-pro');

				echo "<table class='form-table'><tr><th><label><i>{$label}</i></label></th><td><div><small>$help</small></div><table>";
				foreach ($dates as $d) {
					echo "<tr><th>{$d->name}</th>";
					$n = $d->next->format('Y-m-d');
					if (!$d->valid)
						$n = sprintf('<del title="%s" style="cursor:not-allowed;">%s</del>', \esc_attr(__('User has no access to this Loyalty System.', 'woorewards-pro')), $n);
					echo sprintf('<td>%s</td><td>%s</td>', _x('Next :', 'birthday point earning', 'woorewards-pro'), $n);

					echo sprintf(
						'<td title="%s">%s</td><td>%s</td>',
						$d->min->format('Y-m-d'),
						_x('Last :', 'birthday point earning', 'woorewards-pro'),
						$d->last ? $d->last->format('Y-m-d') : '—'
					);
					echo '</tr>';
				}
				echo "</table></td></tr></table>";
			}
		}
	}

	function saveProfileBirthday($userId)
	{
		if ( !current_user_can( 'edit_user', $userId ) ) {
			return false;
		}
		$field = $this->getDefaultBirthdayMetaKey();
		$date = \sanitize_text_field($_POST[$field]);
		\update_user_meta( $userId, $field, $date);
	}

}
