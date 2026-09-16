<?php
define('OTP_DEV_MODE', true);            // true = OTP shown on screen
define('NOTIFY_DEV_MODE', true);         // true = emails only logged

define('SMTP_HOST','smtp.gmail.com');    // Brevo: smtp-relay.brevo.com
define('SMTP_USER','youremail@gmail.com');
define('SMTP_PASS','your16charapppassword');
define('SMTP_PORT',587);
define('SMTP_SECURE','tls');
define('MAIL_FROM','youremail@gmail.com');
define('MAIL_FROM_NAME','Deal360.shop');

define('MSG91_AUTH_KEY','YourMSG91AuthKey');
define('MSG91_OTP_TEMPLATE_ID','YourDLTTemplateID');

define('SEND_WELCOME',true); define('SEND_AD_STATUS',true); define('SEND_ENQUIRY',true);
define('SEND_CHAT',true);    define('SEND_PLAN',true);      define('SEND_WORKER_STATUS',true);
define('SEND_REVIEW',true);
define('CHAT_EMAIL_THROTTLE_MIN',5);
define('SITE_URL','http://localhost/deal360');   // → https://deal360.shop in production