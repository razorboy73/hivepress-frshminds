<?php
namespace LWS\WOOREWARDS\PRO\Events;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** Manage common feature for Events about order. */
trait T_SponsorshipOrigin
{
	public function getAvailableOrigins()
	{
		$origins = array(
			array('value' => 'sponsor',   'label' => __("Sponsorship email", 'woorewards-pro')),
			array('value' => 'referral',  'label' => __("Referral link", 'woorewards-pro')),
			array('value' => 'socials',   'label' => __("Any social network", 'woorewards-pro')),
			array('value' => 'socialnet', 'label' => __("Specific social network", 'woorewards-pro'), 'group' => \LWS\WOOREWARDS\PRO\Core\Socials::instance()->asDataSource()),
		);
		if( $this->acceptNoneValue() )
		{
			$origins = array_merge(array(
				array('value' => 'none', 'label' => _x("None", "Expect visitor to never registered any sponsor at all", 'woorewards-pro'))
			), $origins);
		}
		return \apply_filters('lws_woorewards_sponsorship_events_available_origins', $origins);
	}

	public function getOrigins()
	{
		return isset($this->origins) ? $this->origins : array();
	}

	public function setOrigins($origins)
	{
		if( !is_array($origins) )
			$origins = @json_decode(@base64_decode($origins));
		if( is_array($origins) )
			$this->origins = $origins;
		return $this;
	}

	/** Guess against current visitor data */
	public function isValidCurrentSponsorship()
	{
		if (!$this->getOrigins())
			return true;

		$sponsorship = new \LWS\WOOREWARDS\PRO\Core\Sponsorship();
		$this->sponsorship = $sponsorship->getCurrentUsers();
		if (!$this->sponsorship->sponsor_id)
			return false;

		return $this->isValidOrigin($this->sponsorship->origin);
	}

	/** @param $order (WP_Order) */
	public function isValidOriginByOrder($order, $guestAllowed=false)
	{
		if (!$this->getOrigins())
			return true;

		$sponsorship = new \LWS\WOOREWARDS\PRO\Core\Sponsorship();
		$this->sponsorship = $sponsorship->getUsersFromOrder($order, $guestAllowed);
		if (!$this->sponsorship->sponsor_id)
			return false;

		return $this->isValidOrigin($this->sponsorship->origin);
	}

	public function isValidOrigin($origin=false)
	{
		if( $origins = $this->getOrigins() ) // no origins set means no restriction
		{
			if( !$origin ) // default origin is sponsor
				$origin = 'sponsor';

			// complete generic restrictions
			if( in_array('sponsor', $origins) )
				$origins[] = 'manual';
			if( in_array('socials', $origins) )
				$origins = array_merge($origins, \LWS\WOOREWARDS\PRO\Core\Socials::instance()->getSupportedNetworks());

			if( !in_array($origin, $origins) )
				return false;
		}
		return true;
	}

	protected function filterSponsorshipData($data=array(), $prefix='')
	{
		$data[$prefix.'origins'] = base64_encode(json_encode($this->getOrigins()));
		return $data;
	}

	protected function filterSponsorshipForm($content='', $prefix='', $context='editlist', $column=2)
	{
		$form = $this->getFieldsetBegin($column, __("Constraints", 'woorewards-pro'), 'span2');

		// Origin restriction
		$label   = __("Sponsorship/Referral Origin", 'woorewards-pro');
		$form .= "<div class='lws-$context-opt-title label'>$label</div>";
		$form .= "<div class='lws-$context-opt-input value'>";
		$form .= \LWS\Adminpanel\Pages\Field\LacChecklist::compose($prefix.'origins', array(
			'comprehensive' => true,
			'source'        => $this->getAvailableOrigins(),
			'class'         => 'above',
		));
		$form .= "</div>";

		$form .= $this->getFieldsetEnd($column);
		return $content.$form;
	}

	/** @return bool */
	protected function optSponsorshipSubmit($prefix='', $form=array(), $source='editlist')
	{
		$values = \apply_filters('lws_adminpanel_arg_parse', array(
			'post'     => ($source == 'post'),
			'values'   => $form,
			'format'   => array(
				$prefix.'origins' => array('S'),
			),
			'defaults' => array(
				$prefix.'origins' => array(),
			),
			'labels'   => array(
				$prefix.'origins' => __("Origin restrictions", 'woorewards-pro'),
			)
		));
		if( !(isset($values['valid']) && $values['valid']) )
			return isset($values['error']) ? $values['error'] : false;

		$this->setOrigins($values['values'][$prefix.'origins']);
		return true;
	}

	protected function optSponsorshipFromPost(\WP_Post $post)
	{
		$this->setOrigins(\get_post_meta($post->ID, 'woorewards_sponsorship_origin', true));
		return $this;
	}

	protected function optSponsorshipSave($id)
	{
		\update_post_meta($id, 'woorewards_sponsorship_origin', $this->getOrigins());
		return $this;
	}

	/** Provided to be overriden. Default returns false.
	 * 	Override and return true to manage a 'None' option in origin.
	 *  'None' expect visitor to never registered any sponsor in any way. */
	protected function acceptNoneValue()
	{
		return false;
	}
}
