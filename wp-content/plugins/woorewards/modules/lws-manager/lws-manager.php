<?php
/**
 * Plugin Name: LWS Manager
 * Description: Manage LWS plugins install and support.
 * Plugin URI: https://plugins.longwatchstudio.com
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 2.1.3
 * Text Domain: lwsmanager
 *
 * Copyright (c) 2021 Long Watch Studio (email: contact@longwatchstudio.com). All rights reserved.
 *
 */

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

add_filter('lws_manager_instance', function($instance){
	if (!$instance) {
		require_once \dirname(__FILE__) . '/include/manager.php';
		$instance = (object)array(
			'version'  => '9.9.9', // hardcoded for backward compatibility but not used anymore
			'instance' => new \LWS\Manager\Manager(),
		);
	}
	return $instance;
}, -2001003); // priority based on version => -((major*1000 + minor)*1000) + bugfix
