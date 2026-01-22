# pwalock
App to add some privacy to Nextcloud when running as PWA

## How it works?
Once installed and activated, user will have the opportunity to set a pin in the Personal settings section. The app functions only when Nextcloud is being run in PWA mode. The app will require pin code, while showing an overlay screen, when the PWA windows has been idle for certain time (as per settings). Same thing if the PWA windows is closed or reloaded, etc.
In case of several failures, pwalock will initiate the logout process.
