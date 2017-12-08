<?php
/**
 * @package    Joomla.Site
 * @subpackage plg_user_profile_authorlist
 * @author     Jesus Vargas Garita
 * @copyright  Copyright (C) 2018 Jesus Vargas Garita
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('JPATH_BASE') or die;

class plgUserProfile_AuthorList extends JPlugin
{
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	function onContentPrepareData($context, $data)
	{
		// Check we are manipulating a valid form.
		if (!in_array($context, array('com_users.profile', 'com_users.user', 'com_users.registration', 'com_admin.profile')))
		{
 			return true;
		}

		if (is_object($data))
		{
			$userId = isset($data->id) ? $data->id : 0;

			$db = JFactory::getDbo();
			$db->setQuery(
				'SELECT id FROM #__authorlist WHERE userid = '. $userId
			);
			$author_id = $db->loadResult();

			if (!isset($data->profile_authorlist) and $author_id)
			{
				// Load the author data from the database.
				$db->setQuery(
					'SELECT display_alias, image, description, params FROM #__authorlist' .
						' WHERE userid = ' . (int) $userId
				);

				try
				{
					$author = $db->loadObject();
				}
				catch (RuntimeException $e)
				{
					$this->_subject->setError($e->getMessage());
					return false;
				}
				// Merge the profile data.
				$data->profile_authorlist = array();

				foreach ($author as $key=>$value)
				{
					if ($key=='params') {
						$registry = new JRegistry;
						$registry->loadString($value);
						$params = $registry->toArray();
						foreach($params as $pkey=>$pvalue) {
							$data->profile_authorlist['param_'.$pkey] = $pvalue;
						}
					}
					$data->profile_authorlist[$key] = $value;
				}
			}
		}

		return true;
	}

	function onContentPrepareForm($form, $data)
	{
		$userId = isset($data->id) ? $data->id : 0;

		$db = JFactory::getDbo();
		$db->setQuery(
			'SELECT id FROM #__authorlist WHERE userid = '. $userId
		);
		$author_id = $db->loadResult();

		if ($author_id) {
			// Load user_profile plugin language
			$lang = JFactory::getLanguage();
			$lang->load('plg_user_profile_authorlist', JPATH_ADMINISTRATOR);
			$lang->load('com_authorlist', JPATH_ADMINISTRATOR);

			if (!($form instanceof JForm)) {
				$this->_subject->setError('JERROR_NOT_A_FORM');
				return false;
			}
			// Check we are manipulating a valid form.
			if (!in_array($form->getName(), array('com_users.profile', 'com_users.registration','com_users.user','com_admin.profile'))) {
				return true;
			}

			// Add the profile fields to the form.
			JForm::addFormPath(dirname(__FILE__).'/form');
			$form->loadFile('author', false);
		}
	}

	function onUserAfterSave($data, $isNew, $result, $error)
	{
		$userId	= JArrayHelper::getValue($data, 'id', 0, 'int');

		$db = JFactory::getDbo();
		$db->setQuery(
			'SELECT id FROM #__authorlist WHERE userid = '. $userId
		);
		$author_id = $db->loadResult();

		if ($userId && $result && isset($data['profile_authorlist']) && (count($data['profile_authorlist'])) && $author_id)
		{
			try
			{
				$db = &JFactory::getDbo();

				$tuples = array();
				$params = array();
				foreach ($data['profile_authorlist'] as $k => $v) {
					if (strpos($k,'param_')!==false) {
						$k = str_replace('param_', '', $k);
						$params[$k] = $v;
					} else {
						$tuples[] = $k.' = '.$db->quote($v);
					}
				}

				$registry = new JRegistry;
				$registry->loadArray($params);
				$params = (string) $registry;

				$tuples[] = 'params = '.$db->quote($params);

				$db->setQuery('UPDATE #__authorlist SET '.implode(', ', $tuples) . ' WHERE userid = ' .$userId );
				if (!$db->query()) {
					throw new Exception($db->getErrorMsg());
				}
			}
			catch (JException $e) {
				$this->_subject->setError($e->getMessage());
				return false;
			}
		}

		return true;
	}

	function onUserAfterDelete($user, $success, $msg)
	{
		if (!$success) {
			return false;
		}

		$userId	= JArrayHelper::getValue($user, 'id', 0, 'int');

		if ($userId)
		{
			try
			{
				$db = JFactory::getDbo();
				$db->setQuery(
					'DELETE FROM #__authorlist WHERE userid = '.$userId
				);

				if (!$db->query()) {
					throw new Exception($db->getErrorMsg());
				}
			}
			catch (JException $e)
			{
				$this->_subject->setError($e->getMessage());
				return false;
			}
		}

		return true;
	}

}
