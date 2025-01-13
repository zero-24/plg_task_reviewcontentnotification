-- Remove old version of the template
DELETE FROM `#__mail_templates` WHERE `template_id` = 'plg_task_reviewcontentnotification.second_notification_mail';

-- Add the new mail templates
INSERT IGNORE INTO `#__mail_templates` (`template_id`, `extension`, `language`, `subject`, `body`, `htmlbody`, `attachments`, `params`) VALUES
('plg_task_reviewcontentnotification.second_notification_mail', 'plg_task_reviewcontentnotification', '', 'PLG_TASK_REVIEWCONTENTNOTIFICATION_SECOND_NOTIFICATION_MAIL_SUBJECT', 'PLG_TASK_REVIEWCONTENTNOTIFICATION_SECOND_NOTIFICATION_MAIL_BODY', '', '', '{"tags": ["title", "public_url", "sitename", "url", "last_modified", "created", "edit_url", "backend_url", "date_modifier"]}');

-- Rename the column
ALTER TABLE `#__content_reviewcontentnotification` CHANGE COLUMN seccond_notification second_notification datetime;
ALTER TABLE `#__content_reviewcontentnotification` CHANGE COLUMN seccond_notification second_notification datetime;
