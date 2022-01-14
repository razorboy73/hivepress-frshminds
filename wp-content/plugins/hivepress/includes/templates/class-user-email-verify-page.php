<?php
/**
 * User email verify page template.
 *
 * @template user_email_verify_page
 * @description User email verify page.
 * @package HivePress\Templates
 */

namespace HivePress\Templates;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * User email verify page template class.
 *
 * @class User_Email_Verify_Page
 */
class User_Email_Verify_Page extends Page_Narrow {

	/**
	 * Class constructor.
	 *
	 * @param array $args Template arguments.
	 */
	public function __construct( $args = [] ) {
		$args = hp\merge_trees(
			[
				'blocks' => [
					'page_content' => [
						'blocks' => [
							'user_email_verify_message' => [
								'type'   => 'part',
								'path'   => 'user/register/user-email-verify-message',
								'_order' => 10,
							],
						],
					],
				],
			],
			$args
		);

		parent::__construct( $args );
	}
}
