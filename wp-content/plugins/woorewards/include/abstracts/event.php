<?php
namespace LWS\WOOREWARDS\Abstracts;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_WOOREWARDS_INCLUDES . '/core/pool.php';

/** Base class for each way to earn points.
 *	To be used, an Event must be declare by calling Event::register @see IRegistrable
 *
 *	The final purpose of an Event is to generate points @see addPoint()
 *
 *	Each pool is in charge to :
 * * install its selected events @see _install()
 * * save specific settings @ss _save()
 * * load specific data @see _fromPost()
 *
 *	Anyway, an event is available for information or selection and so can be instanciated from anywhere.
 *  */
abstract class Event implements ICategorisable, IRegistrable
{
	const POST_TYPE = 'lws-wre-event';
	private static $s_events = array();

	/** Inhereted Event already instanciated from WP_Post, $this->id is availble. It is up to you to load any extra configuration. */
	abstract protected function _fromPost(\WP_Post $post);
	/** Event already saved as WP_Post, $this->id is availble. It is up to you to save any extra configuration. */
	abstract protected function _save($id);
	/** @return a human readable type for UI */
	abstract public function getDisplayType();
	/** Add hook to grab events and add points. */
	abstract protected function _install();

	/** To be overridden.
	 *	@return (bool) if event support that rule.
	 *	Cooldown: do not earn points if the same event triggered
	 *	too many time in a period. */
	function isRuleSupportedCooldown() { return false; }

	/**	@param userId (int)
	 * 	@param $defaultUnset (bool) the value returned if no cooldown defined;
	 *	default is true, so no cooldown means that event is always available.
	 *	@return (bool) true if cooldown is not already full
	 *	for the given user. */
	function isCool($userId, $defaultUnset=true, $defaultNoUser=false)
	{
		if (!$this->isRuleSupportedCooldown())
			return $defaultUnset;
		if (!($ci = $this->getCooldownInfo(true)))
			return $defaultUnset;
		if ($userId) {
			global $wpdb;
			$req = \LWS\Adminpanel\Tools\Request::from($wpdb->lwsWooRewardsHistoric, 'h');
			$req->select('COUNT(h.id)');
			$req->where('h.`user_id`=%d AND h.`origin`=%s');
			$req->arg(array($userId, $this->getId()));
			$req->where(sprintf("DATE_ADD(h.`mvt_date`, %s) >= NOW()", $ci->duration->getSqlInterval()));
			$c = $req->getVar();
			if (false === $c)
				return false; // means error occured in db
			return ($c < $ci->count);
		}
		return $defaultNoUser;
	}

	function getCooldownText()
	{
		if ($ci = $this->getCooldownInfo(true)) {
			$period = $ci->duration->getPeriodText();
			if ($ci->duration->getCount() > 1)
				$period = sprintf(_x('%1$s %2$s', 'cooldown period eg. "3 Months"', 'woorewards-lite'), $ci->duration->getCount(), $period);
			if (1 == $ci->count)
				return sprintf(_x('Max. once every %1$s', 'cooldown display', 'woorewards-lite'), $period);
			else
				return sprintf(_x('Max. %2$s times per %1$s', 'cooldown display', 'woorewards-lite'), $period, $ci->count);
		}
		return '';
	}

	/** @return false or object with properties {count, period[, interval]} */
	function getCooldownInfo($withDateInterval=false)
	{
		if (!$this->isRuleSupportedCooldown())
			return false;
		if (!isset($this->cooldownInfo) || !$this->cooldownInfo)
			return false;
		if (0 == $this->cooldownInfo->count || !\trim($this->cooldownInfo->period))
			return false;
		if ($withDateInterval && !$this->cooldownInfo->duration) {
			$this->cooldownInfo->duration = \LWS\Adminpanel\Tools\Duration::fromString($this->cooldownInfo->period);
			if ($this->cooldownInfo->duration->isNull()) {
				$this->cooldownInfo->count    = 0;
				$this->cooldownInfo->period   = '';
				return false;
			}
		}
		return $this->cooldownInfo;
	}

	function setCooldownInfo($count=0, $period=0.0)
	{
		$this->cooldownInfo = (object)array(
			'count'    => \absint($count),
			'period'   => $period,
			'duration' => false,
		);
		if (0 == $this->cooldownInfo->count || !\trim($this->cooldownInfo->period)) {
			$this->cooldownInfo->count  = 0;
			$this->cooldownInfo->period = '';
		}
	}

	/** If additionnal info should be displayed in settings form. */
	protected function getCooldownTooltips($text)
	{
		return $text;
	}

	static public $recurrentAllowed = false;

	function isRepeatAllowed() {
		return self::$recurrentAllowed;
	}

	/** @return false or object with properties {count, period[, interval]} */
	function getRepeatInfo($withDateInterval=false)
	{
		if (!$this->isRepeatAllowed())
			return false;
		if (!isset($this->repeatInfo) || !$this->repeatInfo)
			return false;
		if (0 == $this->repeatInfo->count || !\trim($this->repeatInfo->period))
			return false;
		if ($withDateInterval && !$this->repeatInfo->duration) {
			$this->repeatInfo->duration = \LWS\Adminpanel\Tools\Duration::fromString($this->repeatInfo->period);
			if ($this->repeatInfo->duration->isNull()) {
				$this->repeatInfo->count    = 0;
				$this->repeatInfo->period   = '';
				return false;
			}
		}
		return $this->repeatInfo;
	}

	function setRepeatInfo($count=0, $period=0.0)
	{
		$this->repeatInfo = (object)array(
			'count'    => \absint($count),
			'period'   => $period,
			'duration' => false,
		);
		if (0 == $this->repeatInfo->count || !\trim($this->repeatInfo->period)) {
			$this->repeatInfo->count  = 0;
			$this->repeatInfo->period = '';
		}
	}

	static public $delayAllowed = false;

	function isDelayAllowed() {
		return self::$delayAllowed;
	}

	/** returns a Duration instance */
	public function getDelay()
	{
		if (!$this->isDelayAllowed())
			return false;
		if (!(isset($this->delay) && $this->delay))
			return false;
		return $this->delay->isNull() ? false : $this->delay;
	}

	/** @param $days (false|int|Duration) */
	public function setDelay($days=false)
	{
		if( empty($days) )
			$this->delay = \LWS\Adminpanel\Duration::void();
		else if( is_a($days, '\LWS\Adminpanel\Duration') )
			$this->delay = $days;
		else
			$this->delay = \LWS\Adminpanel\Duration::fromString($days);
		return $this;
	}

	/**	@return array of data to feed the form @see getForm.
	 *	Each key should be the name of an input balise. */
	function getData()
	{
		$prefix = $this->getDataKeyPrefix();
		$data = array(
			$prefix.'multiplier'  => $this->getMultiplier(),
			$prefix.'title'       => isset($this->title) ? $this->title : '',
			$prefix.'cooldown_c'  => '',
			$prefix.'cooldown_p'  => '',
			$prefix.'delay'       => '',
			$prefix.'recurrent_c' => '',
			$prefix.'recurrent_p' => '',
		);
		if($delay = $this->getDelay()) {
			$data[$prefix.'delay'] = $delay->toString();
		}
		if ($ci = $this->getCooldownInfo()) {
			$data[$prefix.'cooldown_c'] = $ci->count;
			$data[$prefix.'cooldown_p'] = $ci->period;
		}
		if ($ri = $this->getRepeatInfo()) {
			$data[$prefix.'recurrent_c'] = $ri->count;
			$data[$prefix.'recurrent_p'] = $ri->period;
		}
		return $data;
	}

	/**	Provided to be overriden.
	 *	@param $context usage of returned inputs, default is an edition in editlist.
	 *	@return (string) the inside of a form (without any form balise).
	 *	@notice in override, dedicated option name must be type specific @see getDataKeyPrefix()
	 *	dedicated DOM must declare css attribute for hidden/show editlist behavior
	 * 	@code
	 *		class='lws_woorewards_system_choice {$this->getType()}'
	 *	@endcode
	 *	You can use several placeholder balises to insert DOM in middle of previous form (take care to keep for anyone following).
	 *	For each fieldset (numbered from 0, 1...) @see str_replace @see getFieldsetPlaceholder()
	 *	@code
	 *	<!-- [fieldset-1-head:{$this->getType()}] -->
	 *	<!-- [fieldset-1-foot:{$this->getType()}] -->
	 *	@endcode */
	function getForm($context='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$str = $this->getFieldsetBegin(0, __("Action to perform", 'woorewards-lite'), '', false);

		$str .= "<div class='lws-$context-opt-title label'>" . __("Action", 'woorewards-lite') . "</div>";
		$str .= "<div class='value large lws_woorewards_system_type_info'>" . $this->getDisplayType() . "</div>";
		$str .= $this->getFieldsetPlaceholder(true, 0); // type will always be first, so exceptionnaly put at second place

		// custom title
		$label = _x("Action title", "Event title", 'woorewards-lite');
		$placeholder = \esc_attr(\apply_filters('the_title', $this->getDisplayType(), $this->getId()));
		$value = isset($this->title) ? \esc_attr($this->title) : '';
		$str .= "<div class='lws-$context-opt-title label'>$label</div>";
		$str .= "<div class='value lws-$context-opt-input value'><input type='text' size='30' id='{$prefix}title' name='{$prefix}title' value='$value' placeholder='$placeholder' /></div>";

		$str .= $this->getFieldsetEnd(0);
		$str .= $this->getFieldsetBegin(1, __("Points settings", 'woorewards-lite'));

		// multiplier
		$label = _x("Earned points", "Event point multiplier", 'woorewards-lite');
		$placeholder = '1';
		$value = empty($this->getMultiplier()) ? '' : \esc_attr($this->getMultiplier());
		$str .= "<div for='{$prefix}multiplier' class='lws-$context-opt-title label'>$label</div>";
		$str .= "<div class='lws-$context-opt-input value'><input type='text' size='5' id='{$prefix}multiplier' name='{$prefix}multiplier' value='$value' placeholder='$placeholder' pattern='\\d*' /></div>";

		// cooldown
		if ($this->isRuleSupportedCooldown()) {
			$label = _x("Cooldown", "Event cooldown", 'woorewards-lite');
			$tooltip = $this->getCooldownTooltips(__("Allow this event to be counted no more than X times per time interval or let it empty for no limit.", 'woorewards-lite'));
			$value = sprintf(
				_x('Max %1$s times / %2$s', 'cooldown edition', 'woorewards-lite'),
				"<input type='text' id='{$prefix}cooldown_c' name='{$prefix}cooldown_c' size='2'/>",
				\LWS\Adminpanel\Pages\Field\Duration::compose($prefix.'cooldown_p', array(
					'force'   => true,
					'periods' => array('M', 'D', 'W', 'H', 'I'),
				))
			);
			$str .= <<<EOT
<div class='field-help'>{$tooltip}</div>
<div class='lws-{$context}-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>
<div class='lws-{$context}-opt-input value'><div class='lws-field-flex-wrap'>{$value}</div></div>
EOT;
		}

		// Points delay
		if ($this->isDelayAllowed()) {
			$label = _x("Points delay", "Event points delay", 'woorewards-lite');
			$tooltip = __("If set, points will only be given after the delay you set here", 'woorewards-lite');
			$delay = \LWS\Adminpanel\Pages\Field\Duration::compose($prefix.'delay');
			$str .= <<<EOT
<div class='field-help'>{$tooltip}</div>
<div class='lws-{$context}-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>
<div class='lws-{$context}-opt-input value'>{$delay}</div>
EOT;
		}

		// Recurrent points
		if ($this->isRepeatAllowed()) {
			$label = _x("Repeated points", "Event points occurences", 'woorewards-lite');
			$tooltip = __("Set how many times points will be given and at what periodicity", 'woorewards-lite');
			$value = sprintf(
				_x('%1$s occurence(s) | one every %2$s', 'occurences edition', 'woorewards-lite'),
				"<input type='text' id='{$prefix}recurrent_c' name='{$prefix}recurrent_c' size='2'/>",
				\LWS\Adminpanel\Pages\Field\Duration::compose($prefix.'recurrent_p', array(
					'force'   => true,
				))
			);
			$str .= <<<EOT
<div class='field-help'>{$tooltip}</div>
<div class='lws-{$context}-opt-title label'>{$label}<div class='bt-field-help'>?</div></div>
<div class='lws-{$context}-opt-input value' style='margin-bottom:80px'><div class='lws-field-flex-wrap'>{$value}</div></div>
EOT;
		}

		$str .= $this->getFieldsetEnd(1);
		return $str;
	}

	/** Provided to be overriden.
	 *	Back from the form, set and save data from @see getForm
	 *	@param $source origin of form values. Expect 'editlist' or 'post'. If 'post' we will apply the stripSlashes().
	 * 	@return true if ok, (false|string|WP_Error) false or an error description on failure. */
	function submit($form=array(), $source='editlist')
	{
		$prefix = $this->getDataKeyPrefix();
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'multiplier'  => '0',
				$prefix.'title'       => 't',
				$prefix.'cooldown_c'  => '0',
				$prefix.'cooldown_p'  => '/(P(\d+[DYMW])?(T\d+[HMS])?)?/i',
				$prefix.'delay'       => '/(P(\d+[DYMW])?(T\d+[HMS])?)?/i',
				$prefix.'recurrent_c' => '0',
				$prefix.'recurrent_p' => '/(P(\d+[DYMW])?(T\d+[HMS])?)?/i',
			),
			'defaults' => array(
				$prefix.'multiplier' => '0',
				$prefix.'title'      => '',
				$prefix.'cooldown_c' => '0',
				$prefix.'cooldown_p' => '',
				$prefix.'delay'       => '',
				$prefix.'recurrent_c' => '0',
				$prefix.'recurrent_p' => '',
			),
			'labels'   => array(
				$prefix.'multiplier'  => __("Earned points", 'woorewards-lite'),
				$prefix.'title'       => __("Title", 'woorewards-lite'),
				$prefix.'cooldown_c'  => __("Cooldown (action count)", 'woorewards-lite'),
				$prefix.'cooldown_p'  => __("Cooldown (period)", 'woorewards-lite'),
				$prefix.'delay'       => __("Delay", 'woorewards-lite'),
				$prefix.'recurrent_c' => __("Occurences", 'woorewards-lite'),
				$prefix.'recurrent_p' => __("Occurences (period)", 'woorewards-lite'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$this->setTitle($values['values'][$prefix.'title']);
		$this->setMultiplier($values['values'][$prefix.'multiplier']);
		if ($this->isRuleSupportedCooldown()) {
			$this->setCooldownInfo($values['values'][$prefix.'cooldown_c'], $values['values'][$prefix.'cooldown_p']);
		} else {
			$this->setCooldownInfo(0, '');
		}
		if ($this->isDelayAllowed()) {
			$this->setDelay($values['values'][$prefix.'delay']);
		} else {
			$this->setDelay('');
		}
		if ($this->isRepeatAllowed()) {
			$this->setRepeatInfo($values['values'][$prefix.'recurrent_c'], $values['values'][$prefix.'recurrent_p']);
		} else {
			$this->setRepeatInfo(0, '');
		}
		return true;
	}

	protected function getFieldsetBegin($index, $title='', $css='', $withPlaceholder=true)
	{
		if( !empty($css) )
			$css .= ' ';
		$css .= "fieldset fieldset-$index";
		$str = "<div class='$css'>";
		if( !empty($title) )
			$str .= "<div class='title'>$title</div>";
		$str .= "<div class='fieldset-grid'>";
		if( $withPlaceholder )
			$str .= $this->getFieldsetPlaceholder(true, $index);
		return $str;
	}

	protected function getFieldsetEnd($index, $withPlaceholder=true)
	{
		$str = $withPlaceholder ? $this->getFieldsetPlaceholder(false, $index) : '';
		return $str . "</div></div>";
	}

	/** @see getForm insert that balise at top and bottom of each fieldset.
	 * @return (string) html */
	protected function getFieldsetPlaceholder($top, $index)
	{
		return "<!-- [fieldset-".intval($index)."-".($top?'head':'foot').":".$this->getType()."] -->";
	}

	protected function getDataKeyPrefix()
	{
		if( !isset($this->dataKeyPrefix) )
			$this->dataKeyPrefix = \esc_attr($this->getType()) . '_';
		return $this->dataKeyPrefix;
	}

	/**	Provided to be overriden.
	 *	@param $context usage of text. Default is 'backend' for admin, expect 'frontend' for customer.
	 *	@return (string) what this does. */
	function getDescription($context='backend')
	{
		return $this->getDisplayType();
	}

	/** Purpose of an event: earn point for a pool.
	 *	Call this function when an earning event occurs.
	 *	@param $user (WP_User|int) the user earning points.
	 *	@param $reason (string) the cause of the earning.
	 *	@param $pointCount (int) number of point earned, usually 1 since it is up to the pool to multiply it. */
	protected function addPoint($user, $reason='', $pointCount=1, $origin2=false)
	{
		if( !empty($this->getPool()) && !empty($user) )
		{
			if( (is_numeric($user) && $user > 0) || is_a($user, 'WP_User') )
			{
				if( $this->getPool()->userCan($user) )
				{
					$userId = is_numeric($user) ? $user : $user->ID;
					$this->getPool()->addPoints($userId, $this->getMultiplier()*$pointCount, $reason, $this, $origin2 ? $origin2 : $userId);
					$this->getPool()->tryUnlock($userId);
				}
			}
			else
				error_log("Try to add points to an undefined user: " . print_r($user, false));
		}
	}

	public function install()
	{
		$this->_install();
		\do_action('lws_woorewards_abstracts_event_installed', $this);
	}

	static public function fromPost(\WP_Post $post)
	{
		$type = \get_post_meta($post->ID, 'wre_event_type', true);
		$event = static::instanciate($type);

		if( empty($event) )
		{
//			\lws_admin_add_notice_once('lws-wre-event-instanciate', __("Error occured during rewarding event instanciation.", 'woorewards-lite'), array('level'=>'error'));
		}
		else
		{
			$event->id = intval($post->ID);
			$event->name = $post->post_name;
			$event->title = $post->post_title;
			$event->setMultiplier(\get_post_meta($post->ID, 'wre_event_multiplier', true));
			$event->poolId = intval($post->post_parent);
			$event->eventCreationDate = \date_create('0000' != substr($post->post_date_gmt, 0, 4) ? $post->post_date_gmt : $post->post_date);

			$event->cooldownInfo = false;
			if ($event->isRuleSupportedCooldown()) {
				$ci = \get_post_meta($post->ID, 'wre_event_cooldown', true);
				if ($ci) {
					$ci = explode('|', $ci);
					if (count($ci) < 2)
						\array_unshift($ci, 1);
					$event->setCooldownInfo($ci[0], $ci[1]);
				}
			}

			$event->delay = false;
			if($event->isDelayAllowed()) {
				$delay = \get_post_meta($post->ID, 'wre_event_delay', true);
				if($delay) {
					$event->setDelay($delay);
				}
			}

			$event->repeatInfo = false;
			if ($event->isRepeatAllowed()) {
				$ri = \get_post_meta($post->ID, 'wre_event_recurrent', true);
				if ($ri) {
					$ri = explode('|', $ri);
					if (count($ri) < 2)
						\array_unshift($ri, 1);
					$event->setRepeatInfo($ri[0], $ri[1]);
				}
			}

			$event->_fromPost($post);
		}
		return \apply_filters('lws_woorewards_abstracts_event_loaded', $event, $post);
	}

	/** @param $type (string|array) a registered type or an item of getRegistered(). */
	static function instanciate($type)
	{
		$instance = null;
		$registered = (is_string($type) ? static::getRegisteredByName($type) : $type);

		if( is_array($registered) && !empty($registered) )
		{
			try{
				require_once $registered[1];
				$instance = new $registered[0];
			}catch(Exception $e){
				error_log("Cannot instanciate an woorewards Event: " . $e->getMessage());
			}
		}
//		else
//			error_log("Unknown wooreward event registered type from : ".print_r($type, true));

		return $instance;
	}

	public function save(\LWS\WOOREWARDS\Core\Pool &$pool)
	{
		$this->setPool($pool);
		$data = array(
			'ID'          => isset($this->id) ? intval($this->id) : 0,
			'post_parent' => $pool->getId(),
			'post_type'   => self::POST_TYPE,
			'post_status' => $this->getMultiplier() > 0 ? $this->getPoolStatus() : 'draft',
			'post_name'   => $this->getName($pool),
			'post_title'  => isset($this->title) ? $this->title : '',
			'meta_input'  => array(
				'wre_event_multiplier' => $this->getMultiplier(),
				'wre_event_type'       => $this->getType(),
				'wre_event_cooldown'   => '',
				'wre_event_delay'      => '',
				'wre_event_recurrent'  => '',
			)
		);
		if ($this->isRuleSupportedCooldown() && ($ci = $this->getCooldownInfo())) {
			$data['meta_input']['wre_event_cooldown'] = ($ci->count . '|' . $ci->period);
		}
		if ($this->isDelayAllowed()) {
			$data['meta_input']['wre_event_delay'] = $this->getDelay();
		}
		if ($this->isRepeatAllowed() && ($ri = $this->getRepeatInfo())) {
			$data['meta_input']['wre_event_recurrent'] = ($ri->count . '|' . $ri->period);
		}

		$postId = $data['ID'] ? \wp_update_post($data, true) : \wp_insert_post($data, true);
		if( \is_wp_error($postId) )
		{
			error_log("Error occured during event saving: " . $postId->get_error_message());
			\lws_admin_add_notice_once('lws-wre-event-save', __("Error occured during rewarding event saving.", 'woorewards-lite'), array('level'=>'error'));
			return $this;
		}
		$this->id = intval($postId);
		if( isset($this->title) )
			\do_action('wpml_register_string', $this->title, 'title', $this->getPackageWPML(true), __("Title", 'woorewards-lite'), 'LINE');

		$this->_save($this->id);
		\do_action('lws_woorewards_abstracts_event_save_after', $this);
		return $this;
	}

	/** @see https://wpml.org/documentation/support/string-package-translation
	 * Known wpml bug: kind first letter must be uppercase */
	function getPackageWPML($full=false)
	{
		$pack = array(
			'kind' => 'WooRewards Points Earning Method',//strtoupper(self::POST_TYPE),
			'name' => $this->getId(),
		);
		if( $full )
		{
			$title = (isset($this->title) && !empty($this->title)) ? $this->title : ($this->getDisplayType() . '/' . $this->getId());
			if( $pool = $this->getPool() )
				$title = ($pool->getOption('title') . ' - ' . $title);
			$pack['title'] = $title;
			$pack['edit_link'] = \add_query_arg(array('page'=>LWS_WOOREWARDS_PAGE.'.loyalty', 'tab'=>'wr_loyalty.wr_upool_'.$this->getPoolName()), admin_url('admin.php'));
		}
		return $pack;
	}

	/** @alias for getTitle(false, true) */
	public function getTitleAsReason()
	{
		return $this->getTitle(false, true);
	}

	public function getTitle($fallback=true, $forceTranslate=false)
	{
		$title = ((isset($this->title) && !empty($this->title)) ? $this->title : ($fallback ? $this->getDisplayType() : ''));
		if( $forceTranslate || !(is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) )
			$title = \apply_filters('wpml_translate_string', $title, 'title', $this->getPackageWPML());
		return \apply_filters('the_title', $title, $this->getId());
	}

	public function setTitle($title='')
	{
		$this->title = $title;
		return $this;
	}

	public function delete()
	{
		if( isset($this->id) && !empty($this->id) )
		{
			\do_action('lws_woorewards_abstracts_event_delete_before', $this);
			if( empty(\wp_delete_post($this->id, true)) )
				error_log("Failed to delete the rewarding event {$this->id}");
			else
			{
				$pack = $this->getPackageWPML();
				\do_action('wpml_delete_package_action', $pack['name'], $pack['kind']);

				unset($this->id);
			}
		}
		return $this;
	}

	/** Declare a new kind of event. */
	static public function register($classname, $filepath, $unregister=false, $typeOverride=false)
	{
		$id = empty($typeOverride) ? self::formatType($classname) : $typeOverride;
		if( $unregister )
		{
			if( isset(self::$s_events[$id]) )
				unset(self::$s_events[$id]);
		}
		else
			self::$s_events[$id] = array($classname, $filepath);
	}

	static public function getRegistered()
	{
		return self::$s_events;
	}

	static public function getRegisteredByName($name)
	{
		return isset(self::$s_events[$name]) ? self::$s_events[$name] : false;
	}

	public function unsetPool()
	{
		if( isset($this->pool) )
			unset($this->pool);
		return $this;
	}

	public function setPool(&$pool)
	{
		$this->pool =& $pool;
		return $this;
	}

	public function getPool()
	{
		return isset($this->pool) ? $this->pool : false;
	}

	public function getPoolId()
	{
		if( isset($this->pool) && $this->pool )
			return $this->pool->getId();
		else if( isset($this->poolId) )
			return $this->poolId;
		else
			return false;
	}

	public function getPoolName()
	{
		return isset($this->pool) && !empty($this->pool) ? $this->pool->getName() : '';
	}

	public function getPoolType()
	{
		return isset($this->pool) && !empty($this->pool) ? $this->pool->getOption('type') : '';
	}

	public function getPoolStatus()
	{
		if( isset($this->pool) && !empty($this->pool) )
		{
			if( $this->pool->getOption('public') )
				return 'publish';
			else if( $this->pool->getOption('private') )
				return 'private';
			else
				return 'draft';
		}
		return '';
	}

	public function getStackName()
	{
		return isset($this->pool) && !empty($this->pool) ? $this->pool->getStackId() : '';
	}

	public function getId()
	{
		return isset($this->id) ? intval($this->id) : false;
	}

	public function detach()
	{
		if( isset($this->id) )
			unset($this->id);
	}

	/** @param $classname full class with namespace. */
	public static function formatType($classname=false)
	{
		if( $classname === false )
			$classname = \get_called_class();
		return strtolower(str_replace('\\', '_', trim($classname, '\\')));
	}

	public function getType()
	{
		return static::formatType($this->getClassname());
	}

	function getClassname()
	{
		return \get_class($this);
	}

	/** Multiplier is registered by Pool, it is applied to the points generated by the event. */
	public function getMultiplier($context='edit')
	{
		$mul = isset($this->multiplier) ? $this->multiplier : 1;
		if ('view' == $context && (\is_admin() || \get_option('lws_woorewards_show_event_cooldown') == 'on'))
		{
			if ($this->isRuleSupportedCooldown() && $mul > 0.0) {
				if ($ci = $this->getCooldownText()) {
					$mul .= sprintf('<sup>(%s)</sup>', $ci);
				}
			}
		}
		return $mul;
	}

	public function setMultiplier($multiplier)
	{
		$this->multiplier = $multiplier;
		return $this;
	}

	public function setName($name)
	{
		$this->name = $name;
	}

	public function getName($pool=null)
	{
		if( isset($this->name) )
			return $this->name;
		else if( !empty($this->getPool()) )
			return $this->getPool()->getName() . '-' . $this->getType();
		else if( !empty($pool) )
			return $pool->getName() . '-' . $this->getType();
		else
			return $this->getType();
	}

	/** Provided for convenience.
	 * To get new properties with default value. */
	protected function getSinglePostMeta($postId, $meta, $default=false)
	{
		$values = \get_post_meta($postId, $meta, false);
		if( is_array($values) && count($values) )
			return reset($values);
		return $default;
	}

	/** To be overriden to provide choice to administrator. */
	function getInformation()
	{
		return array(
			'label' => $this->getDisplayType(),
			'icon'  => false, /// (string) html
			'color' => false, /// (string) css color format
			'short' => $this->getDescription(),
			'help'  => '',
		);
	}

	/**	Event categories, used to filter out events from pool options.
	 *	@return array with category_id => category_label. */
	public function getCategories()
	{
		return array(
			\LWS\WOOREWARDS\Core\Pool::T_STANDARD  => __("Standard", 'woorewards-lite'),
			\LWS\WOOREWARDS\Core\Pool::T_LEVELLING => __("Leveling", 'woorewards-lite'),
			'achievement' => __("Achievement", 'woorewards-lite'),
			'custom'      => __("Events", 'woorewards-lite')
		);
	}
}
