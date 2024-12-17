-- Add mail templates
INSERT INTO "#__mail_templates" ("template_id", "extension", "language", "subject", "body", "htmlbody", "attachments", "params") VALUES
('plg_task_reviewcontentnotification.not_modified_mail', 'plg_task_reviewcontentnotification', '', 'PLG_TASK_REVIEWCONTENTNOTIFICATION_NOT_MODIFIED_MAIL_SUBJECT', 'PLG_TASK_REVIEWCONTENTNOTIFICATION_NOT_MODIFIED_MAIL_BODY', '', '', '{"tags": ["title", "public_url", "sitename", "url", "last_modified", "created", "edit_url", "backend_url", "date_modifier"]}');
