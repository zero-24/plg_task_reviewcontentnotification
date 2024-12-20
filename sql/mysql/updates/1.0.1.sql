-- Add mail templates
INSERT INTO `#__mail_templates` (`template_id`, `extension`, `language`, `subject`, `body`, `htmlbody`, `attachments`, `params`) VALUES
('plg_task_reviewcontentnotification.seccond_notification_mail', 'plg_task_reviewcontentnotification', '', 'PLG_TASK_REVIEWCONTENTNOTIFICATION_SECCOND_NOTIFICATION_MAIL_SUBJECT', 'PLG_TASK_REVIEWCONTENTNOTIFICATION_SECCOND_NOTIFICATION_MAIL_BODY', '', '', '{"tags": ["title", "public_url", "sitename", "url", "last_modified", "created", "edit_url", "backend_url", "date_modifier"]}');

--
-- Table structure for table `#__content_reviewcontentnotification`
--
CREATE TABLE IF NOT EXISTS `#__content_reviewcontentnotification` (
  `article_id` int unsigned NOT NULL AUTO_INCREMENT,
  `last_notification` datetime NOT NULL,
  `seccond_notification` datetime NOT NULL,
  `seccond_notification_send` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`article_id`),
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;