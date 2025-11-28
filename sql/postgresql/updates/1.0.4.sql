-- Add {LIST} to tags
UPDATE "#__mail_templates" SET "params" = '{"tags": ["title", "public_url", "sitename", "url", "last_modified", "created", "edit_url", "backend_url", "date_modifier","list"]}' WHERE "template_id" IN ('plg_task_reviewcontentnotification.not_modified_mail','plg_task_reviewcontentnotification.second_notification_mail');
