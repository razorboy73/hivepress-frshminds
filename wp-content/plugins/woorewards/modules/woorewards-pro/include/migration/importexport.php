<?php
namespace LWS\WOOREWARDS\PRO\Migration;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage Import/Export final process.
 *	Read submitted json for import
 *	and output json from ajax export requests.  */
class ImportExport
{
	static function register()
	{
		$me = new self();
		\add_action('wp_ajax_'.'woorewards-pro'.'-export-wr', array($me, 'exportWR'));
		\add_action('wp_ajax_'.'woorewards-pro'.'-export-points', array($me, 'exportPoints'));

		\add_filter('pre_update_option_'.'woorewards-pro'.'_import_file', array($me, 'import'), 9, 3);
		\add_filter('lws_adminpanel_form_attributes'.LWS_WOOREWARDS_PAGE.'.system', array($me, 'importFormAttributes'));
		\add_filter('pre_set_transient_settings_errors', array($me, 'importResult'), 999);
	}

	function importResult($value)
	{
		if( isset($this->importError) )
		{
			\lws_admin_delete_notice('lws_ap_page');
			\lws_admin_add_notice_once('woorewards-pro'.'-error', __("Import Error", 'woorewards-pro').'<br/>'.$this->importError, array('level'=>'error'));
		}
		return $value;
	}

	function importFormAttributes($attrs)
	{
		$attrs['enctype']='multipart/form-data';
		return $attrs;
	}

	/**	@return $oldValue cause WP dont go further with that option. */
	function import($value, $oldValue, $option)
	{
		if( !(isset($_POST['lws_wre_points_action']) && 'import' == $_POST['lws_wre_points_action']) )
			return $oldValue; // we only want a import button click, not a page save

		if( !\current_user_can('manage_options') )
		{
			$this->importError = __("You are not allowed to do that", 'woorewards-pro');
			return $oldValue;
		}
		$stack = isset($_REQUEST['woorewards-pro' . '_default_pool']) ? \sanitize_key($_REQUEST['woorewards-pro' . '_default_pool']) : false;
		if( $stack )
		{
			$pool = \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($stack, false);
			if( $pool )
				$stack = $pool->getStackId();
		}
		else
		{
			$this->importError = __("Missing destination loyalty system.", 'woorewards-pro');
			return $oldValue;
		}

		$replace = true;
		if (isset($_POST['woorewards-pro' . '_behavior']) && 'add' == $_POST['woorewards-pro' . '_behavior'])
			$replace = false;

		$key = 'woorewards-pro' . '_import_file';
		if( isset($_FILES[$key]) && !empty($_FILES[$key]) && !empty($_FILES[$key]['tmp_name']) )
		{
			$filename = $_FILES[$key]['name'];
			if( !empty($_FILES[$key]['error']) )
			{
				$this->importError = sprintf(__("Error during upload of file %s, perhaps the file is too big. You can try to split it up or increase max allowed file size on your server.", 'woorewards-pro'), $filename);
			}
			else
			{
				if( $_FILES[$key]['type'] != 'application/json' )
				{
					$this->importError = __("Expects a JSON file.", 'woorewards-pro');
				}
				else try
				{
					$json = @json_decode(@file_get_contents($_FILES[$key]['tmp_name']), true);
					$this->importJSON($json, $stack, $replace);
				}
				catch(Exception $e)
				{
					$this->importError = __("The file cannot be read or format is invalid. Expects JSON content.", 'woorewards-pro');
				}
				unlink($_FILES[$key]['tmp_name']);
			}
		}
		else
			$this->importError = __("Please, select a file to import.", 'woorewards-pro');
		return $oldValue;
	}

	/** @param $replace (bool) if false, points are added. */
	protected function importJSON($json, $stack, $replace=true)
	{
		$affected = 0;
		$unknown = array();
		$ignored = array();
		global $wpdb;
		$table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}lws_wr_historic'");
		set_time_limit(0);
		$reason = _x("Import", "History line", 'woorewards-pro');
		$metakey = 'lws_wre_points_'.$stack;

		$multiply = floatval(str_replace(',', '.', \get_option('woorewards-pro'.'_multiply', 1)));
		if( !$multiply )
			$multiply = 1;
		$round = \get_option('woorewards-pro'.'_rounding', 'floor');

		foreach( $json as $row )
		{
			if( isset($row['email']) && isset($row['points']) )
			{
				$email = \trim($row['email']);
				if (!$email)
					continue;

				$points = floatval($row['points']) * $multiply;
				if( 'floor' == $round )
					$points = floor($points);
				else if( 'ceil' == $round )
					$points = ceil($points);
				else if( 'half_up' == $round )
					$points = round($points, 0, PHP_ROUND_HALF_UP);
				else if( 'half_down' == $round )
					$points = round($points, 0, PHP_ROUND_HALF_DOWN);

				if( $user = \get_user_by('email', $email) )
				{
					$oldPts = 0;
					if ($replace) {
						\update_user_meta($user->ID, $metakey, $points);
					} else {
						$oldPts = \intval(\get_user_meta($user->ID, $metakey, true));
						\update_user_meta($user->ID, $metakey, $points + $oldPts);
					}
					++$affected;
					if( $table )
					{
						$values = array(
							'user_id'   => $user->ID,
							'stack'     => $stack,
							'new_total' => $points,
							'commentar' => $reason,
							'origin'    => 'migration-tool',
						);
						$formats = array('%d', '%s', '%d', '%s', '%s');
						if (!$replace) {
							$values['new_total'] += $oldPts;
							$values['points_moved'] = $points;
							$formats[] = '%d';
						}
						$wpdb->insert($table, $values, $formats);
					}
				}
				else
					$unknown[$email] = $email;
			}
			else
			{
				$this->importError = __("Invalid data: ", 'woorewards-pro') . htmlentities(json_encode($row));
				return false;
			}
		}

		\lws_admin_add_notice_once('woorewards-pro'.'-notice', sprintf(__("Import done. %d items affected.", 'woorewards-pro'), $affected), array('level'=>'success'));
		if( !empty($unknown) )
		{
			$warning = __("The following users cannot be found. Points ignored.", 'woorewards-pro');
			$unknown = htmlentities(implode("\n", $unknown));
			$warning .= "<textarea>$unknown</textarea>";
			\lws_admin_add_notice_once('woorewards-pro'.'-warning', $warning, array('level'=>'warning'));
		}
		if( !empty($ignored) )
		{
			$warning = __("The following point pools cannot be found. Points ignored.", 'woorewards-pro');
			$ignored = htmlentities(implode("\n", $ignored));
			$warning .= "<textarea>$ignored</textarea>";
			\lws_admin_add_notice_once('woorewards-pro'.'-warning2', $warning, array('level'=>'warning'));
		}
		return true;
	}

	function exportWR()
	{
		if( !\current_user_can('manage_options') )
			\wp_die('forbidden', 403);

		$poolKey = 'woorewards-pro' . '_from_pool';
		$poolName = isset($_REQUEST[$poolKey]) ? trim($_REQUEST[$poolKey]) : false;
		if( $poolName )
		{
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/migration/exportmethod.php';
			require_once LWS_WOOREWARDS_PRO_INCLUDES . '/migration/methods/woorewards.php';
			$method = new \LWS\WOOREWARDS\PRO\Migration\Methods\WooRewards();
			$this->sendJSONPoints($method, $poolName, $poolName);
		}
		\wp_die('Bad request', 400);
	}

	function exportPoints()
	{
		if( !\current_user_can('manage_options') )
			\wp_die('forbidden', 403);

		$metaPost = 'woorewards-pro' . '_from_meta';
		$metaKey = isset($_REQUEST[$metaPost]) ? trim($_REQUEST[$metaPost]) : false;
		$argPost = 'woorewards-pro' . '_with_arg';
		$argValue = isset($_REQUEST[$argPost]) ? trim($_REQUEST[$argPost]) : false;

		if( !$metaKey || trim($metaKey) == '—' )
			\wp_die('Bad request', 400);
		if( !$argValue || trim($argValue) == '—' )
			$argValue = false;

		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/migration/exportmethods.php';
		$method = \LWS\WOOREWARDS\PRO\Migration\ExportMethods::get($metaKey);
		if( !$method )
			\wp_die('Not Implemented', 501);

		if( !$method->instance->supportFreeArgs() && ($args = $method->instance->getArgs()) )
		{
			if( !in_array($argValue, array_keys($args)) )
				\wp_die('Invalid Export argument for '.$method->instance->getTitle(), 418);
		}

		$this->sendJSONPoints($method->instance, $metaKey, $argValue);
	}

	private function sendJSONPoints($method, $value, $arg=false)
	{
		$json = $method->export($value, $arg);

		$base = str_replace(' ', '-', \get_bloginfo('name'));
		$origin = \remove_accents(strtolower($method->getTitle()));
		$origin = preg_replace(array('/\s*-+\s*/', '/[\s_]+/'), array('-', '_'), $origin);
		$origin = \sanitize_key($origin);
		$date = \date('Ymd');
		$arg = \sanitize_key($arg);
		$filename = \esc_attr("{$base}-{$origin}-{$arg}-{$date}.json");
		header("Content-disposition: attachment; filename=\"{$filename}\""); // force download
		\wp_send_json($json);
	}

}