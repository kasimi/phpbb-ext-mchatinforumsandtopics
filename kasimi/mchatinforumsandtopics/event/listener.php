<?php

/**
 *
 * @package phpBB Extension - mChat in Forums and Topics
 * @copyright (c) 2016 kasimi - https://kasimi.net
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace kasimi\mchatinforumsandtopics\event;

use dmzx\mchat\core\mchat;
use dmzx\mchat\core\settings;
use phpbb\auth\auth;
use phpbb\event\data;
use phpbb\language\language;
use phpbb\template\template;
use phpbb\user;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	/** @var user */
	protected $user;

	/** @var language */
	protected $lang;

	/** @var auth */
	protected $auth;

	/** @var template */
	protected $template;

	/** @var mchat */
	protected $mchat;

	/** @var settings */
	protected $settings;

	/**
	 * @param user		$user
	 * @param language	$lang
	 * @param auth		$auth
	 * @param template	$template
	 * @param mchat		$mchat
	 * @param settings	$settings
	 */
	public function __construct(
		user $user,
		language $lang,
		auth $auth,
		template $template,
		mchat $mchat = null,
		settings $settings = null
	)
	{
		$this->user		= $user;
		$this->lang		= $lang;
		$this->auth		= $auth;
		$this->template	= $template;
		$this->mchat	= $mchat;
		$this->settings	= $settings;
	}

	/**
	 * @return array
	 */
	static public function getSubscribedEvents()
	{
		return [
			// Inject our settings
			'dmzx.mchat.ucp_settings_modify'							=> 'ucp_settings_modify',

			// Display on viewforum and viewtopic
			'core.viewforum_modify_topics_data'							=> 'viewforum',
			'core.viewtopic_modify_page_title'							=> 'viewtopic',

			// UCP and ACP settings
			'core.permissions'											=> ['permissions', -10],
			'core.acp_users_prefs_modify_template_data'					=> ['acp_add_lang', 10],
			'dmzx.mchat.acp_globalusersettings_modify_template_data'	=> ['acp_add_lang', 10],
			'dmzx.mchat.ucp_modify_template_data'						=> ['acp_add_lang', 10],
		];
	}

	/**
	 *
	 */
	public function viewforum()
	{
		$this->add_mchat('viewforum');
	}

	/**
	 *
	 */
	public function viewtopic()
	{
		$this->add_mchat('viewtopic');
	}

	/**
	 * @param string $mode one of viewforum|viewtopic
	 */
	protected function add_mchat($mode)
	{
		if (!$this->is_mchat_enabled() || !$this->auth->acl_get('u_mchat_view') || !$this->settings->cfg('mchat_in_' . $mode))
		{
			return;
		}

		// We use the page_index() method later to render mChat
		// so we need to enable mChat on the index page only for this request
		$this->user->data['user_mchat_index'] = 1;
		$this->settings->set_cfg('mchat_index', 1, true);

		// Render mChat
		$this->mchat->page_index();

		// Amend some template data
		$this->template->assign_vars([
			'MCHAT_PAGE'			=> $mode,
			'MCHAT_INDEX_HEIGHT'	=> (int) $this->settings->cfg('mchat_index_height'),
		]);
	}

	/**
	 *
	 */
	public function acp_add_lang()
	{
		if ($this->is_mchat_enabled())
		{
			$this->lang->add_lang('mchatinforumsandtopics_ucp', 'kasimi/mchatinforumsandtopics');
		}
	}

	/**
	 * @param data $event
	 */
	public function ucp_settings_modify($event)
	{
		$event['ucp_settings'] = array_merge($event['ucp_settings'], [
			'mchat_in_viewforum' => ['default' => 0],
			'mchat_in_viewtopic' => ['default' => 0],
		]);
	}

	/**
	 * @param data $event
	 */
	public function permissions($event)
	{
		if (!$this->is_mchat_enabled())
		{
			return;
		}

		$category = 'mchat_user_config';

		$new_permissions = [
			'u_mchat_in_viewforum',
			'u_mchat_in_viewtopic',
		];

		$categories = $event['categories'];

		if (!empty($categories[$category]))
		{
			$permissions = $event['permissions'];

			foreach ($new_permissions as $new_permission)
			{
				$permissions[$new_permission] = [
					'lang' => 'ACL_' . strtoupper($new_permission),
					'cat' => $category,
				];
			}

			$event['permissions'] = $permissions;
		}
	}

	/**
	 * @return bool
	 */
	protected function is_mchat_enabled()
	{
		return $this->mchat !== null && $this->settings !== null;
	}
}
