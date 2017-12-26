<?php

/**
 * Simple Spoiler Extension for phpBB.
 * @author Alfredo Ramos <alfredo.ramos@yandex.com>
 * @copyright 2017 Alfredo Ramos
 * @license GPL-2.0
 */

namespace alfredoramos\simplespoiler\includes;

use phpbb\db\driver\factory as database;
use phpbb\filesystem\filesystem;
use phpbb\language\language;
use phpbb\template\template;

class helper
{

	/** @var \phpbb\db\driver\factory */
	protected $db;

	/** @var \phpbb\filesystem\filesystem */
	protected $filesystem;

	protected $language;

	protected $template;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/** @var \acp_bbcodes */
	protected $acp_bbcodes;

	/**
	 * Constructor of the helper class.
	 *
	 * @param \phpbb\db\driver\factory		$db
	 * @param \phpbb\filesystem\filesystem	$filesystem
	 * @param string						$root_path
	 * @param string						$php_ext
	 *
	 * @return void
	 */
	public function __construct(database $db, filesystem $filesystem, language $language, template $template, $root_path, $php_ext)
	{
		$this->db = $db;
		$this->filesystem = $filesystem;
		$this->language = $language;
		$this->template = $template;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;

		if (!class_exists('acp_bbcodes'))
		{
			include($this->root_path . 'includes/acp/acp_bbcodes.' . $this->php_ext);
		}

		$this->acp_bbcodes = new \acp_bbcodes;
	}

	/**
	 * Install the new BBCode adding it in the database or updating it if it already exists.
	 *
	 * @return void
	 */
	public function install_bbcode()
	{
		// Remove conflicting BBCode
		$this->remove_bbcode('spoiler=');

		$data = $this->bbcode_data();

		if (empty($data))
		{
			return;
		}

		$data['bbcode_id'] = (int) $this->bbcode_id();
		$data = array_replace(
			$data,
			$this->acp_bbcodes->build_regexp(
				$data['bbcode_match'],
				$data['bbcode_tpl']
			)
		);

		// Get old BBCode ID
		$old_bbcode_id = (int) $this->bbcode_exists($data['bbcode_tag']);

		// Update or add BBCode
		if ($old_bbcode_id > NUM_CORE_BBCODES)
		{
			$this->update_bbcode($old_bbcode_id, $data);
		}
		else
		{
			$this->add_bbcode($data);
		}
	}

	/**
	 * Uninstall the BBCode from the database.
	 *
	 * @return void
	 */
	public function uninstall_bbcode()
	{
		$data = $this->bbcode_data();

		if (empty($data))
		{
			return;
		}

		$this->remove_bbcode($data['bbcode_tag']);
	}

	/**
	 * Check whether BBCode already exists.
	 *
	 * @param string $bbcode_tag
	 *
	 * @return integer
	 */
	public function bbcode_exists($bbcode_tag = '')
	{
		if (empty($bbcode_tag))
		{
			return -1;
		}

		$sql = 'SELECT bbcode_id
			FROM ' . BBCODES_TABLE . '
			WHERE ' . $this->db->sql_build_array('SELECT', ['bbcode_tag' => $bbcode_tag]);
		$result = $this->db->sql_query($sql);
		$bbcode_id = (int) $this->db->sql_fetchfield('bbcode_id');
		$this->db->sql_freeresult($result);

		// Set invalid index if BBCode doesn't exist to avoid
		// getting the first record of the table
		$bbcode_id = $bbcode_id > NUM_CORE_BBCODES ? $bbcode_id : -1;

		return $bbcode_id;
	}

	/**
	 * Calculate the ID for the BBCode that is about to be installed.
	 *
	 * @return integer
	 */
	public function bbcode_id()
	{
		$sql = 'SELECT MAX(bbcode_id) as last_id
			FROM ' . BBCODES_TABLE;
		$result = $this->db->sql_query($sql);
		$bbcode_id = (int) $this->db->sql_fetchfield('last_id');
		$this->db->sql_freeresult($result);
		$bbcode_id += 1;

		if ($bbcode_id <= NUM_CORE_BBCODES)
		{
			$bbcode_id = NUM_CORE_BBCODES + 1;
		}

		return $bbcode_id;
	}


	/**
	 * Add the BBCode in the database.
	 *
	 * @param array $data
	 *
	 * @return void
	 */
	public function add_bbcode($data = [])
	{
		if (empty($data) ||
			(!empty($data['bbcode_id']) && (int) $data['bbcode_id'] > BBCODE_LIMIT))
		{
			return;
		}

		$sql = 'INSERT INTO ' . BBCODES_TABLE . '
			' . $this->db->sql_build_array('INSERT', $data);
		$this->db->sql_query($sql);

	}

	/**
	 * Remove BBCode by tag.
	 *
	 * @param string $bbcode_tag
	 *
	 * @return void
	 */
	public function remove_bbcode($bbcode_tag = '')
	{
		if (empty($bbcode_tag))
		{
			return;
		}

		$bbcode_id = (int) $this->bbcode_exists($bbcode_tag);

		// Remove only if exists
		if ($bbcode_id > NUM_CORE_BBCODES)
		{
			$sql = 'DELETE FROM ' . BBCODES_TABLE . '
				WHERE bbcode_id = ' . $bbcode_id;
			$this->db->sql_query($sql);
		}
	}

	/**
	 * Update BBCode data if it already exists.
	 *
	 * @param integer	$bbcode_id
	 * @param array		$data
	 *
	 * @return void
	 */
	public function update_bbcode($bbcode_id = -1, $data = [])
	{
		$bbcode_id = (int) $bbcode_id;

		if ($bbcode_id <= NUM_CORE_BBCODES || empty($data))
		{
			return;
		}

		unset($data['bbcode_id']);

		$sql = 'UPDATE ' . BBCODES_TABLE . '
			SET ' . $this->db->sql_build_array('UPDATE', $data) . '
			WHERE bbcode_id = ' . $bbcode_id;
		$this->db->sql_query($sql);
	}

	public function add_bbcode_help($block_name = '')
	{
		if (empty($block_name) || $block_name !== 'HELP_BBCODE_BLOCK_OTHERS')
		{
			return;
		}

		// Load language keys
		$this->language->add_lang('help', 'alfredoramos/simplespoiler');

		// FAQ helper, it just stores language keys
		$faq = [
			'title' => 'HELP_BBCODE_BLOCK_SPOILERS',
			'questions' => [
				'HELP_BBCODE_SPOILERS_BASIC_QUESTION'	=> 'HELP_BBCODE_SPOILERS_BASIC_ANSWER',
				'HELP_BBCODE_SPOILERS_TITLE_QUESTION'	=> 'HELP_BBCODE_SPOILERS_TITLE_ANSWER'
			]
		];

		// Add help block
		$this->template->assign_block_vars('faq_block', [
			'BLOCK_TITLE'	=> $this->language->lang($faq['title']),
			'SWITCH_COLUMN'	=> false
		]);

		// Arguments for functions generate_text_for_{storage,display}()
		$uid = $bitfield = $flags = null;

		// Generate questions and answers
		foreach ($faq['questions'] as $key => $value)
		{
			$has_title = (strpos($key, 'TITLE') !== false);
			$text = sprintf(
				'[spoiler%2$s]%1$s[/spoiler]',
				$this->language->lang('HELP_BBCODE_SPOILERS_DEMO_BODY'),
				$has_title ? sprintf(' title=%s', $this->language->lang('HELP_BBCODE_SPOILERS_DEMO_TITLE')) : ''
			);
			generate_text_for_storage($text, $uid, $bitfield, $flags, true, true, true);
			$parsed_text = generate_text_for_display($text, $uid, $bitfield, $flags);

			$this->template->assign_block_vars('faq_block.faq_row', [
				'FAQ_QUESTION'	=> $this->language->lang($key),
				'FAQ_ANSWER'	=> $this->language->lang(
					$value,
					$parsed_text,
					$this->language->lang('HELP_BBCODE_SPOILERS_DEMO_BODY'),
					($has_title ? $this->language->lang('HELP_BBCODE_SPOILERS_DEMO_TITLE') : null)
				)
			]);
		}
	}

	/**
	 * BBCode data used in the migration files.
	 *
	 * @return array
	 */
	public function bbcode_data()
	{
		// Return absolute path if file exists
		$xsl = $this->filesystem->realpath(
			__DIR__ . '/../styles/all/template/spoiler.xsl'
		);

		// Store the (trimmed) file content if it is readable
		$template = $this->filesystem->is_readable($xsl) ? trim(file_get_contents($xsl)) : '';

		// The spoiler template should not be empty
		if (empty($template))
		{
			return [];
		}

		return [
			'bbcode_tag'	=> 'spoiler',
			'bbcode_match'	=> '[spoiler title={TEXT2;optional}]{TEXT1}[/spoiler]',
			'bbcode_tpl'	=> $template,
			'bbcode_helpline'	=> 'SPOILER_HELPLINE',
			'display_on_posting'	=> 1
		];
	}

}
