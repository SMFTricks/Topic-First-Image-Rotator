<?php

/**
 * @package Topic First Image Rotator
 * @version 1.3
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
	 * Check if the user can see attachments
	 *
	 * @var array
	 */
	public static $_boards_attachments = [];

	/**
	 * The pattern for the images
	 *
	 * @var string
	 */
	public static $_img_pattern = '/(\[img.*?\])(.+?)\[\/img\]/';

	/**
	 * The extension for attached iamges
	 *
	 * @var array
	 */
	public static $_attach_extensions = [
		'png',
		'jpg',
		'jpeg',
		'gif',
	];

	/**
	 * The url for the attachments
	 *
	 * @var string
	 */
	public static $_attach_url = '?action=dlattach;topic=';

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
		global $context, $txt, $scripturl;

		$context['post_url'] = $scripturl . '?action=admin;area=modsettings;sa=firsttopicimage;save';
		$context['sub_template'] = 'show_settings';
		$context['settings_title'] = $txt['firsttopicimage'];
		$context['page_title'] .= ' - ' . $txt['firsttopicimage'];

		// The actual settings
		$config_vars = [
			['check', 'firsttopicimage_enable_index' , 'subtext' => $txt['firsttopicimage_enable_index_desc']],
			['check', 'firstopicimage_include_attachments'],
			['boards', 'firstopicimage_selectboards'],
			['check', 'firsttopicimage_board_only' , 'subtext' => $txt['firsttopicimage_board_only_desc']],
			['int', 'firstopicimage_limit', 'subtext' => $txt['firstopicimage_limit_desc']],
			'',
			['int', 'firstopicimage_width'],
			['int', 'firstopicimage_height'],
			'',
			['int', 'firstopicimage_slides_toshow', 'subtext' => $txt['firstopicimage_slides_toshow_desc']],
			['int', 'firstopicimage_slides_toscroll', 'subtext' => $txt['firstopicimage_slides_toscroll_desc']],
			'',
			['check', 'firstopicimage_centermode'],
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
			clean_cache();
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
		$before = array_slice($context['template_layers'], 0, 3);
		$after = array_slice($context['template_layers'], 3);
		$context['template_layers'] = array_merge($before, ['firsttopicimage'], $after);

		$context['block_images'] = self::loadImages();
	}

	public static function loadImages()
	{
		global $board, $topic, $modSettings, $context, $smcFunc, $txt, $user_info, $scripturl;

		// Should we load in the current section?
		if (((!empty($modSettings['firsttopicimage_enable_index']) && !empty($context['current_action'])) || !empty($topic)) || ((empty($board) || !empty($topic)) && empty($modSettings['firsttopicimage_enable_index'])))
			return [];

		// Load the images
		// Set the boards
		self::$_boards = !empty($modSettings['firstopicimage_selectboards']) ? explode(',', $modSettings['firstopicimage_selectboards']) : [0];

		// Make sure boards are int...
		if (!empty(self::$_boards))
			foreach (self::$_boards as $set_board => $id)
				self::$_boards[$set_board] = (int) $id;

		// Don't show slider on this board if it's not in the set
		if (!empty($board))
		{
			if (!in_array($board, self::$_boards))
				return false;

			// Only show images from this board?
			if (!empty($modSettings['firsttopicimage_board_only']))
				self::$_boards = [$board];
		}

		// Load the CSS
		loadCSSFile('//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.css', ['external' => true]);
		loadCSSFile('FirstTopicImage/styles.css', ['default_theme' => true], 'firsttopicimage_styles');
		// Change width/height
		addInlineCss(
			'.firstopicimage-slick div.resize_image > a > img
			{' . 
				(!empty($modSettings['firstopicimage_width']) ? ('width: ' . $modSettings['firstopicimage_width'] . 'px;') : '' ) . 
				(!empty($modSettings['firstopicimage_height']) ? ('height: ' . $modSettings['firstopicimage_height'] . 'px;') : '' ) . 
			'}' . (empty($modSettings['firstopicimage_centermode']) ? '' : '
			div.resize_image:not(.slick-center) > a>  img
			{
				width: 80px !important;
				height: 100px !important;
			}')
		);
		// Load the JS
		loadJavaScriptFile('//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', ['external' => true, 'defer' => true]);

		// Check if the user can see attachments
		self::$_boards_attachments = !empty($modSettings['firstopicimage_include_attachments']) ? boardsAllowedTo('view_attachments') : [];

		if ((self::$_images = cache_get_data('first_topic_image_u' . $user_info['id'] . (!empty($board) ? '_b' . $board : ''), 3600)) === null)
		{
			$request =  $smcFunc['db_query']('', '
				SELECT t.id_topic, t.id_board, t.id_first_msg, t.id_member_started, t.approved,
					m.subject, m.body, m.poster_time,
					mem.id_member, mem.real_name,
					b.name'. (!empty($modSettings['firstopicimage_include_attachments']) && !empty(self::$_boards_attachments) ? ',
					MIN(a.id_attach) AS id_attach, MAX(a.fileext) AS fileext, a.approved, a.attachment_type' : '') . '
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = t.id_member_started)
					LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)'. (!empty($modSettings['firstopicimage_include_attachments']) && !empty(self::$_boards_attachments) ? '
					LEFT JOIN  {db_prefix}attachments AS a ON (a.id_msg = t.id_first_msg)' : '') . '
				WHERE t.id_board IN ({array_int:boards})
					AND (m.body LIKE "%[img%"'. (!empty($modSettings['firstopicimage_include_attachments']) && !empty(self::$_boards_attachments) ? ' 
						OR (m.body LIKE "%[attach%")
						AND a.attachment_type = {int:attachment_type}
						AND a.approved = {int:approved}
						AND fileext IN ({array_string:extensions})'  : '') . ')
					AND {query_see_board}' . (!empty($modSettings['firstopicimage_include_attachments']) && !empty(self::$_boards_attachments) ? (self::$_boards_attachments == [0] ? '' : '
					AND t.id_board IN ({array_int:attachment_boards})') : '
					') . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND (t.approved = {int:approved}
						OR (t.id_member_started != 0
						AND t.id_member_started = {int:current_member}))') . (!empty($modSettings['firstopicimage_include_attachments']) && !empty(self::$_boards_attachments)? '
				GROUP BY t.id_topic, t.id_board, t.id_first_msg, t.id_member_started, t.approved, m.subject, m.body, m.poster_time, mem.id_member, mem.real_name, b.name, a.approved, a.attachment_type' : '') . '
				ORDER BY t.id_topic DESC
				LIMIT {int:limit}',
				[
					'approved' => 1,
					'attachment_type' => 0,
					'boards' => self::$_boards,
					'attachment_boards' => !empty(self::$_boards_attachments ) && is_array(self::$_boards_attachments ) ? self::$_boards_attachments  : [],
					'limit' => empty($modSettings['firstopicimage_limit']) ? 10 : $modSettings['firstopicimage_limit'],
					'current_member' => $user_info['id'],
					'extensions' => self::$_attach_extensions, 
				]
			);

			// Populate the array
			while ($row = $smcFunc['db_fetch_assoc']($request))
			{
				// Set the var
				$img_url  = [];
				// Get the image url
				if (empty($row['id_attach']))
				{
					if (empty(preg_match(self::$_img_pattern, $row['body'], $img_url)))
						continue;
				}
				else
				{
					$img_url[2] = $scripturl . self::$_attach_url . $row['id_topic'] . '.0;attach=' . $row['id_attach'];
				}
				
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
						'src' => $img_url[2],
						'img' => '<img src="' . $img_url[2] . '" alt="' . $row['subject'] . '"/>',
					]
				];
			}

			$smcFunc['db_free_result']($request);

			cache_put_data('first_topic_image_u' . $user_info['id'] . (!empty($board) ? '_b' . $board : ''), self::$_images, 3600);
		}

		// Fire up the slides
		addInlineJavaScript('
		$(document).ready(function(){
			$(\'.firstopicimage-slick\').slick({
				dots: false,
				infinite: true,
				centerMode: ' . (empty($modSettings['firstopicimage_centermode']) ? 'false' : 'true') . ',
				autoplay: ' . (empty($modSettings['firstopicimage_slides_autoplay']) ? 'false' : 'true') . ',
				autoplaySpeed: ' . (empty($modSettings['firstopicimage_slides_speed']) ? '1500' : $modSettings['firstopicimage_slides_speed']) . ',
				slidesToShow: ' . (!empty($modSettings['firstopicimage_slides_toshow']) ? (!empty(self::$_images) && $modSettings['firstopicimage_slides_toshow'] > count(self::$_images) ? count(self::$_images) - 1 : $modSettings['firstopicimage_slides_toshow']) : '5') . ',
				slidesToScroll: ' . (empty($modSettings['firstopicimage_slides_toscroll']) ? '1' : $modSettings['firstopicimage_slides_toscroll']) . ',

				responsive: [
				{
					breakpoint: 1200,
					settings: {
						slidesToShow: ' . (!empty(self::$_images) && count(self::$_images) <= 5 ? count(self::$_images) - 1 : '5') . ',
					}
				},
				{
					breakpoint: 991,
					settings: {
						slidesToShow: ' . (!empty(self::$_images) && count(self::$_images) <= 3 ? count(self::$_images) - 1 : '3') . ',
					}
				},
				{
					breakpoint: 580,
					settings: {
						slidesToShow: 1,
					}
				}]
			});
		});', true);

		return self::$_images;
	}
}