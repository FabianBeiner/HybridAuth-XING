# HybridAuth provider for the XING.com API

*Tested with HybridAuth 2.1.1-dev.*

## Requirements

Get the latest version *(>= 2.1.1-dev)* of **HybridAuth** from [https://github.com/hybridauth/hybridauth](github.com/hybridauth/hybridauth).

## HELP! I have no idea how this works!

After you downloaded and installed HybridAuth (see above), you'll have to modify the **config.php**. Add the following to the *providers* array:

			"XING" => array (
				"enabled" => true,
				"keys"    => array ( "key" => "", "secret" => "" )
			)

Create a new directory and put an **index.php** file in it, containing the following:

    <?php
    require_once '../Hybrid/Auth.php';
    try {
        $oHybridAuth = new Hybrid_Auth('../config.php');
        $oXING       = $oHybridAuth->authenticate('XING');
        var_dump($oXING->getUserProfile());
        var_dump($oXING->setUserStatus('This is an example from PHP.'));
        var_dump($oXING->getUserContacts());
    }
    catch(Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }

That's it. :) The rest is up to you.