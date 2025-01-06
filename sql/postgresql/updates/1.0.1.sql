-- Add mail templates
INSERT INTO "#__mail_templates" ("template_id", "extension", "language", "subject", "body", "htmlbody", "attachments", "params") VALUES
('plg_task_reviewcontentnotification.seccond_notification_mail', 'plg_task_reviewcontentnotification', '', 'PLG_TASK_REVIEWCONTENTNOTIFICATION_SECCOND_NOTIFICATION_MAIL_SUBJECT', 'PLG_TASK_REVIEWCONTENTNOTIFICATION_SECCOND_NOTIFICATION_MAIL_BODY', '', '', '{"tags": ["title", "public_url", "sitename", "url", "last_modified", "created", "edit_url", "backend_url", "date_modifier"]}');
--
-- Table structure for table `#__content_reviewcontentnotification`
--
CREATE TABLE IF NOT EXISTS "#__content_reviewcontentnotification" (
  "article_id" bigint NOT NULL,
  "last_notification" timestamp without time zone NOT NULL,
  "seccond_notification" timestamp without time zone NOT NULL,
  "seccond_notification_send" timestamp without time zone,
  PRIMARY KEY ("article_id")
);
