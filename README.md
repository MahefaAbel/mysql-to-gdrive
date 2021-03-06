# MySQL to GDrive

## Description
Tools for backup active mysql database to Google Drive automatically, with PHP

## Guide

- Clone this repo
- Composer update
- Move the `Drive.php` to `./vendor/google/apiclient/src/Google/Service` (replace the existing)
- Create API creadentials by following this [step](https://developers.google.com/drive/v3/web/quickstart/php#step_1_turn_on_the_api_name)
- Put the `client.json` as `client_secret.json` in this root directory
- Create `.env` based on `.env.example` file as your system config
