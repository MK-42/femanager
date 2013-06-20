<?php
namespace In2\Femanager\Utility;

use \TYPO3\CMS\Core\Utility\GeneralUtility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Alex Kellner <alexander.kellner@in2code.de>, in2code
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Misc Functions
 *
 * @package femanager
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Div {

	/**
	 * userRepository
	 *
	 * @var \In2\Femanager\Domain\Repository\UserRepository
	 * @inject
	 */
	protected $userRepository;

	/**
	 * userGroupRepository
	 *
	 * @var \In2\Femanager\Domain\Repository\UserGroupRepository
	 * @inject
	 */
	protected $userGroupRepository;

	/**
	 * logRepository
	 *
	 * @var \In2\Femanager\Domain\Repository\LogRepository
	 * @inject
	 */
	protected $logRepository;

	/**
	 * configurationManager
	 *
	 * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManager
	 * @inject
	 */
	protected $configurationManager;

	/**
	 * objectManager
	 *
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 * @inject
	 */
	protected $objectManager;

	/**
	 * Content Object
	 *
	 * @var object
	 */
	public $cObj;

	/**
	 * Return current logged in fe_user
	 *
	 * @return query object
	 */
	public function getCurrentUser() {
		$user = $this->userRepository->findByUid($GLOBALS['TSFE']->fe_user->user['uid']);
		return $user;
	}

	/**
	 * Set object properties from forceValues in TypoScript
	 *
	 * @param $object
	 * @param $settings
	 * @param $cObj
	 * @return $object
	 */
	public function forceValues($object, $settings, $cObj) {
		foreach ((array) $settings as $field => $config) {
			if (stristr($field, '.')) {
				continue;
			}

			// value to set
			$value = $cObj->cObjGetSingle($settings[$field], $settings[$field . '.']);

			if ($field == 'usergroup') {
				// need objectstorage for usergroup field
				$object->removeAllUsergroups();
				$values = GeneralUtility::trimExplode(',', $value, 1);
				foreach ($values as $usergroupUid) {
					$usergroup = $this->userGroupRepository->findByUid($usergroupUid);
					$object->addUsergroup($usergroup);
				}
			} else {
				// set value
				if (method_exists($object, 'set' . ucfirst($field))) {
					$object->{'set' . ucfirst($field)}($value);
				}
			}
		}
		return $object;
	}

	/**
	 * Overwrite usergroups from user by flexform settings
	 *
	 * @param $object
	 * @param $settings
	 * @return $object
	 */
	public function overrideUserGroup($object, $settings) {
		if (empty($settings['new']['overrideUserGroup'])) {
			return $object;
		}

		// for each selected usergroup in the flexform
		$object->removeAllUsergroups();
		foreach (GeneralUtility::trimExplode(',', $settings['new']['overrideUserGroup'], 1) as $usergroupUid) {
			$usergroup = $this->userGroupRepository->findByUid($usergroupUid);
			$object->addUsergroup($usergroup);
		}

		return $object;
	}

	/**
	 * Upload file from $_FILES['qqfile']
	 *
	 * @return mixed	false or file.png
	 */
	public function uploadFile() {

		if (!is_array($_FILES['qqfile'])) {
			return false;
		}

		// Check extension
		if (empty($_FILES['qqfile']['name']) || !\In2\Femanager\Utility\Div::checkExtension($_FILES['qqfile']['name'])) {
			return false;
		}

		// create new filename and upload it
		$basicFileFunctions = $this->objectManager->get('TYPO3\CMS\Core\Utility\File\BasicFileUtility');
		$newFile = $basicFileFunctions->getUniqueName($_FILES['qqfile']['name'], GeneralUtility::getFileAbsFileName('uploads/pics/'));
		if (GeneralUtility::upload_copy_move($_FILES['qqfile']['tmp_name'], $newFile)) {
			$fileInfo = pathinfo($newFile);
			return $fileInfo['basename'];
		}

		return false;
	}

	/**
	 * Check extension of given filename
	 *
	 * @param \string		Filename like (upload.png)
	 * @return \bool		If Extension is allowed
	 */
	public static function checkExtension($filename) {
		$extensionList = 'jpg,jpeg,png,gif,bmp'; // TODO: put list into TypoScript (no spaces allowed)
		$fileInfo = pathinfo($filename);

		if (!empty($fileInfo['extension']) && GeneralUtility::inList($extensionList, $fileInfo['extension'])) {
			return true;
		}
		return false;
	}

	/**
	 * Hash a password from $user->getPassword()
	 *
	 * @param \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $user
	 * @param \string $method		"md5" or "sha1"
	 * @return void
	 */
	public static function hashPassword(&$user, $method) {
		if ($method == 'md5') {
			$user->setPassword(md5($user->getPassword()));
		}
		if ($method == 'sha1') {
			$user->setPassword(sha1($user->getPassword()));
		}
	}

	/**
	 * Checks if object was changed or not
	 *
	 * @param $object
	 * @return \bool
	 */
	public static function isDirtyObject($object) {
		foreach ($object->_getProperties() as $propertyName => $propertyValue) {
			if ($object->_isDirty($propertyName)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get changed properties (compare two objects with same getter methods)
	 *
	 * @param $object1			Old, stored object
	 * @param $object2			Changed object
	 * @return \array
	 * 			[firstName][old] = Alex
	 * 			[firstName][new] = Alexander
	 */
	public static function getDirtyPropertiesFromObject($object1, $object2) {
		$dirtyProperties = array();
		$ignoreProperties = array(
			'txFemanagerChangerequest',
			'ignoreDirty',
			'lastlogin'
		);
		foreach ($object1->_getProperties() as $propertyName => $propertyValue) {
			if (!method_exists($object2, 'get' . ucfirst($propertyName)) || in_array($propertyName, $ignoreProperties)) {
				continue;
			}
			if (!is_object($propertyValue)) { // ignore usergroup
				if ($propertyValue != $object2->{'get' . ucfirst($propertyName)}()) {
					$dirtyProperties[$propertyName]['old'] = $propertyValue;
					$dirtyProperties[$propertyName]['new'] = $object2->{'get' . ucfirst($propertyName)}();
				}
			} else {
				$titlesOld = self::implodeObjectStorageOnProperty($propertyValue);
				$titlesNew = self::implodeObjectStorageOnProperty($object2->{'get' . ucfirst($propertyName)}());
				if ($titlesOld != $titlesNew) {
					$dirtyProperties[$propertyName]['old'] = $titlesOld;
					$dirtyProperties[$propertyName]['new'] = $titlesNew;
				}
			}
		}
		return $dirtyProperties;
	}

	/**
	 * Implode subjobjects on a property (example for usergroups: "ug1, ug2, ug3")
	 *
	 * @param \object $objectStorage
	 * @param \string $property
	 * @param \string $glue
	 * @return \string
	 */
	public static function implodeObjectStorageOnProperty($objectStorage, $property = 'uid', $glue = ', ') {
		$value = '';
		foreach ($objectStorage as $object) {
			if (method_exists($object, 'get' . ucfirst($property))) {
				$value .= $object->{'get' . ucfirst($property)}();
				$value .= $glue;
			}
		}
		return substr($value, 0, (strlen($glue) * -1));
	}

	/**
	 * Determine if supplied string is a valid MD5 Hash
	 *
	 * @param string $md5 String to validate
	 * @return boolean
	 */
	public static function isMd5($md5) {
		return !empty($md5) && preg_match('/^[a-f0-9]{32}$/', $md5);
	}

	/**
	 * @param \string $title												Title to log
	 * @param \int $state													State to log
	 * @param \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $user			Related User
	 * @return void
	 */
	public function log($title, $state, \TYPO3\CMS\Extbase\Domain\Model\FrontendUser $user) {
		// Disable Log
		$confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['femanager']);
		if (!empty($confArr['disableLog'])) {
			return;
		}

		// Create Log
		$log = $this->objectManager->get('In2\Femanager\Domain\Model\Log');
		$log->setTitle($title);
		$log->setState($state);
		$log->setUser($user);
		$this->logRepository->add($log);
	}

	/**
	 * Create Hash from String and TYPO3 Encryption Key (if available)
	 *
	 * @param \string $string			Any String to hash
	 * @param \int $length				Hash Length
	 * @return \string $hash			Hashed String
	 */
	public static function createHash($string, $length = 10) {
		if (!empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'])) {
			$hash = GeneralUtility::shortMD5($string . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
		} else {
			$hash = GeneralUtility::shortMD5($string);
		}
		return $hash;
	}

	/**
	 * Create array for sending mails
	 *
	 * @param \string $emailString			String with separated emails (splitted by \n)
	 * @param \string $name
	 * @return \array $mailArray
	 */
	public static function makeEmailArray($emailString, $name = 'receiver') {
		$emails = GeneralUtility::trimExplode("\n", $emailString, 1);
		$mailArray = array();
		foreach ($emails as $email) {
			if (!GeneralUtility::validEmail($email)) {
				continue;
			}
			$mailArray[$email] = $name;
		}
		return $mailArray;
	}

	/**
	 * Read values between brackets
	 *
	 * @param \string $value
	 * @return \string
	 */
	public static function getValuesInBrackets($value = 'test(1,2,3)') {
		preg_match_all( '/\(.*?\)/i', $value, $result);
		return str_replace(array('(', ')'), '', $result[0][0]);
	}

	/**
	 * SendPost - Send values via curl to target
	 *
	 * @param \In2\Femanager\Domain\Model\User $user User properties
	 * @param array $config TypoScript Settings
	 * @param object $contentObject cObj
	 * @return void
	 */
	public static function sendPost($user, $config, $contentObject) {
		// stop if turned off
		if (!$contentObject->cObjGetSingle($config['new.']['sendPost.']['_enable'], $config['new.']['sendPost.']['_enable.'])) {
			return;
		}

		$properties = $user->_getCleanProperties(); // get properties from user
		$contentObject->start($properties); // push properties to TypoScript to get used with .field
		$curl = array(
			'url' => $config['new.']['sendPost.']['targetUrl'],
			'data' => $contentObject->cObjGetSingle($config['new.']['sendPost.']['data'], $config['new.']['sendPost.']['data.']),
			'properties' => $properties
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $curl['url']);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $curl['data']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_exec($ch);
		curl_close($ch);

		// Debug Output
		if ($config['new.']['sendPost.']['debug']) {
			GeneralUtility::devLog('femanager sendpost values', 'femanager', 0, $curl);
		}
	}

	/**
	 * Generate and send Email
	 *
	 * @param \string $template					Template file in Templates/Email/
	 * @param \array $receiver					Combination of Email => Name
	 * @param \array $sender					Combination of Email => Name
	 * @param \string $subject					Mail subject
	 * @param \array $variables					Variables for assignMultiple
	 * @param \array $typoScript				Add TypoScript to overwrite values
	 * @return \bool							Mail was sent?
	 */
	public function sendEmail($template, $receiver, $sender, $subject, $variables = array(), $typoScript = array()) {
		// config
		$email = $this->objectManager->get('\TYPO3\CMS\Core\Mail\MailMessage');
		$this->cObj = $this->configurationManager->getContentObject();
		if (!$this->cObj->cObjGetSingle($typoScript['_enable'], $typoScript['_enable.'])) { // stop mail sending if turned off via TypoScritp
			return FALSE;
		}

		/**
		 * Generate Email Body
		 */
		$extbaseFrameworkConfiguration = $this->configurationManager->getConfiguration(\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
		$templatePathAndFilename = GeneralUtility::getFileAbsFileName($extbaseFrameworkConfiguration['view']['templateRootPath']) . 'Email/' . ucfirst($template) . '.html';
		$emailView = $this->objectManager->get('Tx_Fluid_View_StandaloneView');
		$emailView->getRequest()->setControllerExtensionName('Femanager');
		$emailView->getRequest()->setPluginName('Pi1');
		$emailView->getRequest()->setControllerName('New');
		$emailView->setTemplatePathAndFilename($templatePathAndFilename);
		$emailView->setPartialRootPath(GeneralUtility::getFileAbsFileName($extbaseFrameworkConfiguration['view']['partialRootPath']));
		$emailView->setLayoutRootPath(GeneralUtility::getFileAbsFileName($extbaseFrameworkConfiguration['view']['layoutRootPath']));
		$emailView->assignMultiple($variables);
		$emailBody = $emailView->render();

		/**
		 * Generate and send Email
		 */
		$email
			->setTo($receiver)
			->setFrom($sender)
			->setSubject($subject)
			->setCharset($GLOBALS['TSFE']->metaCharset)
			->setBody($emailBody, 'text/html');

		// overwrite email receiver
		if (
			$this->cObj->cObjGetSingle($typoScript['receiver.']['email'], $typoScript['receiver.']['email.']) &&
			$this->cObj->cObjGetSingle($typoScript['receiver.']['name'], $typoScript['receiver.']['name.'])
		) {
			$email->setTo(
				array(
					$this->cObj->cObjGetSingle($typoScript['receiver.']['email'], $typoScript['receiver.']['email.']) =>
					$this->cObj->cObjGetSingle($typoScript['receiver.']['name'], $typoScript['receiver.']['name.'])
				)
			);
		}

		// overwrite email sender
		if (
			$this->cObj->cObjGetSingle($typoScript['sender.']['email'], $typoScript['sender.']['email.']) &&
			$this->cObj->cObjGetSingle($typoScript['sender.']['name'], $typoScript['sender.']['name.'])
		) {
			$email->setFrom(
				array(
					$this->cObj->cObjGetSingle($typoScript['sender.']['email'], $typoScript['sender.']['email.']) =>
					$this->cObj->cObjGetSingle($typoScript['sender.']['name'], $typoScript['sender.']['name.'])
				)
			);
		}

		// overwrite email subject
		if ($this->cObj->cObjGetSingle($typoScript['subject'], $typoScript['subject.'])) {
			$email->setSubject($this->cObj->cObjGetSingle($typoScript['subject'], $typoScript['subject.']));
		}

		// overwrite email CC receivers
		if ($this->cObj->cObjGetSingle($typoScript['cc'], $typoScript['cc.'])) {
			$email->setCc($this->cObj->cObjGetSingle($typoScript['cc'], $typoScript['cc.']));
		}

		// overwrite email priority
		if ($this->cObj->cObjGetSingle($typoScript['priority'], $typoScript['priority.'])) {
			$email->setPriority($this->cObj->cObjGetSingle($typoScript['priority'], $typoScript['priority.']));
		}

		// add attachments from typoscript
		if ($this->cObj->cObjGetSingle($typoScript['attachments'], $typoScript['attachments.'])) {
			$files = GeneralUtility::trimExplode(',', $this->cObj->cObjGetSingle($typoScript['attachments'], $typoScript['attachments.']), 1);
			foreach ($files as $file) {
				$email->attach(\Swift_Attachment::fromPath($file));
			}
		}

		$email->send();

		return $email->isSent();
	}
}
?>