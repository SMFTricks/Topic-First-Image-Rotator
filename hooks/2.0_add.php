<?php

/**
 * @package Firs Topic Image Rotator
 * @version 1.0
 * @author Diego Andrés <diegoandres_cortes@outlook.com>
 * @copyright Copyright (c) 2021, SMF Tricks
 * @license MIT
 */

	if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
		require_once(dirname(__FILE__) . '/SSI.php');

	elseif (!defined('SMF'))
		exit('<b>Error:</b> Cannot install - please verify you put this in the same place as SMF\'s index.php.');

	// So... looking for something new
	$hooks = array(
		'integrate_pre_include' => '$sourcedir/FirstTopicImage.php',
		'integrate_modify_modifications' => 'FirstTopicImage::subaction',
		'integrate_admin_areas' => 'FirstTopicImage::admin_area',
		'integrate_load_theme' => 'FirstTopicImage::block',
	);

	foreach ($hooks as $hook => $function)
		add_integration_function($hook, $function);