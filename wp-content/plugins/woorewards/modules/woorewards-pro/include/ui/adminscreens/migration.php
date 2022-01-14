<?php
namespace LWS\WOOREWARDS\PRO\Ui\AdminScreens;
// don't call the file directly
if (!defined('ABSPATH')) exit();

class Migration
{
	static function mergeGroups(&$groups)
	{
		$groups = array_merge($groups, self::getGroups());
	}

	static function getGroups()
	{
		require_once LWS_WOOREWARDS_PRO_INCLUDES . '/migration/exportmethods.php';
		$exports = new \LWS\WOOREWARDS\PRO\Migration\ExportMethods();

		$groups = array(
			'export_wr' => array(
				'id' => 'wr_export_points_wr',
				'icon'	 => 'lws-icon-migration',
				'class'	=> 'half',
				'title' => __("Export Points from MyRewards", 'woorewards-pro'),
				'text' => __("Select a points and rewards system to export the users points from that system.", 'woorewards-pro'),
				'fields' => array(
					'pool' => array(
						'id'    => 'woorewards-pro' . '_from_pool',
						'type'  => 'lacselect',
						'title' => __("points and rewards system", 'woorewards-pro'),
						'extra' => array(
							'class'    => 'lws-ignore-confirm',
							'maxwidth' => '400px',
							'gizmo'    => true,
							'ajax'     => 'lws_woorewards_pool_list',
						)
					),
					'export' => array(
						'id'    => 'export-wr',
						'type'  => 'button',
						'title' => __("Export", 'woorewards-pro'),
						'extra' => array(
							'link' => array('ajax' => 'woorewards-pro' . '-export-wr'),
						)
					),
				)
			),
			'export_other' => array(
				'id' 	=> 'wr_export_points_other',
				'icon'	=> 'lws-icon-migration',
				'class'	=> 'half',
				'title' => __("Export from other plugins", 'woorewards-pro'),
				'text'	=> sprintf(
					'%s<br/><strong>%s</strong>',
					__("If you're migrating from another loyalty plugin, you can export the users points from the other plugin and import them into MyRewards.", 'woorewards-pro'),
					__("The other plugin needs to be installed and active for this procedure to work.", 'woorewards-pro')
				),
				'fields' => array(
					'meta' => array(
						'id'    => 'woorewards-pro' . '_from_meta',
						'type'  => 'lacselect',
						'title' => __("Loyalty Plugin or Meta Key", 'woorewards-pro'),
						'extra' => array(
							'class' => 'lws-ignore-confirm',
							'value' => '—',
							'maxwidth' => '400px',
							'allownew' => 'on',
							'source' => $exports->getMethods(),
							'gizmo' => true,
							'tooltips' => __("Do not change that value if you are not sure about what you are doing.", 'woorewards-pro'),
						)
					),
					'arg' => array(
						'id'    => 'woorewards-pro' . '_with_arg',
						'type'  => 'lacselect',
						'title' => __("Some Plugin need extra arguments", 'woorewards-pro'),
						'extra' => array(
							'class' => 'lws-ignore-confirm',
							'value' => '—',
							'allownew' => 'on',
							'maxwidth' => '400px',
							'source' => $exports->getArguments(),
							'gizmo' => true,
							'tooltips' => __("Main purpose is for plugins that support several point pools. If the plugin is not listed here, it does not need an extra argument.", 'woorewards-pro'),
						)
					),
					'export' => array(
						'id'    => 'export-points',
						'type'  => 'button',
						'title' => __("Export", 'woorewards-pro'),
						'extra' => array(
							'link' => array('ajax' => 'woorewards-pro' . '-export-points'),
						)
					),
				)
			),
			'import' => array(
				'id' => 'wr_import_points',
				'icon'	=> 'lws-icon-cloud-download-93',
				'title' => __("Import Points", 'woorewards-pro'),
				'text'  => implode('<br/>', array(
					__("Select the exported file, then click on «Import».", 'woorewards-pro'),
					__("The Import process does <b>not</b> generate any reward.", 'woorewards-pro'),
				)),
				'fields' => array(
					'round' => array(
						'id'    => 'woorewards-pro' . '_rounding',
						'type'  => 'lacselect',
						'title' => __("Round imported points", 'woorewards-pro'),
						'extra' => array(
							'default' => 'floor',
							'maxwidth' => '400px',
							'mode'	=> 'select',
							'tooltips' => __("MyRewards only support integer points", 'woorewards-pro'),
							'source' => array(
								array('value' => 'floor', 'label' => __("Round fractions down", 'woorewards-pro')),
								array('value' => 'ceil',  'label' => __("Round fractions up", 'woorewards-pro')),
								array('value' => 'half_up', 'label' => __("Round to nearest integer, half way round up", 'woorewards-pro')),
								array('value' => 'half_down', 'label' => __("Round to nearest integer, half way round down", 'woorewards-pro')),
							)
						)
					),
					'multiply' => array(
						'id'    => 'woorewards-pro' . '_multiply',
						'type'  => 'text',
						'title' => __("Multiply imported points by", 'woorewards-pro'),
						'extra' => array(
							'default' => '1',
							'placeholder' => '1',
						)
					),
					'behavior' => array(
						'id'    => 'woorewards-pro' . '_behavior',
						'type'  => 'lacselect',
						'title' => __("Import Mode", 'woorewards-pro'),
						'extra' => array(
							'default' => 'replace',
							'maxwidth' => '400px',
							'mode'	=> 'select',
							'source' => array(
								array('value' => 'replace', 'label' => __("Replace customers points", 'woorewards-pro')),
								array('value' => 'add', 'label' => __("Add points to customers totals", 'woorewards-pro')),
							),
						)
					),
					'default' => array(
						'id'    => 'woorewards-pro' . '_default_pool',
						'type'  => 'lacselect',
						'title' => __("Add points to that points and rewards system", 'woorewards-pro'),
						'extra' => array(
							'maxwidth' => '400px',
							'gizmo'    => true,
							'ajax'     => 'lws_woorewards_pool_list',
						)
					),
					'file' => array(
						'id'    => 'woorewards-pro' . '_import_file',
						'type'  => 'input',
						'extra' => array(
							'value' => '',
							'placeholder' => '*.json',
							'type' => 'file',
						)
					),
					'import' => array(
						'id'    => 'import-points',
						'type'  => 'custom',
						'title' => '',
						'extra' => array(
							'gizmo'   => true,
							'content' => sprintf(
								'<button type="submit" name="lws_wre_points_action" value="import" class="lws-adm-btn">%s</button>',
								__("Import", 'woorewards-pro')
							)
						)
					),
				)
			),
		);
		return $groups;
	}
}
