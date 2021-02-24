# pi-hole-custo

This is my custom adaptation to Pi-Hole. There are two parts to this repository. The first part is a custom backend API endpoint for getting information from the database and munipulating records too. The second part is OAuth2 Sign in for the admin portal. 

## Example Usage

I use this in my organisation to firstly make certain device have to be 'authorized' before they can access the internet. This uses Pi-Hole groups and is set up that all devices that use Pi-hole are automatically put in a black list that blocks all internet traffic except for certain host. One of these hosts is web server that the user has to manually go to this address. This web server hosts a login that user then has to sign in with their network credentials. Once the user has signed-in, the IDP finds what group they are meant to part of and will then send it to the Custom Pi-Hole API to add the device to the group. If successful, the device will be able to access the internet.
NOTE: This Repository does not currently include the User Sign-in section. You will have the use the Custom API endpoint for added and removing users from groups.
The other thing I have included in this is an OAuth2 Sign in flow for the admin portal. This allows admins to sign into the admin portal to make changes without giving the Pi-Hole Admin Password out. In my organisation we use ADFS as our IDP of choice which provides SSO.

## Installation

You will need to place the following files in the following Pi-Hole Directories:

For the Custom API endpoint, you need to place the custom api in the following directory:
{html directory}/admin/scripts/pi-hole/php:
```bash
customapi.php
```

For the admin sign in, you will need to create a new directory:
{html directory}/auth/:
```bash
auth.php
oauth2-callback.php
```

## Configuration

For the Custom API, I reccomend you use the token, which is just a randomly generated string (Or anything you like), this helps prevents anyone making changes to your Pi-Hole.
You set the token in customapi.php script:
```php
<?php
$reload = false;

require_once('func.php');
require_once('database.php');
$GRAVITYDB = getGravityDBFilename();
$db = SQLite3_connect($GRAVITYDB, SQLITE3_OPEN_READWRITE);

$secretToken = "{enter your secret token here}";
if($_GET['token'] == $secretToken){
.....
```

For the Admin Sign-In, you will need to provide some OAuth credentials. In my Org we use ADFS and this worked with ADFS. You will also need to provide your Pi-Hole Admin password.

oauth2-callback.php:
```php
<?php
$oauthClientID = "{your oauth client ID}";
$oauthClientSec = "{your oauth client secret}";
$oauthScope = "{your oauth scope request}";
$oauthRedirectURI = "http://{your pi-hole server address}/auth/oauth2-callback.php";
$oauthAuthorize = "{your oauth2 authorize endpoint}?client_id=$oauthClientID&redirect_uri=$oauthRedirectURI&response_type=code&scope=$oauthScope";
$oauthTokenEP = "{your oauth2 token endpoint}";
$oauthAuthTokenCookie = "_SSO_Auth_Token";
$openidIDTokenCookie = "_SSO_ID_Token";
$encodedURI = urlencode($oauthRedirectURI);
$returnCode = $_GET['code'];
...
```

auth.php:
```php
...
    $piHoleWebPassword = "{your pi-hole web admin password here}";
...
```


## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change. Security was not at the heighest priorites for this project, so feel free to make it more secure.

