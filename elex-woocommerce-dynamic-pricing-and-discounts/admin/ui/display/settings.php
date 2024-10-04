<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

// Sets active tab 
$active_tab = (isset($_REQUEST['tab']) && is_string($_REQUEST['tab'])) ? sanitize_text_field($_REQUEST['tab']) : 'rules_order';

// Data handling of form submit request.
if (isset($_REQUEST['submit'])) {
	$path = ELEX_DP_BASIC_ROOT_PATH . 'admin/data/settings_page/elex-save-options.php';
	if (file_exists($path) == true) {
		include_once  $path;
	}
}

// Renders view
require_once ELEX_DP_BASIC_ROOT_PATH . 'admin/ui/renderer/settings.php';
