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
use phpbb\template\template;
use phpbb\user;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	/** @var user */
	protected $user;

	/** @var auth */
	protected $auth;

	/** @var template */
	protected $template;

	/** @var mchat */
	protected $mchat;

	/** @var settings */
	protected $settings;

	/** @var string */
	protected $form_name = '';

	/** @var bool */
	protected $custom_form_token = false;

	/**
	 * Constructor
	 *
	 * @param user		$user
	 * @param auth		$auth
	 * @param template	$template
	 * @param mchat		$mchat
	 * @param settings	$settings
	 */
	public function __construct(
		user $user,
		auth $auth,
		template $template,
		mchat $mchat = null,
		settings $settings = null
	)
	{
		$this->user		= $user;
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
		return array(
			// Inject our settings
			'dmzx.mchat.ucp_settings_modify'							=> 'ucp_settings_modify',

			// Display on viewforum and viewtopic
			'core.add_form_key'											=> 'add_form_key',
			'core.viewforum_modify_topics_data'							=> 'viewforum',
			'core.viewtopic_modify_page_title'							=> 'viewtopic',

			// UCP and ACP settings
			'core.permissions'											=> array('permissions', -10),
			'core.acp_users_prefs_modify_template_data'					=> array('acp_add_lang', 10),
			'dmzx.mchat.acp_globalusersettings_modify_template_data'	=> array('acp_add_lang', 10),
			'dmzx.mchat.ucp_modify_template_data'						=> array('acp_add_lang', 10),
		);
	}

	/**
	 * @param Event $event
	 */
	public function add_form_key($event)
	{
		$this->form_name = $event['form_name'];
		$this->custom_form_token = isset($event['template_variable_suffix']);

		if ($this->custom_form_token && $this->form_name === 'mchat')
		{
			$event['template_variable_suffix'] = '_DMZX_MCHAT';
		}
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
		// Abort if another form is already present (when composing a PM, changing UCP settings etc)
		if (!$this->custom_form_token && $this->form_name !== '' && $this->form_name !== 'mchat')
		{
			return;
		}

		if ($this->mchat === null || $this->settings === null)
		{
			return;
		}

		if (!$this->auth->acl_get('u_mchat_view') || !$this->settings->cfg('mchat_in_' . $mode))
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
		$this->template->assign_vars(array(
			'MCHAT_PAGE'			=> $mode,
			'MCHAT_INDEX_HEIGHT'	=> 200,
		));
	}

	/**
	 *
	 */
	public function acp_add_lang()
	{
		if ($this->mchat !== null && $this->settings !== null)
		{
			$this->user->add_lang_ext('kasimi/mchatinforumsandtopics', array('mchatinforumsandtopics_ucp'));
		}
	}

	/**
	 * @param Event $event
	 */
	public function ucp_settings_modify($event)
	{
		$event['ucp_settings'] = array_merge($event['ucp_settings'], array(
			'mchat_in_viewforum' => array('default' => 0),
			'mchat_in_viewtopic' => array('default' => 0),
		));
	}

	/**
	 * @param Event $event
	 */
	public function permissions($event)
	{
		$category = 'mchat_user_config';

		$new_permissions = array(
			'u_mchat_in_viewforum',
			'u_mchat_in_viewtopic',
		);

		$categories = $event['categories'];

		if (!empty($categories[$category]))
		{
			$permissions = $event['permissions'];

			foreach ($new_permissions as $new_permission)
			{
				$permissions[$new_permission] = array(
					'lang' => 'ACL_' . strtoupper($new_permission),
					'cat' => $category,
				);
			}

			$event['permissions'] = $permissions;
		}
	}
}
