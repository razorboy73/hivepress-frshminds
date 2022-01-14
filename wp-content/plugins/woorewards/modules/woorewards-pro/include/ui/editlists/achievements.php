<?php
namespace LWS\WOOREWARDS\PRO\Ui\Editlists;
// don't call the file directly
if (!defined('ABSPATH')) exit();

/** List all special pools. */
class Achievements extends \LWS\WOOREWARDS\Ui\Editlists\MultiFormList
{
	const SLUG = 'lws-wr-achievements';

	public function total()
	{
		return $this->getCollection()->count();
	}

	public function read($limit=null)
	{
		$pools = array();
		$collection = $this->getCollection()->asArray();
		if( $limit && $limit->valid() )
			$collection = \array_slice($collection, $limit->offset, $limit->count);

		foreach( $collection as $pool )
		{
			$pools[] = $this->objectToArray($pool);
		}
		return $pools;
	}

	protected function getStepInfo()
	{
		if (empty($this->stepInfo)) {
			$this->stepInfo = array(
				array(
					"icon" => "lws-icon-questionnaire",
					"title" => __("Action to perform to advance in the achievement", 'woorewards-pro'),
				),
				array(
					"icon" => "lws-icon-setup-preferences",
					"title" => __("Points value and options", 'woorewards-pro'),
				)
			);
		}
		return $this->stepInfo;
	}

	public function labels()
	{
		$labels = array(
			'image' => array(__("Badge", 'woorewards-pro'), 'max-content'),
			'display_title' => __("System Title", 'woorewards-pro'),
			'cost' => array(__("Occurence", 'woorewards-pro'), 'max-content'),
			'action_descr' => __("Action", 'woorewards-pro'),
		);
		return \apply_filters('lws_woorewards_achiavements_labels', $labels);
	}

	private function objectToArray($pool)
	{
		$data = $pool->getOptions(array(
			'title', 'display_title'
		));

		$data['src_id'] = $data[self::ROW_ID] = $pool->getId();
		$data['image'] = $pool->getThumbnailImage();
		$data['achievement_badge'] = '';
		$data['cost'] = 1;
		$data['roles'] = $pool->getOption('roles');
		$data['wre_type'] = '';
		$data['action_descr'] = '';

		if ($badge = $pool->getTheReward()) {
			$data['achievement_badge'] = $badge->getBadgeId();
			$data['cost'] = $badge->getCost('edit');
		}
		$data['image'] = sprintf('<div title="%d">%s</div>', $data['achievement_badge'], $data['image']);
		$data['display_title'] = sprintf('<span title="%d" data-id="%d">%s</span>', $data['achievement_badge'], $data['src_id'], $data['display_title']);

		if ($event = $pool->getEvents()->first()) {
			$data['wre_type'] = $event->getType();
			$data['action_descr'] = $event->getDescription();
			$data = array_merge($event->getData(), $data);
		}

		return $data;
	}

	public function defaultValues()
	{
		$values = parent::defaultValues();
		$values['src_id']            = '';
		$values['achievement_badge'] = '';
		$values['cost']              = 1;
		$values['roles']             = '';
		return $values;
	}

	/** no edition, use bulk action */
	public function input()
	{
		$labelCreate = \esc_attr(__("Create", 'woorewards-pro'));
		$labelSave = \esc_attr(__("Save", 'woorewards-pro'));
		$labelCopy = \esc_attr(_x(" (copy)", "title suffix at pool copy", 'woorewards-pro'));
		$rowId = self::ROW_ID;

		$labels = array(
			'stitle' => __("Achievement settings", 'woorewards-pro'),
			'atitle' => __("Action settings", 'woorewards-pro'),
			'title'  => __("Title", 'woorewards-pro'),
			'badge'  => __("Reward", 'woorewards-pro'),
			'cost'   => __("Action occurences", 'woorewards-pro'),
			'action' => __("Action", 'woorewards-pro'),
			'roles'  => __("Allowed roles", 'woorewards-pro'),
		);
		$placeholders = array(
			'badge' => __("Choose a badge ...", 'woorewards-pro'),
			'title' => __("Title (Optional)", 'woorewards-pro'),
			'action' => __("Action to perform", 'woorewards-pro'),
		);
		$tooltips = array(
			'cost'   => __("The number of time the user must perform the chosen action.", 'woorewards-pro'),
			'roles'  => __("Only users with the selected roles can get that achievement. Leave empty if all users are authorized to do it.", 'woorewards-pro'),
		);

		$badgeInput = '';
		if( \LWS\WOOREWARDS\PRO\Core\Badge::countInDB() )
		{
			$badgeInput = \LWS\Adminpanel\Pages\Field\LacSelect::compose('achievement_badge', array(
				'ajax'     => 'lws_woorewards_badge_list',
				'value'    => '',
				'class'    => 'achievement_badge_field',
				//'maxwidth' => '100%',
			));
		}
		else
		{
			$badgeInput = __("Please, create a badge first.", 'woorewards-pro');
			$editurl = \esc_attr(\add_query_arg(array('post_type'=>'lws_badge'), \admin_url('edit.php')));
			$badgeInput = "<div class='warning lws-icon-warning'><a href='{$editurl}' target='_blank'>{$badgeInput}</a></div>";
		}

		$roles = \LWS\Adminpanel\Pages\Field\LacChecklist::compose('roles', array(
			'ajax'     => 'lws_adminpanel_get_roles',
			'value'    => ''
		));

		$eventDivs = parent::input();

		$str = <<<EOT
<input type='hidden' class='lws_wre_pool_save_label create' value='{$labelCreate}'>
<input type='hidden' class='lws_wre_pool_save_label save' value='{$labelSave}'>
<input type='hidden' class='lws_wre_pool_copy_label' value='{$labelCopy}'>
<input type='hidden' name='{$rowId}' class='lws_woorewards_achievement_id' />
<input type='hidden' name='src_id' class='lws_woorewards_achievement_duplic' />

<div class='lws-achievement-main-settings editlist-content-grid'>

	<div class='fieldset'>
		<div class='title'>{$labels['stitle']}</div>
		<div class='fieldset-grid'>
			<div class='lws-editlist-opt-input label'>{$labels['title']}</div>
			<div class='lws-editlist-opt-input value'><input type='text' name='title' placeholder='{$placeholders['title']}' class='lws_woorewards_pool_title' /></div>
			<div class='lws-editlist-opt-input label'>{$labels['badge']}</div>
			<div class='value lws-editlist-opt-input value lws-editlist-opt-badge' placeholder='{$placeholders['badge']}'>
				$badgeInput
			</div>
			<div class='field-help'>{$tooltips['cost']}</div>
			<div class='lws-editlist-opt-input label'>{$labels['cost']}<div class='bt-field-help'>?</div></div>
			<div class='lws-editlist-opt-input value'>
				<input type='number' name='cost' class='lws_woorewards_pool_cost' />
			</div>
			<div class='field-help'>{$tooltips['roles']}</div>
			<div class='lws-editlist-opt-input label'>{$labels['roles']}<div class='bt-field-help'>?</div></div>
			<div class='lws-editlist-opt-input value'>
				$roles
			</div>
		</div>
	</div>

	{$eventDivs}

</div>
EOT;
		return $str;
	}

	public function write($row)
	{
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'values'   => $row,
			'format'   => array(
				self::ROW_ID         => 'd',
				'src_id'             => 'd',
				'title'              => 't',
				'achievement_badge'  => 'D',
				'cost'               => 'D',
				'roles'              => array('s'),
				'wre_type' => 'K',
			),
			'defaults' => array(
				self::ROW_ID => '',
				'src_id'     => '',
				'title'      => '',
				'roles'      => array(),
			),
			'labels'   => array(
				'title'              => __("Title", 'woorewards-pro'),
				'achievement_badge'  => __("Reward", 'woorewards-pro'),
				'cost'               => __("Occurence", 'woorewards-pro'),
				'roles'              => __("Allowed Roles", 'woorewards-pro'),
				'wre_type'           => __("Action", 'woorewards-pro'),
			)
		));
		if (!(isset($values['valid']) && $values['valid'])) {
			if( !$row['achievement_badge'] )
				return new \WP_Error('400', __("You have to pick a badge as achievement reward.", 'woorewards-pro'));
			return isset($values['error']) ? new \WP_Error('400', $values['error']) : false;
		}

		$pool = false;
		$creation = false;

		if (isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID]))) {
			// quick update
			$pool = $this->getCollection()->find($id);
			if (empty($pool)) {
				return new \WP_Error('404', __("The selected Loyalty System cannot be found.", 'woorewards-pro'));
			}
		} else {
			$creation = true; // new pool

			if (isset($row['src_id']) && !empty($srcId = intval($row['src_id']))) {
				// copy that source pool
				$pool = $this->getCollection()->find($srcId);
				if (empty($pool)) {
					return new \WP_Error('404', __("The selected Loyalty System cannot be found for copy.", 'woorewards-pro'));
				}
				$pool->detach();
			} else {
				$pool = \LWS\WOOREWARDS\PRO\Collections\Achievements::instanciate()->create('achievement')->last();
			}

			$pool->setOption('type', \LWS\WOOREWARDS\Core\Pool::T_LEVELLING);
			$pool->setOption('public', true);
		}

		if (!empty($pool)) {
			$event = $pool->getEvents()->last();
			// can we reuse existant
			if ($event && $event->getType() != $row['wre_type']) {
				foreach ($pool->getEvents()->asArray() as $item) {
					$pool->removeEvent($item);
					$item->delete();
				}
				$event = false;
			}

			// create new if needed
			$action = ($event ? $event : \LWS\WOOREWARDS\Collections\Events::instanciate()->create($row['wre_type'])->last());
			if (!$action) {
				return new \WP_Error('404', __("The selected action type cannot be found.", 'woorewards-pro'));
			}

			if (true === ($err = $action->submit($row))) {
				$action->setMultiplier(1);
				if (!$event) {
					$pool->addEvent($action);
				}
			} else {
				return new \WP_Error('update', $err);
			}

			$badge = $pool->getTheReward();
			if (!$badge) {
				$badge = $pool->createTheReward();
			}

			if ($badge) {
				$badge->setBadgeId($row['achievement_badge']);
				$badge->setCost($row['cost']);
				if (empty($row['title']))
									$row['title'] = $badge->getBadge()->getTitle();
			}

			$pool->setOptions(array(
				'title' => $row['title'],
				'roles' => $row['roles']
			));

			if ($creation) {
				$pool->setName($row['title']);
			}

			$pool->ensureNameUnicity();
			$pool->save(true, true);

			$row = $this->objectToArray($pool);
			return $row;
		}
		return false;
	}

	public function erase($row)
	{
		if (is_array($row) && isset($row[self::ROW_ID]) && !empty($id = intval($row[self::ROW_ID]))) {
			$item = $this->getCollection()->find($id);
			if (empty($item)) {
				return new \WP_Error('404', __("The selected Loyalty System cannot be found.", 'woorewards-pro'));
			} elseif (!$item->isDeletable()) {
				return new \WP_Error('403', __("The default Loyalty Systems cannot be deleted.", 'woorewards-pro'));
			} else {
				$item->delete();
				return true;
			}
		}
		return false;
	}

	public function getCollection()
	{
		static $collection = false;
		if ($collection === false) {
			$collection = \LWS\WOOREWARDS\PRO\Collections\Achievements::instanciate()->load();
		}
		return $collection;
	}

	protected function loadChoices()
	{
		if (!isset($this->choices)) {
			$blacklist = array();
			if (!\LWS_WooRewards::isWC()) {
				$blacklist = array_merge(array('woocommerce'=>'woocommerce'), $blacklist);
			}

			$this->choices = \LWS\WOOREWARDS\Collections\Events::instanciate()->create()->byCategory(
				$blacklist,
				array('achievement')
			)->usort(function ($a, $b) {
				return strcmp($a->getDisplayType(), $b->getDisplayType());
			});
		}
		return $this->choices;
	}

	protected function getGroups()
	{
		return \LWS\WOOREWARDS\Ui\Editlists\EventList::getChoiceCategories();
	}
}
