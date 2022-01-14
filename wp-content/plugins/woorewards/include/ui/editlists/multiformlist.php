<?php
namespace LWS\WOOREWARDS\Ui\Editlists;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** manage form with several screens */
abstract class MultiFormList extends \LWS\Adminpanel\EditList\Source
{
	const ROW_ID = 'post_id';

	/** @return array of key => category. category is an array with [label, color, icon]
	 *	for key @see Event::getCategories() */
	abstract protected function getGroups();
	/// @return an array of Event|Unlockable instances
	abstract protected function loadChoices();
	/** @return an array with step information
	 * icon : step icon
	 * title : step title */
	abstract protected function getStepInfo();

	function __construct(\LWS\WOOREWARDS\Core\Pool $pool=null)
	{
		$this->pool = $pool;
	}

	public function defaultValues()
	{
		$values = array();
		foreach( $this->loadChoices()->asArray() as $choice )
			$values = array_merge($values, $choice->getData());

		return array_merge($values, array(
			self::ROW_ID => '', // it is important that id is reset and first for javascript purpose
			'wre_type'   => ''
		));
	}

	protected function getHiddenInputs()
	{
		$rowId = static::ROW_ID;
		return "<input type='hidden' name='{$rowId}' class='lws_woorewards_system_id' />";
	}

	/** radio-grid */
	protected function optionGroups()
	{
		$groups = $this->getGroups();
		if( !isset($groups['']) )
			$groups[''] = array('label'=>'', 'color' => false, 'icon' => false);
		foreach( $groups as &$group )
		{
			$group['items'] = array();
			if( !$group['color'] ) $group['color'] = 'darkgray';
			if( !$group['icon'] )  $group['icon']  = 'lws-icon-show-more';
			if( !isset($group['descr']) ) $group['descr'] = '';
		}

		foreach( $this->loadChoices()->asArray() as $choice )
		{
			$sort = '';
			foreach( $choice->getCategories() as $cat => $name )
			{
				if( isset($groups[$cat]) )
				{
					$sort = $cat;
					break;
				}
			}
			$type = \esc_attr($choice->getType());
			$info = $choice->getInformation();
			if( !$info['color'] ) $info['color'] = $groups[$sort]['color'];
			if( !$info['icon'] )  $info['icon']  = $groups[$sort]['icon'];
			$groups[$sort]['items'][$type] = $info;
		}

		return $groups;
	}

	protected function groupsToRadioGrid($groups)
	{
		$html = '';
		foreach( $groups as $key => $group )
		{
			if( !$group['items'] ) continue;
			// Add group selector as subgrid if needed
			$html .= "<div class='lws_editlist_popup_group_fold lws-radiogrid-group-fold' style='--radiogrid-group-color:{$group['color']};'><div class='fold-icon {$group['icon']}'></div><div class='group-label'><b>{$group['label']}</b> - {$group['descr']}</div><div class='group-fold lws-icon-minus'></div></div>";
			$html .= "<div class='lws_woorewards_system_type_choices radiogrid'>";
			foreach( $group['items'] as $type => $info )
			{
				$info['group'] = $group['label'];
				$data = \base64_encode(\json_encode($info));
				$html .= <<<EOT
<div class='item lws_wre_system_selector_item radiogrid-item' value='{$type}' style='--radiogrid-item-color:{$info['color']};' data-info='{$data}' tabindex='0'>
	<div class='icon lws-icons {$info['icon']}'></div>
	<div class='label'>{$info['label']}</div>
</div>
EOT;
			}
			$html .= "</div>";
		}
		return $html;
	}

	/** no edition, use bulk action */
	function input()
	{
		\wp_enqueue_script('lws_wre_system_selector');
		\wp_enqueue_style('lws_wre_system_selector');

		$divs = array();
		foreach( $this->loadChoices()->asArray() as $choice )
		{
			if( null != $this->pool )
				$choice->setPool($this->pool);
			$type = \esc_attr($choice->getType());
			$divs[] = "<div data-type='$type' class='lws-wr-choice-content lws_woorewards_system_choice editlist-content-grid $type'>"
				. $choice->getForm('editlist')
				. "</div>";
		}

		$opts = $this->groupsToRadioGrid($this->optionGroups());
		$divs = implode("\n\t", $divs);
		$hiddens = $this->getHiddenInputs();
		$stepsInfo = $this->getStepInfo();
		$idleInfo = array(
			'icon'  => 'lws-icon-bulb',
			'label' => __('Information', 'woorewards-lite'),
			'short' => __('Select an element below to see more information about it', 'woorewards-lite'),
			'help'  => __('You will have some extra information displayed if need be.', 'woorewards-lite'),
		);
		$idleData = \base64_encode(\json_encode($idleInfo));

		return <<<EOT
<div class='lws-woorewards-system-edit lws_woorewards_system_master'>
	{$hiddens}
	<div class='lws_woorewards_system_type_select lws-editlist-opt-input multiform-item'>
		<input class='lws_woorewards_system_type' name='wre_type' type='hidden'>
		<div class='step-title'>
			<div class="icon lws-icons {$stepsInfo[0]['icon']}"></div>
			<div class="title">{$stepsInfo[0]['title']}</div>
		</div>
		<div class='lws_wre_system_selected_item_info selected-info' data-info='{$idleData}'>
			<div class='icon lws-icons {$idleInfo['icon']}'></div>
			<div class='description'>
				<div class='desc-title'> {$idleInfo['label']}</div>
				<div class='desc'> {$idleInfo['short']}</div>
				<div class='extra'> {$idleInfo['help']}</div>
			</div>
		</div>
		<div class='radiogrid-wrapper'>{$opts}</div>
	</div>
	<div class='lws-woorewards-system-screens lws_woorewards_system_screens multiform-item'>
		<div class='screens'>
			<div class='step-title'>
				<div class="icon lws-icons {$stepsInfo[1]['icon']}"></div>
				<div class="title">{$stepsInfo[1]['title']}</div>
			</div>
			{$divs}
		</div>
	</div>
</div>
EOT;
	}
}
