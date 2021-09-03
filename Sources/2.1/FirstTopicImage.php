<?php

/**
 * @package Firs Topic Image Rotator
 * @version 1.0
 * @author Diego Andrés <diegoandres_cortes@outlook.com>
 * @copyright Copyright (c) 2021, SMF Tricks
 * @license MIT
 */

if (!defined('SMF'))
	die('No direct access...');

class FirstTopicImage
{
	/**
	 * Contains the images for the slider
	 * 
	 * @var array
	 */
	public static $_images = [];

	/**
	 * Contains the boards for the messages
	 * 
	 * @var array
	 */
	public static $_boards = [];

	/**
	 * The pattern for the images
	 * 
	 * @var string
	 */
	public static $_pattern = '/(\[img.*?\])(.+?)\[\/img\]/';

	public static function subaction(&$subActions)
	{
		$subActions['firsttopicimage'] = 'FirstTopicImage::settings';
	}

	public static function admin_area(&$admin_areas)
	{
			global $txt;

			// Load the language file
			loadLanguage('FirstTopicImage/');

			// Add the new setting area
			$admin_areas['config']['areas']['modsettings']['subsections']['firsttopicimage'] = [$txt['firsttopicimage']];
	}

	public static function settings($return_config = false)
	{
		global $context, $sourcedir, $txt, $scripturl;

		require_once($sourcedir . '/ManageServer.php');
		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;sa=firsttopicimage;save';
		$context['sub_template'] = 'show_settings';
		$context['settings_title'] = $txt['firsttopicimage'];
		$context['page_title'] .= ' - ' . $txt['firsttopicimage'];

		// The actual settings
		$config_vars = [
			['check', 'firsttopicimage_enable_everywhere' , 'subtext' => $txt['firsttopicimage_enable_everywhere_desc']],
			['boards', 'firstopicimage_selectboards'],
			['int', 'firstopicimage_limit', 'subtext' => $txt['firstopicimage_limit_desc']],
			'',
			['int', 'firstopicimage_width'],
			['int', 'firstopicimage_height'],
			'',
			['int', 'firstopicimage_slides_toshow', 'subtext' => $txt['firstopicimage_slides_toshow_desc']],
			['int', 'firstopicimage_slides_toscroll', 'subtext' => $txt['firstopicimage_slides_toscroll_desc']],
			'',
			['check', 'firstopicimage_slides_autoplay'],
			['int', 'firstopicimage_slides_speed', 'subtext' => $txt['firstopicimage_slides_speed_desc']],
		];

		// Return config vars
		if ($return_config)
			return $config_vars;

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();
			saveDBSettings($config_vars);
			redirectexit('action=admin;area=modsettings;sa=firsttopicimage');
		}
		prepareDBSettingContext($config_vars);
	}

	public static function block()
	{
		global $context;

		// Template
		loadTemplate('FirstTopicImage');

		// Load the layer
		$before = array_slice($context['template_layers'], 0, 2);
		$after = array_slice($context['template_layers'], 2);
		$context['template_layers'] = array_merge($before, ['firsttopicimage'], $after);

		$context['block_images'] = self::loadImages();
	}

	public static function loadImages()
	{
		global $board, $topic, $modSettings, $context, $smcFunc, $txt, $user_info, $scripturl;

		// Should we load in the current section?
		if ((empty($modSettings['firsttopicimage_enable_everywhere']) && empty($board) && empty($topic) && !empty($context['current_action'])) || empty($modSettings['firstopicimage_selectboards']))
			return;
		// Load the images
		else
		{
			// Load the CSS
			loadCSSFile('//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css', ['external' => true]);
			loadCSSFile('FirstTopicImage/styles.css', ['default_theme' => true], 'firsttopicimage_styles');
			// Change width/height
			addInlineCss(
				'.firstopicimage-slick div.resize_image > a > img
				{' . 
					(!empty($modSettings['firstopicimage_width']) ? ('width: ' . $modSettings['firstopicimage_width'] . 'px;') : '' ) . 
					(!empty($modSettings['firstopicimage_height']) ? ('height: ' . $modSettings['firstopicimage_height'] . 'px;') : '' ) . 
				'}'
			);
			// Load the JS
			loadJavaScriptFile('//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', ['external' => true]);
			addInlineJavaScript('
				$(document).ready(function(){
					$(\'.firstopicimage-slick\').slick({
						dots: false,
						infinite: true,
						autoplay: ' . (empty($modSettings['firstopicimage_slides_autoplay']) ? 'false' : 'true') . ',
						autoplaySpeed: ' . (empty($modSettings['firstopicimage_slides_speed']) ? '1500' : $modSettings['firstopicimage_slides_speed']) . ',
						slidesToShow: ' . (empty($modSettings['firstopicimage_slides_toshow']) ? '4' : $modSettings['firstopicimage_slides_toshow']) . ',
						slidesToScroll: ' . (empty($modSettings['firstopicimage_slides_toscroll']) ? '1' : $modSettings['firstopicimage_slides_toscroll']) . ',

						responsive: [
						{
							breakpoint: 1150,
							settings: {
								slidesToShow: 4,
							}
						 },
						{
							breakpoint: 900,
							settings: {
								slidesToShow: 3,
							}
						 },
						{
							breakpoint: 600,
							settings: {
								slidesToShow: 2,
							}
						},
						{
							breakpoint: 400,
							settings: {
								slidesToShow: 1,
							}
						}]
					});
				});
			');

			// Set the boards
			self::$_boards = explode(',', $modSettings['firstopicimage_selectboards']);
			self::$_images = [];

			// Make sure boards are int...
			foreach (self::$_boards as $board => $id)
				self::$_boards[$board] = (int) $id;

			$request =  $smcFunc['db_query']('', '
				SELECT t.id_topic, t.id_board, t.id_first_msg, t.id_member_started, t.approved,
					m.subject, m.body, m.poster_time,
					mem.id_member, mem.real_name,
					b.name
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = t.id_member_started)
					LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				WHERE t.id_board IN ({array_int:boards}) AND m.body LIKE "%[img%" AND {query_see_board}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND (t.approved = 1 OR (t.id_member_started != 0 AND t.id_member_started = {int:current_member}))') . '
				ORDER BY t.id_topic DESC
				LIMIT {int:limit}',
				[
					'boards' => self::$_boards,
					'limit' => empty($modSettings['firstopicimage_limit']) ? 10 : $modSettings['firstopicimage_limit'],
					'current_member' => $user_info['id'],
				]
			);

			// Populate the array
			while($row = $smcFunc['db_fetch_assoc']($request))
			{
				// Get the image urls
				preg_match(self::$_pattern, $row['body'], $matches);
				
				self::$_images[] = [
					'author' => [
						'id' => $row['id_member_started'],
						'name' => empty($row['real_name']) ? $txt['guest'] : $row['real_name'],
						'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_started'] . '">' . $row['real_name'] . '</a>',
					],
					'topic' => [
						'id' => $row['id_topic'],
						'title' => $row['subject'],
						'link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
						'date' => timeformat($row['poster_time']),
					],
					'board' => [
						'id' =>$row['id_board'],
						'name' => $row['name'],
						'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
					],
					'image' => [
						'src' => $matches[2],
						'img' => '<img src="' . $matches[2] . '" alt="' . $row['subject'] . '"/>',
					]
				];
			}
			$smcFunc['db_free_result']($request);

			return self::$_images;
		}
	}
}