# Why this is created

https://firebase.google.com/docs/cloud-messaging/migrate-v1

Firebase retired the old FCM API mid-year 2024. Very old projects are in trouble (Laravel 5.2), takes too much effort to upgrade the framework just for FCM. 
So i created this drop-in helper class for my own use. Feel free to share, modify, or fix bug.

Or if you want something that just work without installing dependencies, this is it. It uses good old `CURL()`.

# Laravel dependency
The code uses laravel's `env()` to load the config from your firebase-admin-credentials json file.

It also uses laravel's `storage_path()` to get the logfile path.

# Tests
```
$fcm = new FCMSender();
$fcm->send('topic', 'hello', 'world', 'default', null);
```
