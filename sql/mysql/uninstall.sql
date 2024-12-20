DELETE FROM `#__mail_templates` WHERE `template_id` = 'plg_task_reviewcontentnotification.not_modified_mail';
DELETE FROM `#__mail_templates` WHERE `template_id` = 'plg_task_reviewcontentnotification.seccond_notification_mail';
DELETE TABLE `#__content_reviewcontentnotification`;
