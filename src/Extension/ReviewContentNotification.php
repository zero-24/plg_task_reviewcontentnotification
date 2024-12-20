<?php

/**
 * ReviewContentNotification Task Plugin
 *
 * @copyright  Copyright (C) 2024 Tobias Zulauf All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or later
 */

namespace Joomla\Plugin\Task\ReviewContentNotification\Extension;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\Exception\MailDisabledException;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use PHPMailer\PHPMailer\Exception as phpMailerException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A task plugin. Checks for articles which should be revied after a give time and sends an eMail to the author once an article has been found
 *
 * @since 1.0.0
 */
final class ReviewContentNotification extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var string[]
     * @since 1.0.0
     */
    private const TASKS_MAP = [
        'check.reviewcontent' => [
            'langConstPrefix' => 'PLG_TASK_REVIEWCONTENTNOTIFICATION_SEND',
            'method'          => 'checkReviewContentNotification',
            'form'            => 'sendForm',
        ],
    ];

    /**
     * @var boolean
	 *
     * @since 1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Method to check and send the notification for the articles
     *
     * @param   ExecuteTaskEvent  $event  The `onExecuteTask` event.
     *
     * @return integer  The routine exit code.
     *
     * @since  1.0.0
     * @throws \Exception
     */
    private function checkReviewContentNotification(ExecuteTaskEvent $event): int
    {
        // Load the parameters
		$dateModifier            = $event->getArgument('params')->date_modifier ?? '2';
		$dateModifierType        = $event->getArgument('params')->date_modifier_type ?? 'years';
		$seccondNotification     = $event->getArgument('params')->seccond_notification ?? 0;
		$seccondDateModifier     = $event->getArgument('params')->seccond_date_modifier ?? '2';
		$seccondDateModifierType = $event->getArgument('params')->seccond_date_modifier_type ?? 'months';
		$categoriesToCheck       = $event->getArgument('params')->categories_to_check ?? [];
        $limitItemsPerRun        = $event->getArgument('params')->limit_items_per_run ?? 20;
		$specificEmail           = $event->getArgument('params')->email ?? '';
        $forcedLanguage          = $event->getArgument('params')->language_override ?? 'user';

		// Get all articles to send notifications about
		$articlesToNotify = $this->getContentThatShouldBeNotified($dateModifier, $categoriesToCheck, $dateModifierType, $limitItemsPerRun);

        // If there are no articles to send notifications to we don't have to notify anyone about anything. This is NOT a duplicate check.
        if (empty($articlesToNotify) || $articlesToNotify === false)
		{
			$this->logTask('ReviewContentNotification end');

            return Status::OK;
        }

        // Build the Backend URL
        $baseURL  = Uri::base();
        $baseURL  = rtrim($baseURL, '/');
        $baseURL .= (substr($baseURL, -13) !== 'administrator') ? '/administrator/' : '/';
        $baseURL .= 'index.php';
        $backendURL = new Uri($baseURL);

        /**
         * Some third party security solutions require a secret query parameter to allow log in to the administrator
         * backend of the site. The link generated above will be invalid and could probably block the user out of their
         * site, confusing them (they can't understand the third party security solution is not part of Joomla! proper).
         * So, we're calling the onBuildAdministratorLoginURL system plugin event to let these third party solutions
         * add any necessary secret query parameters to the URL. The plugins are supposed to have a method with the
         * signature:
         *
         * public function onBuildAdministratorLoginURL(Uri &$uri);
         *
         * The plugins should modify the $uri object directly and return null.
         */
        $this->getApplication()->triggerEvent('onBuildAdministratorLoginURL', [&$backendURL]);

        /*
         * Load the appropriate language. We try to load English (UK), the current user's language and the forced
         * language preference, in this order. This ensures that we'll never end up with untranslated strings in the
         * update email which would make Joomla! seem bad. So, please, if you don't fully understand what the
         * following code does DO NOT TOUCH IT. It makes the difference between a hobbyist CMS and a professional
         * solution!
         */
        $jLanguage = $this->getApplication()->getLanguage();
        $jLanguage->load('plg_task_reviewcontentnotification', JPATH_ADMINISTRATOR, 'en-GB', true, true);
        $jLanguage->load('plg_task_reviewcontentnotification', JPATH_ADMINISTRATOR, null, true, false);

		$currentSiteLanguage = $this->getApplication()->get('language', 'en-GB');

        foreach ($articlesToNotify as $articleId => $articleValue)
		{
			// Let's find out the email addresses to notify
			$recipients = $this->getRecipientsArray($specificEmail, $currentSiteLanguage, $articleValue, $forcedLanguage);

			if (empty($recipients))
			{
				$this->logTask('Empty recipients for article id: ' . $articleValue->id);

				continue;
			}

			// Build the content URL
			$contentUrl = RouteHelper::getArticleRoute($articleValue->id, $articleValue->catid, $articleValue->language);

            // Send the emails to the recipients
            foreach ($recipients as $recipient)
			{
				// Loading the preferred (forced) language or the site language
                $jLanguage->load('plg_task_reviewcontentnotification', JPATH_ADMINISTRATOR, $recipient['language'], true, false);

                // Replace merge codes with their values
                $substitutions = [
                    'title'         => $articleValue->title,
                    'public_url'    => Route::link('site', $contentUrl, true, 0, true),
                    'sitename'      => $this->getApplication()->get('sitename'),
                    'url'           => str_replace('/administrator', '', Uri::base()),
                    'last_modified' => Factory::getDate($articleValue->modified)->format(Text::_('DATE_FORMAT_FILTER_DATETIME')),
                    'created'       => Factory::getDate($articleValue->created)->format(Text::_('DATE_FORMAT_FILTER_DATETIME')),
                    'edit_url'      => Route::link('site', $contentUrl . '&task=article.edit&a_id=' . $articleValue->id . '&return=' . base64_encode(Uri::base()), true, 0, true),
                    'backend_url'   => $backendURL->toString(),
                    'date_modifier' => $dateModifier,
                ];

                try
				{
                    $mailer = new MailTemplate('plg_task_reviewcontentnotification.not_modified_mail', $recipient['language']);
                    $mailer->addRecipient($recipient['email']);
                    $mailer->addTemplateData($substitutions);
                    $mailer->send();
                }
				catch (MailDisabledException | phpMailerException $exception)
				{
                    try
					{
                        $this->logTask($jLanguage->_($exception->getMessage()));
                    }
					catch (\RuntimeException $exception)
					{
                        return Status::KNOCKOUT;
                    }
                }
            }

            $this->addArticleToTheLogTable($articleValue->id);

			// SECOND NOTIFICATIONS

			// Check whether we should send seccond eMails
			if (!$seccondNotification)
			{
				$this->logTask('ReviewContentNotification end');

				return Status::OK;
			}

			// Check whether we do have seccond emails to send
			$seccondNotificataionArticles = $this->getArticlesToSendSeccondNotificationFor($categoriesToCheck, $limit);

			if (empty($seccondNotificataionArticles))
			{
				$this->logTask('ReviewContentNotification end');

				return Status::OK;
			}

			// Collect information and send the seccond eMails
			foreach ($seccondNotificataionArticles as $key => $seccondNotificationValue)
			{
				$lastNotificationDate = new Date($this->getLastNotificationDateByArticleId($seccondNotificationValue->id));
				$articleLastModifed = new Date($seccondNotificationValue->modified);

				if ($articleLastModifed > $lastNotificationDate)
				{
					// The article has been modified between the last notification and today, remove it from the log table and continue
					$this->removeArticleIdFromLogTabele($seccondNotificationValue->id);

					continue;
				}

				// Check whether the seccond email has been send already
				if ($this->hasTheSeccondMailBeenSendAlready($seccondNotificationValue->id))
				{
					continue;
				}

				// Let's find out the email addresses to notify
				$recipients = $this->getRecipientsArray($specificEmail, $currentSiteLanguage, $seccondNotificationValue, $forcedLanguage);

				if (empty($recipients))
				{
					$this->logTask('Empty recipients for article id: ' . $articleValue->id);

					continue;
				}

				// Build the content URL
				$contentUrl = RouteHelper::getArticleRoute($seccondNotificationValue->id, $seccondNotificationValue->catid, $seccondNotificationValue->language);

				// Send the emails to the recipients
				foreach ($recipients as $recipient)
				{
					// Loading the preferred (forced) language or the site language
					$jLanguage->load('plg_task_reviewcontentnotification', JPATH_ADMINISTRATOR, $recipient['language'], true, false);

					// Replace merge codes with their values
					$substitutions = [
						'title'         => $seccondNotificationValue->title,
						'public_url'    => Route::link('site', $contentUrl, true, 0, true),
						'sitename'      => $this->getApplication()->get('sitename'),
						'url'           => str_replace('/administrator', '', Uri::base()),
						'last_modified' => Factory::getDate($seccondNotificationValue->modified)->format(Text::_('DATE_FORMAT_FILTER_DATETIME')),
						'created'       => Factory::getDate($seccondNotificationValue->created)->format(Text::_('DATE_FORMAT_FILTER_DATETIME')),
						'edit_url'      => Route::link('site', $contentUrl . '&task=article.edit&a_id=' . $seccondNotificationValue->id . '&return=' . base64_encode(Uri::base()), true, 0, true),
						'backend_url'   => $backendURL->toString(),
						'date_modifier' => $dateModifier,
					];

					try
					{
						$mailer = new MailTemplate('plg_task_reviewcontentnotification.not_modified_mail', $recipient['language']);
						$mailer->addRecipient($recipient['email']);
						$mailer->addTemplateData($substitutions);
						$mailer->send();
					}
					catch (MailDisabledException | phpMailerException $exception)
					{
						try
						{
							$this->logTask($jLanguage->_($exception->getMessage()));
							$this->logTask('ReviewContentNotification end');

							return Status::OK;
						}
						catch (\RuntimeException $exception)
						{
							return Status::KNOCKOUT;
						}
					}
				}

				// The article has been processed the seccond time we can mark it now with the logging database
				$this->markSeccondEmailAsSendinLogTable($seccondNotificationValue->id);
			}
        }

        $this->logTask('ReviewContentNotification end');

        return Status::OK;
    }

    /**
     * Returns the Super Users email information. If you provide a comma separated $email list
     * we will check that these emails do belong to Super Users and that they have not blocked
     * system emails.
     *
     * @param   null|string  $email  A list of Super Users to email
     *
     * @return  array  The list of Super User emails
     *
     * @since   1.0.0
     */
    private function getSuperUsers($email = null)
    {
        $db     = $this->getDatabase();
        $emails = [];

        // Convert the email list to an array
        if (!empty($email)) {
            $temp   = explode(',', $email);

            foreach ($temp as $entry) {
                $emails[] = trim($entry);
            }

            $emails = array_unique($emails);
        }

        // Get a list of groups which have Super User privileges
        $ret = [];

        try {
            $rootId    = Table::getInstance('Asset')->getRootId();
            $rules     = Access::getAssetRules($rootId)->getData();
            $rawGroups = $rules['core.admin']->getData();
            $groups    = [];

            if (empty($rawGroups)) {
                return $ret;
            }

            foreach ($rawGroups as $g => $enabled) {
                if ($enabled) {
                    $groups[] = $g;
                }
            }

            if (empty($groups)) {
                return $ret;
            }
        } catch (\Exception $exc) {
            return $ret;
        }

        // Get the user IDs of users belonging to the SA groups
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('user_id'))
                ->from($db->quoteName('#__user_usergroup_map'))
                ->whereIn($db->quoteName('group_id'), $groups);

            $db->setQuery($query);
            $userIDs = $db->loadColumn(0);

            if (empty($userIDs)) {
                return $ret;
            }
        } catch (\Exception $exc) {
            return $ret;
        }

        // Get the user information for the Super Administrator users
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'username', 'email']))
                ->from($db->quoteName('#__users'))
                ->whereIn($db->quoteName('id'), $userIDs)
                ->where($db->quoteName('block') . ' = 0')
                ->where($db->quoteName('sendEmail') . ' = 1');

            if (!empty($emails)) {
                $lowerCaseEmails = array_map('strtolower', $emails);
                $query->whereIn('LOWER(' . $db->quoteName('email') . ')', $lowerCaseEmails, ParameterType::STRING);
            }

            $db->setQuery($query);
            $ret = $db->loadObjectList();
        } catch (\Exception $exc) {
            return $ret;
        }

        return $ret;
    }

    /**
     * Method to return the content artices that we need to notify the created users for
     *
	 * @param  int     $dateModifier       The date modifier setting from the task needs to be resolved to the actuall value
	 * @param  array   $categoriesToCheck  The categories that should be checked
	 * @param  string  $dateModifierType   The date modifier type like days, months, years
	 * @param  int     $limit              Limit the result list for this task run
	 *
     * @return array  An array of content articles that we need to notify the created users
     *
     * @since  1.0.0
     */
    private function getContentThatShouldBeNotified(int $dateModifier = 2, array $categoriesToCheck = [], $dateModifierType = 'years', $limit = 20)
    {
        // Set the date to the base time for checking the item
		$minimumDatetime = new Date('now');
		$minimumDatetime->modify('-' . $dateModifier . ' ' . $dateModifierType);

		if (empty($categoriesToCheck))
        {
            return false;
        }

		// First get all items from the already send table
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['article_id']))
			->from($db->quoteName('#__content_reviewcontentnotification'));

		$db->setQuery($query);
		$alreadySendToArticleIds = $db->loadColumn();

		// Check the Content Items that should be informed
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'created', 'modified', 'catid', 'created_by', 'state', 'language']))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('modified') . ' < :minimum_datetime')
			// Get only published articles
			->whereIn($db->quoteName('state'), ['1'])
			// Get only artilces from a given category
			->whereIn($db->quoteName('catid'), $categoriesToCheck)
			->setLimit($limit)
			->bind(':minimum_datetime', $minimumDatetime->toSQL(), ParameterType::STRING);

        // Filter the select if we have any items already send
        if (!empty($alreadySendToArticleIds))
        {
            $query->whereNotIn($db->quoteName('id'), $alreadySendToArticleIds);
        }

        $db->setQuery($query);

        // Retrun the result
        return $db->loadObjectList();
    }

    /**
     * Add the current article to the log table with the current and the date for the seccond notification
     *
	 * @param  int     $articleId                 The ID of the article to add to the table
     * @param  int     $seccondDateModifier       The date modifier setting for the seccond email from the task needs to be resolved to the actual value
	 * @param  string  $seccondDateModifierType   The date modifier type for the seccond email like days, months, years
	 *
     * @return array  An array of content articles that we need to notify the created users
     *
     * @since  1.0.1
     */
    private function addArticleToTheLogTable($articleId, $seccondDateModifier, $seccondDateModifierType)
    {
        $today = new Date('now');
        $seccondNotification = new Date('now');
        $seccondNotification->modify('+' . $seccondDateModifier . ' ' . $seccondDateModifierType);

        $articleLogEntry = new stdClass();
        $articleLogEntry->article_id = $articleId;
        $articleLogEntry->last_notification = $today->toSQL();
        $articleLogEntry->seccond_notification = $seccondNotification->toSQL();

        return $db->insertObject('#__content_reviewcontentnotification', $articleLogEntry);
    }

    /**
     * Method to return the content artices that we need to notify the seccond time
     *
	 * @param  array   $categoriesToCheck  The categories that should be checked
	 * @param  int     $limit              Limit the result list for this task run
	 *
     * @return array  An array of content articles that we need to notify the created users
     *
     * @since  1.0.1
     */
	private function getArticlesToSendSeccondNotificationFor(array $categoriesToCheck = [], $limit)
	{
		$today = new Date('now');

		// First get all items from the already send table
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['article_id']))
			->from($db->quoteName('#__content_reviewcontentnotification'))
			->where($db->quoteName('seccond_notification') . ' < :today')
			->setLimit($limit)
			->bind(':today', $today->toSQL(), ParameterType::STRING);

		$db->setQuery($query);
		$alreadySendToArticleIds = $db->loadColumn();

		// Check the Content Items that should be informed
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'title', 'created', 'modified', 'catid', 'created_by', 'state', 'language']))
			->from($db->quoteName('#__content'))
			// Get only published articles
			->whereIn($db->quoteName('state'), ['1'])
			// Get only artilces from a given category
			->whereIn($db->quoteName('catid'), $categoriesToCheck)
			->setLimit($limit);

        // Filter the select if we have any items already send
        if (!empty($alreadySendToArticleIds))
        {
            $query->whereIn($db->quoteName('id'), $alreadySendToArticleIds);
        }

		$db->setQuery($query);

		// Retrun the result
		return $db->loadObjectList();
	}

    /**
     * Method to return the last notification date for a given article ID
     *
	 * @param  int   $articleId  The article ID we want to check
	 *
     * @return string  The last notification date for the given article ID
     *
     * @since  1.0.1
     */
	private function getLastNotificationDateByArticleId($articleId)
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['last_notification']))
			->from($db->quoteName('#__content_reviewcontentnotification'))
			->where($db->quoteName('id') . ' = :id')
			->bind(':id', $articleId, ParameterType::INTEGER);

		$db->setQuery($query);

		return $db->loadResult();
	}

    /**
     * Method to return the last notification date for a given article ID
     *
	 * @param  string     $specificEmail        The configuration setting with the specific emails
	 * @param  string     $currentSiteLanguage  The current defaut site language
	 * @param  stdClass   $articleObject        The current article object from the database
	 * @param  string     $forcedLanguage       The language to force on the eMail
	 *
     * @return string  The last notification date for the given article ID
     *
     * @since  1.0.1
     */
	private function getRecipientsArray($specificEmail, $currentSiteLanguage, $articleObject, $forcedLanguage): array
	{
		$recipients = [];

		if (!empty($specificEmail))
		{
			$specificEmails = explode(',', $specificEmail);

			foreach ($specificEmails as $key => $value)
			{
				$recipients[] = ['email' => $value, 'language' => $currentSiteLanguage];
			}
		}

		// Add the author URL for article
		if (!empty($articleObject->created_by))
		{
			// Take the language from the user or the forcedlanguage based on the configuration
			if ($forcedLanguage === 'user')
			{
				$recipients[] = [
					'email' => Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($articleObject->created_by)->email,
					'language' => Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($articleObject->created_by)->getParam('language', $currentSiteLanguage)
				];
			}
			else
			{
				$recipients[] = [
					'email' => Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($articleObject->created_by)->email,
					'language' => empty($forcedLanguage) ? $currentSiteLanguage : $forcedLanguage
				];
			}
		}

		// Add the super users to when we have not got any recipients until now
		if (empty($recipients))
		{
			$superUsers = $this->getSuperUsers();

			foreach ($superUsers as $superUser)
			{
				// Take the language from the user or the forcedlanguage based on the configuration
				if ($forcedLanguage === 'user')
				{
					$recipients[] = [
						'email' => $superUser->email,
						'language' => Factory::getContainer()->get(
							UserFactoryInterface::class)->loadUserById($superUser->id)->getParam('language', $currentSiteLanguage)
						];
				}
				else
				{
					$recipients[] = ['email' => $superUser->email, 'language' => empty($forcedLanguage) ? $currentSiteLanguage : $forcedLanguage];
				}
			}
		}
	}

    /**
     * Method to delete the given artilce ID from the logging table
     *
	 * @param  int   $articleId  The article ID we want to check
	 *
     * @return  void
     *
     * @since  1.0.1
     */
	private function removeArticleIdFromLogTabele($articleId)
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__content_reviewcontentnotification'))
			->where($db->quoteName('id') . ' = :id')
			->bind(':id', $articleId, ParameterType::INTEGER);

		$db->setQuery($query);

		return $db->execute();
	}

    /**
     * Mark the seccond notification as send within the log table
     *
	 * @param  int   $articleId  The article ID we want to check
	 *
     * @return  bool
     *
     * @since  1.0.1
     */
	private function markSeccondEmailAsSendinLogTable($articleId)
	{
		$today = new Date('now');

        $articleLogEntry = new stdClass();
        $articleLogEntry->article_id = $articleId;
        $articleLogEntry->seccond_notification_send = $today->toSQL();

        return $db->updateObject('#__content_reviewcontentnotification', $articleLogEntry);
	}

    /**
     * Method to check whether the seccond mail for the article has already been send
     *
	 * @param  int   $articleId  The article ID we want to check
	 *
     * @return  bool
     *
     * @since  1.0.1
     */
	private function hasTheSeccondMailBeenSendAlready($articleId): bool
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['seccond_notification_send']))
			->from($db->quoteName('#__content_reviewcontentnotification'))
			->where($db->quoteName('id') . ' = :id')
			->bind(':id', $articleId, ParameterType::INTEGER);

		$db->setQuery($query);

		$result = $db->loadResult();

		if (empty($result) || $result === null)
		{
			return false;
		}

		return true;
	}
}
