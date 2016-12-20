# XING DECIDED TO DISABLE THEIR PUBLIC API!

Please read https://www.xing.com/communities/posts/important-information-about-xing-public-api-support-1012409468?sc_o=as_g.

<hr>

# HybridAuth provider for the XING.com API

*Tested with HybridAuth 2.1.1-dev and 2.4.0-dev.*

## Requirements

Get the latest version *(>= 2.4.0-dev)* of **HybridAuth** from [github.com/hybridauth/hybridauth](https://github.com/hybridauth/hybridauth).

## Quick Start

After you downloaded and installed HybridAuth (see above), you'll have to modify the ``config.php``. Add the following to the *providers* array:

	"XING" => array (
		"enabled" => true,
		"keys"    => array ( "key" => "", "secret" => "" )
	)

Create a new directory and put an ``index.php`` file in it, containing the following:

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

**Deutsche Information auch in meinem [Blog](http://fabian-beiner.de/de/artikel/xing-api-mit-php-hybridauth-abfragen/).**

## License

HybridAuth is released under dual licence MIT and GPL. So is HybridAuth-XING.

If you're using it somehwere, I'd love to hear from you. :)

