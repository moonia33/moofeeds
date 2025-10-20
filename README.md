moofeeds: Facebook, Google Ads and Newsman product feeds for PrestaShop 1.7–9.

<a href="https://github.com/moonia33/moofeeds/releases/latest/download/moofeeds.zip">
	<img alt="Download moofeeds.zip" src="https://img.shields.io/badge/download-moofeeds.zip-blue?style=for-the-badge">
</a>

Or use the direct link: [Download moofeeds.zip (latest release)](https://github.com/moonia33/moofeeds/releases/latest/download/moofeeds.zip)

Installation
- Copy the `moofeeds` folder to your `modules/` directory or install the zip from the Back Office.
- Configure from Modules > moofeeds.

Endpoints
- /feed/facebook.csv
- /feed/google-ads.csv
- /feed/newsman.csv
- /feed/cron?feed=facebook|googleads|newsman&size=1000&max_steps=3&token=...

Notes
- Feeds are served from cached files created by the cron endpoint.
- Token can be regenerated in the module settings page.

Author
- moonia — ramunas@inultimo.lt
