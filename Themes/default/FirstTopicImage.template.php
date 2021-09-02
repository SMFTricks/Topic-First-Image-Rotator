<?php

/**
 * @package Firs Topic Image Rotator
 * @version 1.0
 * @author Diego Andrés <diegoandres_cortes@outlook.com>
 * @copyright Copyright (c) 2021, SMF Tricks
 * @license MIT
 */

function template_firsttopicimage_above()
{
	global $context, $txt;

	// Only load if we actually have something
	if (!empty($context['block_images']))
	{
		echo '
			<div class="firstopicimage-slick slider">';

		foreach ($context['block_images'] as $image)
		{
			echo '
			<div class="resize_image">
				<a href="', $image['topic']['link'], '">
					<img src="', $image['image']['src'], '">
				</a>
				<span class="author">
					', $txt['by'] , ' ', $image['author']['name'], '
				</span>
			</div>';
		}
		echo '
			</div>';
	}
}

function template_firsttopicimage_below()
{
	
}