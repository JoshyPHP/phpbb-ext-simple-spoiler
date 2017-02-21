<?php

/**
 * Simple Spoiler Extension for phpBB.
 * @author Smayliks
 * @copyright 2017 Alfredo Ramos
 * @license GNU GPL-3.0+
 */

/**
 * @ignore
 */
if (!defined('IN_PHPBB')) {
	exit;
}

/**
 * @ignore
 */
if (empty($lang) || !is_array($lang)) {
	$lang = [];
}

$lang = array_merge($lang, [
	'SPOILER'			=> 'Спойлер',
	'SPOILER_HELPLINE'	=> 'Использование: [spoiler]Текст[/spoiler] или [spoiler=Заголовок]Текст[/spoiler]',
	'SPOILER_SHOW'		=> 'Показать',
	'SPOILER_HIDE'		=> 'Скрыть'
]);
