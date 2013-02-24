<?php
/**
 * @copyright	Copyright (C) 2005 - 2009 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;

/**
 * SMF2 User plugin
 *
 * @package		Joomla.Plugin
 * @subpackage	User.joomla
 * @since		1.5
 */
class plgUserSmf2 extends JPlugin
{
	private $_smcFunc = null;
	private $_modSettings = null;
	private $_sourcedir = null;

	/**
	 * Remove all sessions for the user name
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param	array		$user	Holds the user data
	 * @param	boolean		$succes	True if user was succesfully stored in the database
	 * @param	string		$msg	Message
	 *
	 * @return	boolean
	 * @since	1.6
	 */
	public function onUserAfterDelete($user, $succes, $msg)
	{
		global $user_info;

		if (!$succes)
			return false;

		if (!defined('SMF'))
		{
			$this->_initSmf2();
			require_once($this->_sourcedir . '/Subs-Members.php');
			// This is here to fool SMF
			$cUser = JFactory::getUser();
			$user_info['ip'] = '127.0.0.1';
			$user_info['id'] = $cUser->id;
			$user_info['is_admin'] = true;
		}

		// First check if the user exists:
		$request = $this->_smcFunc['db_query']('', '
			SELECT id_member
			FROM {db_prefix}members
			WHERE member_name = {string:member_list}',
			array(
				'member_list' => $user['username'],
				'regular_member' => 0,
				'blank_string' => '',
			)
		);
		$current_member = $this->_smcFunc['db_fetch_assoc']($request);
		$this->_smcFunc['db_free_result']($request);

		if (empty($current_member))
			return true;

		// Then get rid of all the groups (so that SMF's functions will not complain
		$this->_smcFunc['db_query']('', '
			UPDATE {db_prefix}members
			SET
				id_group = {int:regular_member},
				additional_groups = {string:blank_string}
			WHERE id_member = {int:member_list}',
			array(
				'member_list' => $current_member['id_member'],
				'regular_member' => 0,
				'blank_string' => '',
			)
		);
		deleteMembers($current_member['id_member']);

		return true;
	}

	/**
	 * 
	 * This method creates the user in SMF too
	 *
	 * @param	array		$user		Holds the new user data.
	 * @param	boolean		$isnew		True if a new user is stored.
	 * @param	boolean		$success	True if user was succesfully stored in the database.
	 * @param	string		$msg		Message.
	 *
	 * @return	void
	 * @since	1.6
	 */
	public function onUserAfterSave($user, $isnew, $success, $msg)
	{
		if (!$success)
			return false;

		$this->_initSmf2();
		require_once($this->_sourcedir . '/Subs-Members.php');

		if ($isnew)
		{
			$regOptions = array(
				'interface' => 'joomla',
				'username' => $user['username'],
				'email' => $user['email'],
				'password' => $user['password_clear'],
				'password_check' => $user['password_clear'],
				'check_reserved_name' => false,
				'check_password_strength' => false,
				'check_email_ban' => false,
				'send_welcome_email' => false,
				'require' => false,
			);

			$memberID = registerMember($regOptions, true);
			if (is_array($memberID) || empty($memberID))
			{
				// @todo Danger, something went wrong...
				return false;
			}
		}

		// If activation is there it means there is an activation code
		// if it is empty it mean the user is active
		if (empty($user['activation']))
			updateMemberData($memberID, array('is_activated' => 1));

		return true;
	}

	/**
	 * I should use this function to set the cookies...maybe
	 * For sure I should use it to check if the user already
	 * exists and if not create it
	 *
	 * @param	array	$user		Holds the user data
	 * @param	array	$options	Array holding options (remember, autoregister, group)
	 *
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	public function onUserLogin($user, $options = array())
	{
// 		$instance = $this->_getUser($user, $options);
// 
// 		// If _getUser returned an error, then pass it back.
// 		if ($instance instanceof Exception) {
// 			return false;
// 		}
// 
// 		// If the user is blocked, redirect with an error
// 		if ($instance->get('block') == 1) {
// 			JError::raiseWarning('SOME_ERROR_CODE', JText::_('JERROR_NOLOGIN_BLOCKED'));
// 			return false;
// 		}
// 
// 		// Authorise the user based on the group information
// 		if (!isset($options['group'])) {
// 			$options['group'] = 'USERS';
// 		}
// 
// 		// Chek the user can login.
// 		$result	= $instance->authorise($options['action']);
// 		if (!$result) {
// 
// 			JError::raiseWarning(401, JText::_('JERROR_LOGIN_DENIED'));
// 			return false;
// 		}
// 
// 		// Mark the user as logged in
// 		$instance->set('guest', 0);
// 
// 		// Register the needed session variables
// 		$session = JFactory::getSession();
// 		$session->set('user', $instance);
// 
// 		$db = JFactory::getDBO();
// 
// 		// Check to see the the session already exists.
// 		$app = JFactory::getApplication();
// 		$app->checkSession();
// 
// 		// Update the user related fields for the Joomla sessions table.
// 		$db->setQuery(
// 			'UPDATE '.$db->quoteName('#__session') .
// 			' SET '.$db->quoteName('guest').' = '.$db->quote($instance->get('guest')).',' .
// 			'	'.$db->quoteName('username').' = '.$db->quote($instance->get('username')).',' .
// 			'	'.$db->quoteName('userid').' = '.(int) $instance->get('id') .
// 			' WHERE '.$db->quoteName('session_id').' = '.$db->quote($session->getId())
// 		);
// 		$db->query();
// 
// 		// Hit the user last visit field
// 		$instance->setLastVisit();

		return true;
	}

	/**
	 * I should use this function to un-set the cookies...maybe
	 *
	 * @param	array	$user		Holds the user data.
	 * @param	array	$options	Array holding options (client, ...).
	 *
	 * @return	object	True on success
	 * @since	1.5
	 */
	public function onUserLogout($user, $options = array())
	{
// 		$my 		= JFactory::getUser();
// 		$session 	= JFactory::getSession();
// 		$app 		= JFactory::getApplication();
// 
// 		// Make sure we're a valid user first
// 		if ($user['id'] == 0 && !$my->get('tmp_user')) {
// 			return true;
// 		}
// 
// 		// Check to see if we're deleting the current session
// 		if ($my->get('id') == $user['id'] && $options['clientid'] == $app->getClientId()) {
// 			// Hit the user last visit field
// 			$my->setLastVisit();
// 
// 			// Destroy the php session for this user
// 			$session->destroy();
// 		}
// 
// 		// Force logout all users with that userid
// 		$db = JFactory::getDBO();
// 		$db->setQuery(
// 			'DELETE FROM '.$db->quoteName('#__session') .
// 			' WHERE '.$db->quoteName('userid').' = '.(int) $user['id'] .
// 			' AND '.$db->quoteName('client_id').' = '.(int) $options['clientid']
// 		);
// 		$db->query();

		return true;
	}

	/**
	 * This method will return a user object
	 *
	 * If options['autoregister'] is true, if the user doesn't exist yet he will be created
	 *
	 * @param	array	$user		Holds the user data.
	 * @param	array	$options	Array holding options (remember, autoregister, group).
	 *
	 * @return	object	A JUser object
	 * @since	1.5
	 */
	protected function _getUser($user, $options = array())
	{
		$instance = JUser::getInstance();
		if ($id = intval(JUserHelper::getUserId($user['username'])))  {
			$instance->load($id);
			return $instance;
		}

		//TODO : move this out of the plugin
		jimport('joomla.application.component.helper');
		$config	= JComponentHelper::getParams('com_users');
		// Default to Registered.
		$defaultUserGroup = $config->get('new_usertype', 2);

		$acl = JFactory::getACL();

		$instance->set('id'			, 0);
		$instance->set('name'			, $user['fullname']);
		$instance->set('username'		, $user['username']);
		$instance->set('password_clear'	, $user['password_clear']);
		$instance->set('email'			, $user['email']);	// Result should contain an email (check)
		$instance->set('usertype'		, 'deprecated');
		$instance->set('groups'		, array($defaultUserGroup));

		//If autoregister is set let's register the user
		$autoregister = isset($options['autoregister']) ? $options['autoregister'] :  $this->params->get('autoregister', 1);

		if ($autoregister) {
			if (!$instance->save()) {
				return JError::raiseWarning('SOME_ERROR_CODE', $instance->getError());
			}
		}
		else {
			// No existing user and autoregister off, this is a temporary user.
			$instance->set('tmp_user', true);
		}

		return $instance;
	}

	private function _initSmf2()
	{
		global $sourcedir, $boarddir, $time_start,
		$modSettings, $smcFunc, $context, $txt, $user_info,
		$db_server, $db_name, $db_user, $db_passwd, $db_prefix;

		$smf_path = $this->params->get('smf2_path', 0);
		if (empty($smf_path))
			return false;

		define('SMF', 1);
		define('WIRELESS', 0);
		$time_start = microtime();
		// This is here to fool SMF
		$user_info['ip'] = '127.0.0.1';

		require_once($smf_path . '/Settings.php');
		// And important includes.
		require_once($sourcedir . '/QueryString.php');
		require_once($sourcedir . '/Subs.php');
		require_once($sourcedir . '/Errors.php');
		require_once($sourcedir . '/Load.php');
		require_once($sourcedir . '/Security.php');

		// Create a variable to store some SMF specific functions in.
		$smcFunc = array();

		// Initate the database connection and define some database functions to use.
		loadDatabase();

		// Load the settings from the settings table, and perform operations like optimizing.
		reloadSettings();
		$context = array();
		cleanRequest();

		$context['forum_name_html_safe'] = '';
		$context['character_set'] = empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set'];
		$context['utf8'] = $context['character_set'] === 'UTF-8' && (strpos(strtolower(PHP_OS), 'win') === false || @version_compare(PHP_VERSION, '4.2.3') != -1);
		$context['right_to_left'] = !empty($txt['lang_rtl']);

		// Seed the random generator.
		if (empty($modSettings['rand_seed']) || mt_rand(1, 250) == 69)
			smf_seed_generator();

		$this->_smcFunc = $smcFunc;
		$this->_modSettings = $modSettings;
		$this->_sourcedir = $sourcedir;
	}
}
