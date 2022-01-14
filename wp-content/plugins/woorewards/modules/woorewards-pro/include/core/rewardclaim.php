<?php
namespace LWS\WOOREWARDS\PRO\Core;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Expect user follow a unlock link to generate a reward.
 * This class is able to generate url argument to create a reward redeem lin.
 * and answer that kind of link.
 * Then, found back unlockable generate the reward, then provide a simple feedback to user.
 * Should produce redirection. */
class RewardClaim
{
	/** add arguments to url to redeem an unlockable and generate the reward.
	 * The Unlockable must belong to a pool. */
	static public function addUrlUnlockArgs($url, $unlockable, $user)
	{
		if( empty($pool = $unlockable->getPool()) || empty($pool->getId()) )
			return $url;

		static $lastUserKey = '';
		static $lastUser = null;
		if( $lastUser != $user || empty($lastUserKey) )
		{
			$lastUser = $user;
			$lastUserKey = \get_user_meta($user->ID, 'lws_woorewards_user_key', true);
			if( empty($lastUserKey) )
			{
				\update_user_meta($user->ID, 'lws_woorewards_user_key', $lastUserKey = \sanitize_key(\wp_hash(implode('*', array(
					$user->ID,
					$user->user_email,
					rand()
				)))));
			}
		}

		static $lastPoolKey = '';
		static $lastPool = null;
		if( empty($lastPool) || $lastPool->getId() != $pool->getId() || empty($lastPoolKey) )
		{
			$lastPool = &$pool;
			$lastPoolKey = \get_post_meta($lastPool->getId(), 'lws_woorewards_pool_rkey', true);
			if( empty($lastPoolKey) )
			{
				\update_post_meta($lastPool->getId(), 'lws_woorewards_pool_rkey', $lastPoolKey = \sanitize_key(\wp_hash(implode('*', array(
					$pool->getId(),
					$pool->getStackId(),
					rand()
				)))));
			}
		}

		$key = \sanitize_key(\wp_hash(implode('*', array(
			$pool->getId(),
			$pool->getStackId(),
			$unlockable->getId(),
			$unlockable->getType(),
			$user->ID,
			$user->user_email
		))));

		return \add_query_arg(array(
			'lwsrewardclaim' => $lastUserKey,
			'lwstoken1' => $lastPoolKey,
			'lwstoken2' => self::getUnlockableKey($user, $lastPool, $unlockable),
			'lwsnoc' => \date_create()->getTimestamp(), // unused arg, generated to bypass some cache system
		), $url);
	}

	static public function getUnlockableKey($user, $pool, $unlockable)
	{
		return \sanitize_key(\wp_hash(implode('*', array(
			$pool->getId(),
			$pool->getStackId(),
			$unlockable->getId(),
			json_encode($unlockable->getData(true)),
			$user->ID,
			$user->user_email
		))));
	}

	function __construct()
	{
		\add_action('query_vars', array($this, 'addVars'));
		\add_action('parse_request', array($this, 'unlock'));
		\add_action('lws_adminpanel_stygen_content_get_lws_reward_claim', array($this, 'getTemplate'));

		add_action('wp_enqueue_scripts', function () {
			\wp_register_style('lws-wr-rewardclaim-style', LWS_WOOREWARDS_PRO_CSS.'/templates/rewardclaim.css?stygen=lws_woorewards_lws_reward_claim', array(), LWS_WOOREWARDS_PRO_VERSION);
			\wp_register_script('lws-wr-rewardclaim', LWS_WOOREWARDS_PRO_JS . '/rewardclaim.js', array('jquery', 'jquery-ui-core'), LWS_WOOREWARDS_PRO_VERSION, true);
		});

		\add_action('wp_footer', array($this, 'addFooter'));
	}

	/** Check arguments, then generate the reward and register a user notification.
	 * Finally redirect to erase the argument from url. */
	public function unlock($query)
	{
		$userKey = isset($query->query_vars['lwsrewardclaim']) ? trim($query->query_vars['lwsrewardclaim']) : '';
		$poolKey = isset($query->query_vars['lwstoken1']) ? trim($query->query_vars['lwstoken1']) : '';
		$key = isset($query->query_vars['lwstoken2']) ? trim($query->query_vars['lwstoken2']) : '';

		if ($userKey && $poolKey && $key) {
			global $wpdb;
			// find claimer user
			$user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='lws_woorewards_user_key' AND meta_value=%s", $userKey));
			$user = ($user_id ? \get_user_by('ID', $user_id) : false);
			// find claimed pool
			$pool_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='lws_woorewards_pool_rkey' AND meta_value=%s", $poolKey));
			$pool = ($pool_id ? \LWS\WOOREWARDS\PRO\Core\Pool::getOrLoad($pool_id, true) : false);

			$claimed = false;
			if ($user && $pool) {
				// check current user if any
				$loggedUserId = \get_current_user_id();
				if ($loggedUserId && ($loggedUserId != $user->ID)) {
					// another user is logged, avoid confusion
					$this->redirect(array('lws-wr-claimi' => 'ucf'));
				}
				// find the claimed unlockable
				foreach ($pool->getUnlockables()->asArray() as $unlockable) {
					if ($key == self::getUnlockableKey($user, $pool, $unlockable)) {
						$claimed = $unlockable;
						break;
					}
				}

				if($claimed) {
					if ($pool->unlock($user, $claimed)) {
						// success
						$this->redirect(array('lws-wr-claimi' => -(int)$claimed->getId()));
					} elseif (!$pool->isBuyable()) {
						// fail, pool passed away
						$this->redirect(array('lws-wr-claimi' => 'nbu'));
					}
					else {
						// fail, perhaps insufisent point
						$this->redirect(array('lws-wr-claimi' => 'fai'));
					}
				}
				else {
					// no reward found
					$this->redirect(array('lws-wr-claimi' => 'nou'));
				}
			}
			else {
				// user or pool not found
				$this->redirect(array('lws-wr-claimi' => 'nup'));
			}
		}
	}

	// compute url, redirect to and die.
	protected function redirect($args, $keepPage=true) {
		$url = false;
		if (!$keepPage && \LWS_WooRewards::isWC() && \get_current_user_id()) {
			if (\get_option('lws_woorewards_wc_my_account_endpont_loyalty', 'on'))
				$url = \LWS_WooRewards_Pro::getEndpointUrl('lws_woorewards');
		}
		if (!$url) {
			$url = \remove_query_arg($this->addVars(array('lwsnoc')));
		}
		\wp_redirect(\add_query_arg($args, $url));
		exit;
	}

	/** Tell wordpress to look for our url argument (then readable in $query_vars) */
	public function addVars($query_vars=array())
	{
		// unlock reward
		$query_vars[] = 'lwsrewardclaim';
		$query_vars[] = 'lwstoken1';
		$query_vars[] = 'lwstoken2';
		// show result (after redirect)
		$query_vars[] = 'lws-wr-claimi';
		return $query_vars;
	}

	function getNotice($force=false)
	{
		if( \get_option('lws_wr_rewardclaim_popup_disable', '') )
			return false;

		if (!$force) {
			global $wp_query;
			if (isset($wp_query->query_vars['lws-wr-claimi'])) {
				$force = \sanitize_key($wp_query->query_vars['lws-wr-claimi']);
			}
		}

		if ($force) {
			switch($force) {
				case 'ood':
					return array(
						'title'   => __("Out-of-date link detected", 'woorewards-pro'),
						'message' => __("The link you followed seems obsolete. But don't worry, your loyalty points are still there. Please have a look at the rewards list on the site.", 'woorewards-pro'),
					);
				case 'ucf':
					return array(
						'title'   => __("User conflict", 'woorewards-pro'),
						'message' => __("Operation abort since the connected user is not the owner of the requested reward.", 'woorewards-pro'),
					);
				case 'nup':
					return array(
						'title'   => __("User or loyalty system cannot be found", 'woorewards-pro'),
						'message' => __("The link you followed seems obsolete. But don't worry, your loyalty points are still there. Please have a look at the rewards list on the site.", 'woorewards-pro'),
					);
				case 'fai':
					return array(
						'title'   => __("The requested reward cannot be unlocked", 'woorewards-pro'),
						'message' => __("The requested reward cannot be unlocked, perhaps have you already spent the required point amount? Please, have a look at the rewards list on the site.", 'woorewards-pro'),
					);
				case 'nbu':
					return array(
							'title'   => __("The requested reward cannot be unlocked", 'woorewards-pro'),
							'message' => __("The requested reward cannot be unlocked. The loyalty system has expired.", 'woorewards-pro'),
					);
				case 'nou':
					$force = 0; // let it go to default
				default:
					if (\is_numeric($force)) {
						if ($force = \absint($force)) {
							$claimed = \LWS\WOOREWARDS\Collections\Unlockables::instanciate()->load(array('p' => $force))->last();
							if ($claimed) {
								return array(
									'title'   => $claimed->getTitle(),
									'message' => \LWS\WOOREWARDS\Core\Trace::toString($claimed->getReason('frontend')),
								);
							}
						}
						return array(
							'title'   => __("The requested reward cannot be found", 'woorewards-pro'),
							'message' => __("Rewards should have been updated. Please, have a look at the reward list on the site.", 'woorewards-pro'),
						);
					}
					break;
			}
		}
		return false;
	}

	function getTemplate()
	{
		$this->stygen = true;
		$notice = array(
			'title' => 'The Unlocked Reward Title',
			'message' => 'The desscription of the unlocked reward',
		);
		$pool = \LWS\WOOREWARDS\Collections\Pools::instanciate()->create('dummy')->last();

		$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
		$coupon->setInPercent(false);
		$coupon->setValue('10');
		$coupon->setTitle('The Cat Reward');
		$coupon->setDescription('This is not a real reward - But it looks cool anyway');
		$coupon->dummyImg = \esc_attr(LWS_WOOREWARDS_PRO_IMG.'/cat.png');
		$pool->addUnlockable($coupon, '50');
		$unlockables[] = $coupon;
		$coupon = new \LWS\WOOREWARDS\PRO\Unlockables\Coupon();
		$coupon->setInPercent(true);
		$coupon->setValue('5');
		$coupon->setTitle('The New Woo Reward');
		$coupon->setDescription('This is not a real reward - But it looks cool too');
		$coupon->dummyImg = \esc_attr(LWS_WOOREWARDS_PRO_IMG.'/horse.png');
		$pool->addUnlockable($coupon, '40');
		$unlockables[] = $coupon;
		$content = $this->getPopup($notice,$unlockables);
		unset($this->stygen);
		return $content;
	}

	function enqueueScripts($withClaim=false)
	{
		\wp_enqueue_style('lws-wr-rewardclaim-style');
		\wp_enqueue_style('lws-icons');
		if( $withClaim )
			 \wp_enqueue_script('lws-wr-rewardclaim');
	}

	function addFooter()
	{
		$notice = false;
		if (isset($_REQUEST['lws-wr-claim']) && \trim($_REQUEST['lws-wr-claim'])) {
			// Detect a v2 claim link.
			$notice = $this->getNotice('ood');
		} else {
			$notice = $this->getNotice();
		}

		if ($notice) {
			// Show unlock result
			$this->enqueueScripts(true);
			$unlockables = false;
			// show user available rewards (if option set)
			if (\get_option('lws_wr_rewardclaim_notice_with_rest', 'on')) {
				$unlockables = \LWS\WOOREWARDS\PRO\Conveniences::instance()->getUserUnlockables(\get_current_user_id(), 'avail');
			}
			echo $this->getPopup($notice, $unlockables, 'lws_wooreward_rewardclaimed');
		}
	}

	/** @param $notice array('title'=>'', 'message'=>'')
	 *	@param $unlockables : additional rewards that can still be unlocked
	 *	@param $popupId : additional id that will provoke popup animation
	 *	@return (string) html div */
	public function getPopup($notice, $unlockables=false,$popupId = '')
	{
		$demo = (isset($this->stygen) && $this->stygen);
		$title = \lws_get_option('lws_woorewards_wc_reward_claim_title',__("New reward unlocked !", 'woorewards-pro'));
		$header = \lws_get_option('lws_woorewards_wc_reward_claim_header',__("You've just unlocked the following reward :", 'woorewards-pro'));
		$stitle = \lws_get_option('lws_woorewards_wc_reward_claim_stitle',__("Other rewards are waiting for you", 'woorewards-pro'));
		$notice = \wp_parse_args($notice, array('title'=>'', 'message'=>''));

		if( !isset($this->stygen) )
		{
			$title = \apply_filters('wpml_translate_single_string', $title, 'Widgets', "WooRewards - Reward Claim Popup - Title");
			$header = \apply_filters('wpml_translate_single_string', $header, 'Widgets', "WooRewards - Reward Claim Popup - Header");
			$stitle = \apply_filters('wpml_translate_single_string', $stitle, 'Widgets', "WooRewards - Reward Claim Popup - Subtitle");
		}

		$orcontent ='';
		if( $unlockables && \get_option('lws_wr_rewardclaim_notice_with_rest', 'on') )
		{
			$orcontent .= <<<EOT
			<div class='lwss_selectable lws-woorewards-reward-claim-others' data-type='Unlockable rewards'>
				<div class='lwss_selectable lwss_modify lws-wr-reward-claim-stitle' data-id='lws_woorewards_wc_reward_claim_stitle' data-type='Second Title'>
					<span class='lwss_modify_content'>{$stitle}</span>
				</div>
EOT;
			foreach( $unlockables as $unlockable )
			{
				$pool = $unlockable->getPool();
				$pName = $pool->getName();
				$pTitle = $pool->getOption('display_title');
				$userId = \get_current_user_id();
				$user = \get_user_by('ID', $userId);
				$points = $demo ? 254 : $pool->getPoints($userId);
				if( !($pointName = apply_filters('lws_woorewards_point_symbol_translation', false, 2, $pName)) )
					$pointName = __('Points', 'woorewards-pro');
				$u = array(
					'img'   => $unlockable->getThumbnailImage(),
					'title' => $unlockable->getTitle(),
					'descr' => $unlockable->getCustomDescription(),
					'cost'  => $unlockable->getUserCost($userId, 'front'),
				);
				if( !$u['img'] && $demo && isset($unlockable->dummyImg) )
					$u['img'] = "<img class='lws-wr-thumbnail lws-wr-unlockable-thumbnail' src='{$unlockable->dummyImg}'/>";

				$unlockLink = esc_attr(self::addUrlUnlockArgs(
					\LWS\WOOREWARDS\PRO\Conveniences::instance()->getUrlTarget($demo),
					$unlockable,
					$user
				));
				$labels = array(
					'lsystem' 		=> __("Loyalty System", 'woorewards-pro'),
					'ypoints' 		=> sprintf(__("Your %s", 'woorewards-pro'), strtolower($pointName)),
					'unlock' 		=> __("Unlock", 'woorewards-pro'),
					'cost'	 		=> sprintf(__("%s cost", 'woorewards-pro'), ucfirst(strtolower($pointName)))
				);

				$orcontent .= <<<EOT
			<div class='lwss_selectable lws-woorewards-reward-claim-other' data-type='Unlockable reward'>
				<div class='lwss_selectable lws-woorewards-reward-claim-other-thumb' data-type='Unlockable thumbnail'>{$u['img']}</div>
				<div class='lwss_selectable lws-woorewards-reward-claim-other-cont' data-type='Unlockable details'>
					<div class='lwss_selectable lws-woorewards-reward-claim-other-title' data-type='Unlockable Title'>{$u['title']}</div>
					<div class='lwss_selectable lws-woorewards-reward-claim-other-desc' data-type='Unlockable Description'>{$u['descr']}</div>
				</div>
				<div class='lwss_selectable lws-woorewards-reward-claim-other-info' data-type='Unlockable Informations'><table class='lwss_selectable lws-woorewards-reward-claim-other-table' data-type='Information table'>
					<tr><th class='lwss_selectable lws-woorewards-reward-claim-other-th' data-type='Information header'>{$labels['lsystem']}</th><td>{$pTitle}</td></tr>
					<tr><th class='lwss_selectable lws-woorewards-reward-claim-other-th' data-type='Information header'>{$labels['ypoints']}</th><td>{$points}</td></tr>
					<tr><th class='lwss_selectable lws-woorewards-reward-claim-other-th' data-type='Information header'>{$labels['cost']}</th><td>{$u['cost']}</td></tr>
				</table></div>
				<div class='lwss_selectable lws-woorewards-reward-claim-other-unlock' data-type='Unlockable Action'>
					<button class='lwss_selectable lws-woorewards-reward-claim-other-button' data-type='Unlock Button' data-href="{$unlockLink}">{$labels['unlock']}</button>
				</div>
			</div>
EOT;
			}
		}

		return <<<EOT
			<div id='{$popupId}' class='lwss_selectable lws-woorewards-reward-claim-cont' data-type='Main Container'>
				<div class='lws-wr-reward-claim-titleline'>
					<div class='lwss_selectable lwss_modify lws-wr-reward-claim-title' data-id='lws_woorewards_wc_reward_claim_title' data-type='Title'>
						<span class='lwss_modify_content'>{$title}</span>
					</div>
					<div class='lwss_selectable lws-wr-reward-claim-close lws-icon lws-icon-cross' data-type='Close Button'></div>
				</div>
				<div class='lwss_selectable lwss_modify lws-wr-reward-claim-header' data-id='lws_woorewards_wc_reward_claim_header' data-type='Header'>
					<span class='lwss_modify_content'>{$header}</span>
				</div>
				<div class='lwss_selectable lws-wr-reward-claimed' data-type='Unlocked reward'>
					<div class='lwss_selectable lws-wr-reward-claimed-title' data-type='Reward Title'>{$notice['title']}</div>
					<div class='lwss_selectable lws-wr-reward-claimed-desc' data-type='Reward Description'>{$notice['message']}</div>
				</div>
				$orcontent
			</div>
EOT;
	}
}
