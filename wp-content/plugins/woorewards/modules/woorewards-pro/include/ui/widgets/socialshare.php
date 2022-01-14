<?php
namespace LWS\WOOREWARDS\PRO\Ui\Widgets;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Provide a widget for customer to share a content on social medias.
 * Can be used as a Widget, a Shortcode [lws_social_share]. */
class SocialShareWidget extends \LWS\WOOREWARDS\Ui\Widgets\Widget
{
	const URL_ARG = 'wrshare';
	const URL_ARG_P = 'wrshare2';
	const META_KEY = 'lws_woorewards_social_share_token_';

	public static function install()
	{
		self::register(get_class());
		$me = new self(false);
		\add_shortcode('lws_social_share', array($me, 'shortcode'));

		\add_filter('lws_adminpanel_stygen_content_get_'.'wr_social_share', array($me, 'template'));

		\add_filter('query_vars', array($me, 'varsReferral'));
		\add_action('parse_query', array($me, 'grabReferral'));
		\add_filter('lws_woorewards_fresh_user_sponsored_by', array($me, 'sponsorship'), 11, 3);

		\add_action('wp_enqueue_scripts', array($me, 'registerScripts'));
		\add_action('admin_enqueue_scripts', array($me, 'registerScripts'));
	}

	function registerScripts()
	{
		\wp_register_style('woorewards-social-share', LWS_WOOREWARDS_PRO_CSS.'/templates/social-share.css?stygen=lws_woorewards_social_share_template', array(), LWS_WOOREWARDS_PRO_VERSION);
		\wp_register_script('woorewards-social-share',LWS_WOOREWARDS_PRO_JS.'/social-share.js',array('jquery', 'lws-tools'),LWS_WOOREWARDS_PRO_VERSION, true);
	}


	protected function enqueueScripts($demo=false)
	{
		\wp_enqueue_style('lws-icons');
		if( !$demo )
		{
			\wp_enqueue_script('jquery');
			\wp_enqueue_script('lws-tools');
			\wp_enqueue_script('woorewards-social-share');
			// cause stygen already include it
			\wp_enqueue_style('woorewards-social-share');
		}

		\do_action('lws_woorewards_socials_scripts', $demo);
	}

	/** Will be instanciated by WordPress at need */
	public function __construct($asWidget=true)
	{
		if( $asWidget )
		{
			parent::__construct(
				'lws_woorewards_social_share',
				__("MyRewards Social share", 'woorewards-pro'),
				array(
					'description' => __("Provide Social Share links to your customers.", 'woorewards-pro')
				)
			);
		}
	}

	function template($snippet){
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
		if( !empty(\get_current_user_id()) || !empty(\get_option('lws_woorewards_social_display_unconnected', '')) )
		{
			echo $args['before_widget'];
			if( is_array($instance) && isset($instance['title']) && !empty($instance['title']) )
			{
				echo $args['before_title'];
				echo \apply_filters('widget_title', $instance['title'], $instance);
				echo $args['after_title'];
			}
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

		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Social Share - Title", $new_instance['header']);
		\do_action('wpml_register_single_string', 'Widgets', "WooRewards - Social Share - Description", $new_instance['text']);

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
			\esc_attr(_x("Share that content on Social Medias", "frontend widget", 'woorewards-pro'))
		);
		// text
		$this->eFormFieldText(
			$this->get_field_id('text'),
			__("Text displayed to users", 'woorewards-pro'),
			$this->get_field_name('text'),
			\esc_attr($instance['text']),
			\esc_attr(_x("Earn loyalty points by sharing this page with your friends on social medias", "frontend widget", 'woorewards-pro'))
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
			'text'  => '',
			'url'  => '',
		);
	}

	public function getOrCreateToken($userId, $social)
	{
		$token = \get_user_meta($userId, self::META_KEY.$social, true);
		if( empty($token) && ($user = \get_user_by('ID', $userId)) )
		{
			$token = \sanitize_key(\wp_hash($social.json_encode($user).rand()));
			\update_user_meta($userId, self::META_KEY.$social, $token);
		}
		return $token;
	}

	public function sponsorship($sponsor, $user, $email)
	{
		if( !$sponsor->id && \get_option('lws_woorewards_socialshare_back_give_sponsorship', '') )
		{
			$sponsorship = new \LWS\WOOREWARDS\PRO\Core\Sponsorship();
			$ref = $sponsorship->getCurrentReferral();

			if( $ref->user_id && $ref->hash && $ref->origin && \LWS\WOOREWARDS\PRO\Core\Socials::instance()->isSupportedNetwork($ref->origin) )
			{
				if( $ref->user_id != $user->ID )
				{
					$meta = $this->getMetaByReferral($ref->hash);
					if( $ref->user_id == $meta->user_id )
					{
						$sponsor->id = $ref->user_id;
						$sponsor->origin = $meta->social;
					}
				}
			}
		}
		return $sponsor;
	}

	function varsReferral($vars)
	{
		$vars[] = self::URL_ARG;
		$vars[] = self::URL_ARG_P;
		return $vars;
	}

	/** Keep referral in session to let visitor continues without losing referral info.
	 * @see \LWS\WOOREWARDS\PRO\Core\Sponsorship::setCurrentReferral() */
	public function grabReferral(&$query)
	{
		$referral = isset($query->query[self::URL_ARG]) ? trim($query->query[self::URL_ARG]) : '';
		$hash = isset($query->query[self::URL_ARG_P]) ? trim($query->query[self::URL_ARG_P]) : '';

		if( !empty($referral) && !empty($hash) )
		{
			$sponsorship = new \LWS\WOOREWARDS\PRO\Core\Sponsorship();
			$ref = $sponsorship->getCurrentReferral();

			if( $ref->hash != $hash || !$ref->user_id || !$ref->origin )
			{
				$meta = $this->getMetaByReferral($referral);
				\do_action('lws_woorewards_social_backlink', $meta->user_id, $meta->social, $hash);
				$ref->user_id = $meta->user_id;
				$ref->hash = $referral;
				$ref->origin = $meta->social;
			}
			$sponsorship->setCurrentReferral($ref->user_id, $ref->hash, $ref->origin);
			if( \get_option('lws_woorewards_redirect_after_referral_grab', 'on') )
			{
				\wp_redirect(\remove_query_arg(array(self::URL_ARG, self::URL_ARG_P)));
				exit();
			}
		}
	}

	protected function getMetaByReferral($referral)
	{
		global $wpdb;
		$metakey = self::META_KEY . '%';
		$meta = $wpdb->get_row($wpdb->prepare(
			"SELECT user_id, meta_key FROM {$wpdb->usermeta} WHERE meta_key LIKE '{$metakey}' AND meta_value=%s",
			$referral
		));
		if( $meta )
		{
			$meta->social = substr($meta->meta_key, strlen(self::META_KEY));
			return $meta;
		}
		return (object)array('user_id' => false, 'meta_key' => false, 'social' => false);
	}

	/** @brief shortcode [lws_sponsorship]
	 *	Display input box to set sponsored email, then a div for server answer. */
	public function shortcode($atts=array(), $content='')
	{
		$atts = \wp_parse_args($atts, $this->defaultArgs());
		if( empty($userId = \get_current_user_id()) )
		{
			if( empty(\get_option('lws_woorewards_social_display_unconnected', '')) )
				return $content;
		}

		$demo = isset($this->stygen);
		$nonce = $demo ? '' : \esc_attr(\wp_create_nonce('lws_woorewards_socialshare'));
		$hash = '';
		if( !$demo && $userId )
		{
			if( !empty($atts['url']) )
				$hash = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getCustomPageHash($atts['url']);
			else
				$hash = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getCurrentPageHash();
		}
		$escHash = \esc_attr($hash);

		$buttons = '';
		$socials = \lws_get_option(
			'lws_woorewards_smshare_socialmedias',
			\LWS\WOOREWARDS\PRO\Core\Socials::instance()->getSupportedNetworks()
		);
		$popup = '';
		if( !empty(\get_option('lws_woorewards_social_share_popup', '')) )
		{
			$popup = " data-popup='on'";
		}

		foreach($socials as $social)
		{
			$icon = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getIcon($social);
			$href = '#';
			$target='';
			if( !$demo )
			{
				$url = '';
				if( $userId )
				{
					if( !empty($atts['url']) )
					{
						$url = \add_query_arg(
							array(
								self::URL_ARG => $this->getOrCreateToken($userId, $social),
								self::URL_ARG_P => $hash
							),
							$atts['url']
						);
					}else{
						$url = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getCurrentPageUrl(array(
							self::URL_ARG => $this->getOrCreateToken($userId, $social),
							self::URL_ARG_P => $hash
						));
					}
				}
				else
				{
					if( !empty($atts['url']) )
						$url = $atts['url'];
					else
						$url = \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getCurrentPageUrl();
				}
				$href = \esc_attr(\LWS\WOOREWARDS\PRO\Core\Socials::instance()->getShareLink($social, $url));
				$target='_blank';
			}
			$buttons .= "<a target='{$target}'{$popup} href='{$href}' class='lwss_selectable lws-woorewards-social-button {$icon}' data-n='{$nonce}' data-s='{$social}' data-p='{$escHash}' data-type='{$social} link'></a>";
		}

		if( empty($atts['header']) )
			$atts['header'] = \lws_get_option('lws_woorewards_social_share_widget_message', __("Share that content on Social Medias", 'woorewards-pro'));
		if( empty($atts['text']) )
			$atts['text'] = \lws_get_option('lws_woorewards_social_share_widget_text', __("Earn loyalty points by sharing this page with your friends on Social Medias", 'woorewards-pro'));
		if( !$demo )
		{
			$atts['header'] = \apply_filters('wpml_translate_single_string', $atts['header'], 'Widgets', "WooRewards - Social Share - Title");
			$atts['text'] = \apply_filters('wpml_translate_single_string', $atts['text'], 'Widgets', "WooRewards - Social Share - Description");
			if( !$userId )
			{
				$atts['text'] = \lws_get_option('lws_woorewards_social_text_unconnected', __("Only logged in users can earn points for sharing", 'woorewards-pro'));
				$atts['text'] = \apply_filters('wpml_translate_single_string', $atts['text'], 'Widgets', "WooRewards - Social Share - Not connected");
			}
		}
		$this->enqueueScripts($demo);

		$content = <<<EOT
<div class='lwss_selectable lws-woorewards-social_share-widget' data-type='Main'>
	<div class='lwss_selectable lwss_modify lws-woorewards-social_share-description' data-id='lws_woorewards_social_share_widget_message' data-type='Header'>
		<span class='lwss_modify_content'>{$atts['header']}</span>
	</div>
	<div class='lwss_selectable lwss_modify lws-woorewards-social_share-text' data-id='lws_woorewards_social_share_widget_text' data-type='Message to users'>
		<span class='lwss_modify_content'>{$atts['text']}</span>
	</div>
	<div class='lwss_selectable lws-woorewards-social_share-btline' data-type='Buttons Line'>
		{$buttons}
	</div>
</div>
EOT;
		return $content;
	}
}
