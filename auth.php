<?php
if(isset($_COOKIE["_SSO_Auth_Token"])){ //check if user has Auth Token Cookie
    $oauthToken = $_COOKIE['_SSO_Auth_Token'];
    $openidToken = $_COOKIE["_SSO_ID_Token"];
    //valid user?
    //log into Pi hole
    session_start();
    $setupVars = parse_ini_file("/etc/pihole/setupVars.conf");
    // Try to read password hash from setupVars.conf
    if(isset($setupVars['WEBPASSWORD'])){
        $pwhash = $setupVars['WEBPASSWORD'];
    }
    else{
        $pwhash = "";
    }
    $piHoleWebPassword = "{your-pi-hole-admin-password}}"; // Pi-Hole Web Admin Password
    $piHolePasswordHash = hash('sha256',hash('sha256',$piHoleWebPassword));
    if(hash_equals($pwhash, $piHolePasswordHash)){ //check that Hashed passwords match.
        $_SESSION["hash"] = $pwhash;
        // setcookie('persistentlogin', $pwhash, time()+60*60*24*7,"/"); //Enable this if you want to keep users signed in.
        header('Location: /admin/index.php'); //redirect user back to admin index
        exit();
    }else{
        echo "Sorry, Wrong password!";
        exit;
    }
}else{//require user to sign in!
    header("location: oauth2-callback.php");
}

?>