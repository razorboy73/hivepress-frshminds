<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Display a link to a site page for sharing on social networks.
 *	Could be a simple link or a QR code. */
class ReferralWidget extends \LWS\WOOREWARDS\Ui\Widgets\Widget
{
	public static function install()
	{
		self::register(get_class());
		$me = new self(false);

		/* Keep for compatiblity purposes */
		\add_shortcode('lws_referral', array($me, 'shortcode'));
		/* Real Shortcode */
		\add_shortcode('lws_sponsorship_link', array($me, 'shortcode'));

		\add_filter('lws_adminpanel_stygen_content_get_'.'wr_referral', array($me, 'template'));
		\add_filter('query_vars', array($me, 'varsReferral'));
		\add_action('parse_query', array($me, 'grabReferral'));
		\add_filter('lws_woorewards_fresh_user_sponsored_by', array($me, 'sponsorship'), 10, 3);
		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));

		if (\get_option('lws_woorewards_sponsorship_tinify_enabled', ''))
			self::tryDecodeTinyURl();
	}

	function registerScripts()
	{
		\wp_register_script('woorewards-qrcode',LWS_WOOREWARDS_PRO_JS.'/widget-qrcode.js',array('jquery'),LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_script('woorewards-referral',LWS_WOOREWARDS_PRO_JS.'/referral.js',array('jquery'),LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_style('woorewards-referral', LWS_WOOREWARDS_PRO_CSS.'/templates/referral.css?stygen=lws_woorewards_referral_template', array(), LWS_WOOREWARDS_PRO_VERSION);
	}

	protected function enqueueScripts()
	{
		\wp_enqueue_style('lws-icons');
		\wp_enqueue_script('lws-qrcode-js');
		\wp_enqueue_script('woorewards-qrcode');
		if( !isset($this->stygen) )
		{
			\wp_enqueue_script('woorewards-referral');
		}
		\wp_enqueue_style('woorewards-referral');
	}

	public function sponsorship($sponsor, $user, $email)
	{
		if( !$sponsor->id && \get_option('lws_woorewards_referral_back_give_sponsorship', 'on') )
		{
			$sponsorship = new \LWS\WOOREWARDS\PRO\Core\Sponsorship();
			$ref = $sponsorship->getCurrentReferral();
			if( $ref->user_id && $ref->hash && $ref->origin == 'referral' )
			{
				if( $ref->user_id != $user->ID && $ref->user_id == $this->getUserByReferral($ref->hash) )
				{
					$sponsor->id = $ref->user_id;
					$sponsor->origin = 'referral';
				}
			}
		}
		return $sponsor;
	}

	public function varsReferral($vars)
	{
		$vars[] = 'referral';
		return $vars;
	}

	/** Keep referral in session to let visitor continues without losing referral info.
	 * @see \LWS\WOOREWARDS\PRO\Core\Sponsorship::setCurrentReferral() */
	public function grabReferral(&$query)
	{
		$referral = isset($query->query['referral']) ? trim($query->query['referral']) : '';
		if( $referral )
		{
			$sponsorship = new \LWS\WOOREWARDS\PRO\Core\Sponsorship();
			$ref = $sponsorship->getCurrentReferral();
			if( $ref->hash != $referral || !$ref->user_id || $ref->origin != 'referral' )
			{
				$ref->user_id = $this->getUserByReferral($referral);
				$ref->hash = $referral;
				\do_action('lws_woorewards_referral_followed', $referral, $ref->user_id);
			}
			$sponsorship->setCurrentReferral($ref->user_id, $ref->hash, 'referral');
			if( \get_option('lws_woorewards_redirect_after_referral_grab', 'on') )
			{
				\wp_redirect(\remove_query_arg('referral'));
				exit();
			}
		}
	}

	protected function getUserByReferral($referral)
	{
		global $wpdb;
		$metakey = 'lws_woorewards_user_referral_token';
		$refId = $wpdb->get_var($wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='{$metakey}' AND meta_value=%s",
			$referral
		));
		return $refId;
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if( $asWidget )
		{
			parent::__construct(
				'lws_woorewards_referral',
				__("MyRewards Sponsorship Link", 'woorewards-pro'),
				array(
					'description' => __("Provide a Sponsorship link to your customers.", 'woorewards-pro')
				)
			);
		}
	}

	function template($snippet=''){
		$this->stygen = true;
		$snippet = $this->shortcode();
		unset($this->stygen);
		return $snippet;
	}

	/**	Display the widget,
	 *	@see https://developer.wordpress.org/reference/classes/wp_widget/
	 * 	display parameters in $args
	 *	get option from $instance */
	public function widget($args, $instance)
	{
		if( !empty(\get_current_user_id()) )
		{
			echo $args['before_widget'];
			if( is_array($instance) && isset($instance['title']) && !empty($instance['title']) )
			{
				echo $args['before_title'];
				echo \apply_filters('widget_title', $instance['title'], $instance);
				echo $args['after_title'];
			}
			if( isset($instance['url']) && !empty($instance['url']) )
				$instance['url'] = \apply_filters('wpml_translate_single_string', $instance['url'], 'Widgets', "WooRewards - Referral Widget - Redirection");
			echo $this->shortcode($instance);
			echo $args['after_widget'];
		}
	}

	/** ensure all required fields exist. */
	public function update($new_instance, $old_instance)
	{
		$new_instance = \wp_parse_args(
			array_merge($old_instance, $new_instance),
			$this->defaultArgs()
		);

		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Referral Widget - Header", $new_instance['header']);
		if( !empty($new_instance['url']) )
			\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Referral Widget - Redirection", $new_instance['url']);

		return $new_instance;
	}

	/** Widget parameters (admin) */
	public function form($instance)
	{
		$instance = \wp_parse_args($instance, $this->defaultArgs());

		// title
		$this->eFormFieldText(
			$this->get_field_id('title'),
			__("Title", 'woorewards-pro'),
			$this->get_field_name('title'),
			is_array($instance) && isset($instance['title']) ? \esc_attr($instance['title']) : ''
		);
		// header
		$this->eFormFieldText(
			$this->get_field_id('header'),
			__("Header", 'woorewards-pro'),
			$this->get_field_name('header'),
			\esc_attr($instance['header']),
			\esc_attr(_x("Share that sponsorship link", "frontend widget", 'woorewards-pro'))
		);
		// behavior
		$this->eFormFieldRadio(
			$this->get_field_id('display'),
			__("Display", 'woorewards-pro'),
			$this->get_field_name('display'),
			array(
				'link'	=> __("Link", 'woorewards-pro'),
				'qrcode'=> __("QR Code", 'woorewards-pro'),
				'both'	=> __("Both", 'woorewards-pro'),
			),
			$instance['display']
		);

		// url
		$this->eFormFieldText(
			$this->get_field_id('url'),
			__("Shared url (Optional)", 'woorewards-pro'),
			$this->get_field_name('url'),
			\esc_attr($instance['url'])
		);
	}

	protected function defaultArgs()
	{
		return array(
			'title'  => '',
			'header'  => '',
			'url'  => '',
			'display'  => '',
		);
	}

	public function getOrCreateToken($userId)
	{
		$token = \get_user_meta($userId, 'lws_woorewards_user_referral_token', true);
		if( empty($token) )
		{
			$user = \get_user_by('ID', $userId);
			if( $user )
			{
				$token = \sanitize_key(\wp_hash(json_encode($user).rand()));
				\update_user_meta($userId, 'lws_woorewards_user_referral_token', $token);
			}
		}
		return $token;
	}

	/** @brief shortcode [lws_referral]
	 *	 */
	public function shortcode($atts=array(), $content='')
	{
		$this->enqueueScripts();
		$atts = \wp_parse_args($atts, $this->defaultArgs());
		if( empty($userId = \get_current_user_id()) )
			return $content;

		if( !isset($atts['header']) || empty($atts['header']) )
			$atts['header'] = \lws_get_option('lws_woorewards_referral_widget_message', __("Share that Sponsorship link", 'woorewards-pro'));
		if( !isset($this->stygen) )
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Sponsorship Widget - Header");
		if( !isset($atts['display']) || empty($atts['display']) )
			$atts['display'] = \lws_get_option('lws_woorewards_sponsorship_link_display', 'link');

		$url = '';
		if( isset($atts['url']) && $atts['url'] )
			$url = \add_query_arg('referral', $this->getOrCreateToken($userId), $atts['url']);
		else if( $defpage = \get_option('lws_woorewards_sponsorship_link_page') )
			$url = \add_query_arg('referral', $this->getOrCreateToken($userId), \get_permalink($defpage));
		else if( isset($this->stygen) && $this->stygen )
			$url = \add_query_arg('referral', $this->getOrCreateToken($userId), \home_url());
		else // current page
			$url = \add_query_arg('referral', $this->getOrCreateToken($userId), \LWS\Adminpanel\Tools\Conveniences::getCurrentPermalink());

		if (!\is_admin() && !(isset($this->stygen) && $this->stygen) && \get_option('lws_woorewards_sponsorship_tinify_enabled', ''))
			$url = self::tinifyUrl($url);

		$content = 	"<div class='lwss_selectable lws-woorewards-referral-widget' data-type='Main'>";
		$content .= "<div class='lwss_selectable lwss_modify lws-woorewards-referral-description' data-id='lws_woorewards_referral_widget_message' data-type='Header'>";
		$content .= "<span class='lwss_modify_content'>{$atts['header']}</span>";
		$content .= "</div>";

		if($atts['display']=='qrcode' || $atts['display']=='both')
		{
			$link = \esc_attr($url);
			$content .= "<div class='lwss_selectable lws-woorewards-spqrcode-wrapper' data-type='QR Code Wrapper'>";
			$content .= "<div class='lwss_selectable lws-woorewards-spqrcode qrcode' tabindex='0' data-type='QR Code' data-qrcode='{$link}'></div>";
			$content .= "<div class='lwss_selectable lws-woorewards-spqrcode-copy-icon lws-icon lws-icon-copy qrcopy' data-type='Copy QR Code'></div>";
			$content .= "</div>";
		}
		if($atts['display']=='link' || $atts['display']=='both')
		{
			$link = \htmlentities($url);
			$content .= "<div class='lwss_selectable lws-woorewards-referral-field-copy lws_referral_value_copy' data-type='Sponsorship link'>";
			$content .= "<div class='lwss_selectable lws-woorewards-referral-field-copy-text content' tabindex='0' data-type='Link'>{$link}</div>";
			$content .= "<div class='lwss_selectable lws-woorewards-referral-field-copy-icon lws-icon lws-icon-copy copy' data-type='Copy button'></div>";
			$content .= "</div>";
		}
		$content .= "</div>";

		return $content;
	}

	/** If a tiny URL is detected and decoded, redirect and die. */
	static protected function tryDecodeTinyURl()
	{
		if (\is_admin())
			return;
		if (!(isset($_GET, $_GET['~']) && $_GET['~']))
			return;

		$short = $_GET['~'];
		global $wpdb;
		$sql = "SELECT `longurl` FROM {$wpdb->base_prefix}lws_wr_tinyurls WHERE `shorturl` = %s";
		$redirect = $wpdb->get_var($wpdb->prepare($sql, $short));

		if ($redirect) {
			if (\wp_redirect($redirect))
				exit;
		}
	}

	static function tinifyUrl($url)
	{
		$url = \remove_query_arg('~', $url);
		$ref = md5($url);
		$base = \get_option('lws_woorewards_sponsorship_short_url');
		if (!$base)
			$base = \site_url();

		global $wpdb;
		$sql = <<<EOT
SELECT `shorturl`
FROM {$wpdb->base_prefix}lws_wr_tinyurls
WHERE `longref` = %s AND `longurl` = %s
EOT;
		$short = $wpdb->get_var($wpdb->prepare($sql, $ref, $url));

		if (!$short) {
			$unique = $short = \LWS\Adminpanel\Tools\Conveniences::rebaseNumber(substr($ref, 0, 16), 16, 64);
			// unicity
			$sql = "SELECT COUNT(*) FROM {$wpdb->base_prefix}lws_wr_tinyurls WHERE `shorturl` = '%s'";
			$index = 0;
			while($wpdb->get_var(sprintf($sql, $short))) {
				$short = ($unique . $index++);
			}
			// keep it
			$wpdb->query($wpdb->prepare(
				"INSERT INTO {$wpdb->base_prefix}lws_wr_tinyurls (shorturl, longurl, longref) VALUES (%s, %s, %s)",
				$short, $url, $ref
			));
		}
		return \add_query_arg('~', $short, $base);
	}
}
