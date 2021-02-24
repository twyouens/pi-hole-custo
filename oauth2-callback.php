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
if(isset($_GET['code'])){
    //run token exchange
    $curl = curl_init();
    curl_setopt_array($curl, [
    CURLOPT_URL => $oauthTokenEP,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => "grant_type=authorization_code&client_id=$oauthClientID&client_secret=$oauthClientSec&code=$returnCode&redirect_uri=$encodedURI",
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/x-www-form-urlencoded"
    ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
    echo "Error when trying to sign you in! The following Curl error occured:" . $err;
    } else {
        $tokenArray = json_decode($response, true);
        if(!isset($tokenArray['error'])){
            $accessTokenValue = $tokenArray['access_token'];
            $accessTokenExpire = $tokenArray['expires_in'];
            $accessTokenType = $tokenArray['token_type'];
            $accessTokenScope = $tokenArray['scope'];
            $openIdToken = $tokenArray['id_token'];
            setcookie($oauthAuthTokenCookie,$accessTokenValue, time() + $accessTokenExpire,"/auth","");
            setcookie($openidIDTokenCookie,$openIdToken,time() + $accessTokenExpire, "/auth","");
            header("location: auth.php");
        }else{
            $OAuthError = $tokenarray["error"];
            if($OAuthError == "invalid_grant"){// check for error
                header("location: $oauthAuthorize");//redirect to authorize if code did not work.
            }else{
                header("location: $oauthAuthorize");//redirect to authorize if code did not work.
            }
        }
    }
}elseif(isset($_GET['error'])){
    //error with auth server
    echo "There was an error with the authorization server that is preventing you from siging in. This could be you do not have access to the DNS server. Please contact IT Services.";
    exit;
}else{
    //go to authorize
    header("location: $oauthAuthorize");
}
?>