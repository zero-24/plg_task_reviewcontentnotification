# ReviewContentNotification Plugin

This Joomla plugin checks whether an existing content item has not been modified for an given time to inform the author and ask him to confirm the content of the article.

## Configuration

### Initial setup the plugin

- [Download the latest version of the plugin](https://github.com/zero-24/plg_task_reviewcontentnotification/releases/latest)
- Install the plugin using `Upload & Install`
- Enable the plugin `Task - ReviewContentNotification` from the plugin manager
- Setup the new Task Plugin `System -> Scheduled Tasks -> New -> ReviewContentNotification`

Now the inital setup is completed, please make sure that the cron has been fully setup in the best cases it should use the WebCron setting.

## Issues / Pull Requests

You have found an Issue, have a question or you would like to suggest changes regarding this extension?
[Open an issue in this repo](https://github.com/zero-24/plg_task_reviewcontentnotification/issues/new) or submit a pull request with the proposed changes.

## Translations

You want to translate this extension to your own language? Check out my [Crowdin Page for my Extensions](https://joomla.crowdin.com/zero-24) for more details. Feel free to [open an issue here](https://github.com/zero-24/plg_task_reviewcontentnotification/issues/new) on any question that comes up.

## Joomla! Extensions Directory (JED)

This plugin can also been found in the Joomla! Extensions Directory: [ReviewContentNotification by zero24](https://extensions.joomla.org/extension/reviewcontentnotification/)

## Special Thanks

Alain Rihs - @AlainRnet

For giving me the inspiration for the plugin and the initial tests. Thanks 👍

## Release steps

- `build/build.sh`
- `git commit -am 'prepare release ReviewContentNotification 1.0.1'`
- `git tag -s '1.0.1' -m 'ReviewContentNotification 1.0.1'`
- `git push origin --tags`
- create the release on GitHub
- `git push origin master`

## Crowdin

### Upload new strings

`crowdin upload sources`

### Download translations

`crowdin download --skip-untranslated-files --ignore-match`
