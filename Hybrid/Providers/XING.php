<?php
/*!
* HybridAuth
* http://hybridauth.sourceforge.net | https://github.com/hybridauth/hybridauth
* (c) 2009-2015 HybridAuth authors | hybridauth.sourceforge.net/licenses.html
*/

/**
 * XING.com Provider
 *
 * @author  Fabian Beiner <fb@fabianbeiner.de>
 * @version 1.1.0
 */
class Hybrid_Providers_XING extends Hybrid_Provider_Model_OAuth1
{
    /**
     * Initialize.
     */
    function initialize() {
        if (!$this->config['keys']['key'] || !$this->config['keys']['secret']) {
            throw new Exception('You need a consumer key and secret to connect to ' . $this->providerId . '.');
        }

        parent::initialize();

        // XING API endpoints.
        $this->api->api_base_url      = 'https://api.xing.com/v1/';
        $this->api->authorize_url     = 'https://api.xing.com/v1/authorize';
        $this->api->request_token_url = 'https://api.xing.com/v1/request_token';
        $this->api->access_token_url  = 'https://api.xing.com/v1/access_token';

        // Currently there is only version "v1" available.
        if (isset($this->config['api_version']) && $this->config['api_version']) {
            $this->api->api_base_url = 'https://api.xing.com/' . $this->config['api_version'] . '/';
        }

        // We don't need them.
        $this->api->curl_auth_header = false;
    }

    /**
     * Begin logging in.
     */
    function loginBegin() {
        // Handle the request token.
        $aToken                   = $this->api->requestToken($this->endpoint);
        $this->request_tokens_raw = $aToken;

        // The HTTP status code needs to be 201. If it's not, something is wrong.
        if ($this->api->http_code !== 201) {
            throw new Exception('Authentication failed! ' . $this->providerId . ' returned an error: ' . $this->errorMessageByStatus($this->api->http_code) . '.');
        }

        // If we don't have an OAuth token by now, something is ABSOLUTELY wrong.
        if (!isset($aToken['oauth_token'])) {
            throw new Exception('Authentication failed! ' . $this->providerId . ' returned an invalid OAuth token.');
        }

        $this->token('request_token', $aToken['oauth_token']);
        $this->token('request_token_secret', $aToken['oauth_token_secret']);

        // Redirect to the XING authorization URL.
        Hybrid_Auth::redirect($this->api->authorizeUrl($aToken));
    }

    /**
     * Finish logging in.
     */
    function loginFinish() {
        $sToken    = (isset($_REQUEST['oauth_token'])) ? $_REQUEST['oauth_token'] : '';
        $sVerifier = (isset($_REQUEST['oauth_verifier'])) ? $_REQUEST['oauth_verifier'] : '';

        if (!$sToken || !$sVerifier) {
            throw new Exception('Authentication failed! ' . $this->providerId . ' returned an invalid OAuth token/verifier.');
        }

        // Handle the access token.
        $aToken                  = $this->api->accessToken($sVerifier);
        $this->access_tokens_raw = $aToken;

        // You know the deal, don't you? :)
        if ($this->api->http_code !== 201) {
            throw new Exception('Authentication failed! ' . $this->providerId . ' returned an error: ' . $this->errorMessageByStatus($this->api->http_code) . '.');
        }

        // If we don't have an OAuth token by now, something is ABSOLUTELY wrong.
        if (!isset($aToken['oauth_token'])) {
            throw new Exception('Authentication failed! ' . $this->providerId . ' returned an invalid OAuth token.');
        }

        // Delete the request tokens, as we don't need them anymore.
        $this->deleteToken('request_token');
        $this->deleteToken('request_token_secret');

        // But store the access tokens for later usage.
        $this->token('access_token', $aToken['oauth_token']);
        $this->token('access_token_secret', $aToken['oauth_token_secret']);

        // Connection established!
        $this->setUserConnected();
    }

    /**
     * Gets the profile of the user who has granted access.
     *
     * @see https://dev.xing.com/docs/get/users/me
     */
    function getUserProfile() {
        $oResponse = $this->api->get('users/me');

        // The HTTP status code needs to be 200 here. If it's not, something is wrong.
        if ($this->api->http_code !== 200) {
            throw new Exception('Profile request failed! ' . $this->providerId . ' API returned an error: ' . $this->errorMessageByStatus($this->api->http_code) . '.');
        }

        // We should have an object by now.
        if (!is_object($oResponse)) {
            throw new Exception('Profile request failed! ' . $this->providerId . ' API returned an error: invalid response.');
        }

        // Redefine the object.
        $oResponse = $oResponse->users[0];

        /**
         * Handle the profile data.
         *
         * @see  http://hybridauth.sourceforge.net/userguide/Profile_Data_User_Profile.html
         */
        $this->user->profile->identifier    = (property_exists($oResponse, 'id')) ? $oResponse->id : '';
        $this->user->profile->profileURL    = (property_exists($oResponse, 'permalink')) ? $oResponse->permalink : '';
        $this->user->profile->displayName   = (property_exists($oResponse, 'display_name')) ? $oResponse->display_name : '';
        $this->user->profile->description   = (property_exists($oResponse, 'interests')) ? $oResponse->interests : ''; // Not really a "description, but anyways …
        $this->user->profile->firstName     = (property_exists($oResponse, 'first_name')) ? $oResponse->first_name : '';
        $this->user->profile->lastName      = (property_exists($oResponse, 'last_name')) ? $oResponse->last_name : '';
        $this->user->profile->gender        = (property_exists($oResponse, 'gender')) ? $oResponse->gender : '';
        $this->user->profile->emailVerified = (property_exists($oResponse, 'active_email')) ? $oResponse->active_email : '';

        // My own priority: Homepage, blog, other, something else.
        if (property_exists($oResponse, 'web_profiles')) {
            $this->user->profile->webSiteURL = (property_exists($oResponse->web_profiles, 'homepage')) ? $oResponse->web_profiles->homepage[0] : null;
            if (null === $this->user->profile->webSiteURL) {
                $this->user->profile->webSiteURL = (property_exists($oResponse->web_profiles, 'blog')) ? $oResponse->web_profiles->blog[0] : null;
            }
            if (null === $this->user->profile->webSiteURL) {
                $this->user->profile->webSiteURL = (property_exists($oResponse->web_profiles, 'other')) ? $oResponse->web_profiles->other[0] : null;
            }
            // Just use *anything*!
            if (null === $this->user->profile->webSiteURL) {
                foreach ($oResponse->web_profiles as $aUrl) {
                    $this->user->profile->webSiteURL = $aUrl[0];
                    break;
                }
            }
        }

        // We use the largest picture available.
        if (property_exists($oResponse, 'photo_urls') && property_exists($oResponse->photo_urls, 'large')) {
            $this->user->profile->photoURL = (property_exists($oResponse->photo_urls, 'large')) ? $oResponse->photo_urls->large : '';
        }

        // Try to get the native language first.
        if (property_exists($oResponse, 'languages')) {
            foreach ($oResponse->languages as $sLanguage => $sSkill) {
                $this->user->profile->language = strtoupper($sLanguage);
                if ($sSkill == 'NATIVE') {
                    break;
                }
            }
        }

        // Age stuff.
        if (property_exists($oResponse, 'birth_date')) {
            $this->user->profile->age        = floor((time() - strtotime($oResponse->birth_date->year . '-' . $oResponse->birth_date->month . '-' . $oResponse->birth_date->day)) / 31556926);
            $this->user->profile->birthDay   = $oResponse->birth_date->day;
            $this->user->profile->birthMonth = $oResponse->birth_date->month;
            $this->user->profile->birthYear  = $oResponse->birth_date->year;
        }

        // As XING is a business network, users are more likely to be interested in the business address.
        $oAddress = (property_exists($oResponse, 'business_address')) ? $oResponse->business_address : null;
        if (null === $oAddress && property_exists($oResponse, 'private_address')) {
            $oAddress = $oResponse->private_address;
        }
        if (null !== $oAddress) {
            $this->user->profile->phone   = (property_exists($oAddress, 'phone')) ? $oAddress->phone : '';
            $this->user->profile->address = (property_exists($oAddress, 'street')) ? $oAddress->street : '';
            $this->user->profile->country = (property_exists($oAddress, 'country')) ? $oAddress->country : '';
            $this->user->profile->region  = (property_exists($oAddress, 'province')) ? $oAddress->province : '';
            $this->user->profile->city    = (property_exists($oAddress, 'city')) ? $oAddress->city : '';
            $this->user->profile->zip     = (property_exists($oAddress, 'zip_code')) ? $oAddress->zip_code : '';
            $this->user->profile->email   = (property_exists($oAddress, 'email')) ? $oAddress->email : '';
            if (null === $this->user->profile->language) {
                $this->user->profile->language = (property_exists($oAddress, 'country')) ? $oAddress->country : '';
            }
            // The following two are actually not part of the normalized user profile structure used by HybridAuth...
            $this->user->profile->mobile = (property_exists($oAddress, 'mobile_phone')) ? $oAddress->mobile_phone : '';
            $this->user->profile->fax    = (property_exists($oAddress, 'fax')) ? $oAddress->fax : '';
        }

        return $this->user->profile;
    }

    /**
     * Update the user status.
     *
     * @see http://hybridauth.sourceforge.net/userguide/Profile_Data_User_Status.html
     */
    function setUserStatus($sMessage) {
        $aParameters = array('oauth_token' => $this->token('access_token'),
                             'id'          => 'me');

        // German network, there will probably be Umlauts somewhere. :)
        mb_internal_encoding('UTF-8');

        if (!is_string($sMessage) || $sMessage == '') {
            throw new Exception('The passed parameter needs to be a string.');
        }

        // Check if the message is <= 420 characters.
        if (strlen($sMessage) >= 420) {
            $aParameters['message'] = mb_substr($sMessage, 0, 419) . '…';
        }
        else {
            $aParameters['message'] = $sMessage;
        }

        try {
            $oResponse = $this->api->post('users/' . $aParameters['id'] . '/status_message', $aParameters);
            if ($this->api->http_code === 201) {
                return true;
            }
            elseif ($this->api->http_code === 403) {
                throw new Exception('Something went wrong. ' . $this->providerId . ' denied the access.');
            }
            elseif ($this->api->http_code === 404) {
                throw new Exception('The user "' . $aParameters['id'] . '" was not found.');
            }

            return false;
        } catch (Exception $e) {
            throw new Exception('Could not update the status. ' . $this->providerId . ' returned an error: ' . $e . '.');
        }
    }

    /**
     * Load user contacts.
     *
     * @see http://hybridauth.sourceforge.net/userguide/Profile_Data_User_Contacts.html
     * @return Hybrid_User_Contact[]
     * @throws Exception
     */
    function getUserContacts() {
        try {
            $oResponse = $this->api->get('users/me/contacts?limit=100&user_fields=id,display_name,permalink,web_profiles,photo_urls,display_name,interests,active_email&offset=0');

            // The HTTP status code needs to be 200 here. If it's not, something is wrong.
            if ($this->api->http_code !== 200) {
                throw new Exception('User Contact request failed! ' . $this->providerId . ' API returned an error: ' . $this->errorMessageByStatus($this->api->http_code) . '.', $this->api->http_code);
            }

            // We should have an object by now.
            if (!is_object($oResponse)) {
                throw new Exception('User Contact request failed! ' . $this->providerId . ' API returned an error: invalid response.');
            }

            $oTotal = $oResponse->contacts->users;
            $iTotal = $oResponse->contacts->total;

            for ($i = 100; $i <= $iTotal; $i = $i + 100) {
                $oResponse = $this->api->get('users/me/contacts?limit=100&user_fields=id,display_name,permalink,web_profiles,photo_urls,display_name,interests,active_email&offset=' . $i);
                $oTotal    = array_merge($oTotal, $oResponse->contacts->users);
            }
        } catch (Exception $e) {
            throw new Exception('Could not fetch contacts. ' . $this->providerId . ' returned an error: ' . $e . '.', $e->getCode());
        }

        // Return empty array if there are no contacts.
        if (count($oTotal) == 0) {
            return array();
        }

        // Create the contacts array.
        $aContacts = array();
        foreach ($oTotal as $aTitle) {
            $oContact              = new Hybrid_User_Contact();
            $oContact->identifier  = (property_exists($aTitle, 'id')) ? $aTitle->id : '';
            $oContact->profileURL  = (property_exists($aTitle, 'permalink')) ? $aTitle->permalink : '';
            $oContact->displayName = (property_exists($aTitle, 'display_name')) ? $aTitle->display_name : '';
            $oContact->description = (property_exists($aTitle, 'interests')) ? $aTitle->interests : '';
            $oContact->email       = (property_exists($aTitle, 'active_email')) ? $aTitle->active_email : '';

            // My own priority: Homepage, blog, other, something else.
            if (property_exists($aTitle, 'web_profiles')) {
                $oContact->webSiteURL = (property_exists($aTitle->web_profiles, 'homepage')) ? $aTitle->web_profiles->homepage[0] : null;
                if (null === $oContact->webSiteURL) {
                    $oContact->webSiteURL = (property_exists($aTitle->web_profiles, 'blog')) ? $aTitle->web_profiles->blog[0] : null;
                }
                if (null === $oContact->webSiteURL) {
                    $oContact->webSiteURL = (property_exists($aTitle->web_profiles, 'other')) ? $aTitle->web_profiles->other[0] : null;
                }
                // Just use *anything*!
                if (null === $oContact->webSiteURL) {
                    foreach ($aTitle->web_profiles as $aUrl) {
                        $oContact->webSiteURL = $aUrl[0];
                        break;
                    }
                }
            }

            // We use the largest picture available.
            if (property_exists($aTitle, 'photo_urls') && property_exists($aTitle->photo_urls, 'large')) {
                $oContact->photoURL = (property_exists($aTitle->photo_urls, 'large')) ? $aTitle->photo_urls->large : '';
            }

            $aContacts[] = $oContact;
        }

        return $aContacts;
    }

    /**
     * Get contact count
     *
     * @see https://dev.xing.com/docs/get/users/:user_id/contacts
     *
     * @param string $xingId The XING-ID of the user
     * @return int the number of contacts
     * @throws Exception
     */
    function getUserContactCount($xingId)
    {
        try {
            $oResponse = $this->api->get('users/'. $xingId .'/contacts?limit=0');
            // The HTTP status code needs to be 200 here. If it's not, something is wrong.
            if ($this->api->http_code !== 200) {
                throw new Exception('User Contact count request failed! ' . $this->providerId . ' API returned an error: ' . $this->errorMessageByStatus($this->api->http_code) . '.', $this->api->http_code);
            }

            // We should have an object by now.
            if (!is_object($oResponse)) {
                throw new Exception('User Contact count request failed! ' . $this->providerId . ' API returned an error: invalid response.');
            }
            return $oResponse->contacts->total;
        } catch (Exception $e) {
            throw new Exception('Could not fetch Contact count. ' . $this->providerId . ' returned an error: ' . $e . '.', $e->getCode());
        }
    }

    /**
     * Find users by given email
     *
     * @see https://dev.xing.com/docs/get/users/find_by_emails
     *
     * @param array $emails the list of emails that will be searched in XING
     * @param boolean $isUserExisting collect only user that have an accessible XING profile
     * @return XingUser[] the associative array with emails as key
     * @throws Exception
     */
    public function findUsersByEmail( $emails, $isUserExisting = true )
    {
        $user_fields_string = XingUser::getApiRequestFields();

        $aParameters = array(
            'oauth_token' => $this->token( 'access_token' ),
            'user_fields' => $user_fields_string,
        );

        $found_users = array();
        //each email seach request has a limit of 100 emails
        $all_emails_chunks = array_chunk( $emails, 100 );
        foreach ($all_emails_chunks as $single_emails_chunk) {
            $aParameters[ 'emails' ] = implode( ',', $single_emails_chunk );
            $oResponse = $this->api->get( 'users/find_by_emails', $aParameters );

            $this->verifyResponse( 'find_by_emails', $this->api->http_code, $oResponse );

            // parse response
            foreach ($oResponse->results->items as $item) {
                $user_email = $item->email;
                $user = array();
                if (null !== $item->user) {
                    // valid user
                    if (property_exists( $item->user, 'id' ) && $item->user->id != null) {
                        // if id is null then the user is inactive or something wrong anyway
                        $user = new XingUser( $item->user );
                    }
                }

                // filter only found users if requested
                if (!$isUserExisting || ( $isUserExisting && ( count( $user ) > 0 ) )) {
                    $found_users[ $user_email ] = $user;
                }
            }
        }

        return $found_users;
    }

    private function verifyResponse( $requestName, $http_code, $oResponse )
    {
        // The HTTP status code needs to be 200 here. If it's not, something is wrong.
        if ($this->api->http_code !== 200) {
            throw new Exception(
                $requestName . ' request failed! ' . $this->providerId . ' API returned an error: ' . $this->errorMessageByStatus( $http_code ) . '.'
            );
        }

        // We should have an object by now.
        if (!is_object( $oResponse )) {
            throw new Exception( $requestName . ' request failed! ' . $this->providerId . ' API returned an error: invalid response.' );
        }

        return true;
    }
}

/**
 * XingUser - basic XING user profile
 *
 * This is based on the standard Hybrid_User_Contact with some more specific fields
 */
class XingUser extends Hybrid_User_Contact
{

    // maps Hybrid_User_Contact to its relative XING field ids
    private static $xingUser_xingapi_fields_map = array(
        'identifier' => 'id',
        // priority ordered, they are parsed in order. If there is nothing also in web_profiles/other, it takes any web_profiles/ existing
        'webSiteURL' => array( 'web_profiles/homepage', 'web_profiles/blog', 'web_profiles/other', 'web_profiles/*' ),
        'profileURL' => 'permalink',
        // uses the 'large' value from the photo_urls as the user pic
        'photoURL' => array( 'photo_urls/large' ),
        'displayName' => 'display_name',
        'description' => 'interests',
        'email' => 'active_email',
        'firstName' => 'first_name',
        'lastName' => 'last_name',
        'employmentStatus' => 'employment_status',
        'gender' => 'gender',
    );

    // extra attributes that are not included in the Hybrid_User_Contact
    public $firstName = null;
    public $lastName = null;
    public $employmentStatus = null;
    // gender values seems not to be reliable
    public $gender = null;

    /**
     * XINGUser constructor.
     *
     * Create a XING user using with the data coming from the API response
     * @param  stdClass $oResponse the response coming from XING api request
     * @throws Exception
     */
    public function __construct( $oResponse )
    {
        foreach (self::$xingUser_xingapi_fields_map as $classFieldName => $apiFieldName) {
            if (!in_array( $classFieldName, array_keys( get_object_vars( $this ) ) )) {
                throw new Exception( "Cannot find class property [ $classFieldName ]" );
            }
            if (is_array( $apiFieldName )) {
                // if there are multiple elements, there is a preference order in which they are assigned
                // TODO at the moment just 2 levels but should be recursive to parse also more complex nested fields
                foreach ($apiFieldName as $apiFieldNameItem) {
                    list ( $apiFieldNameItemParent, $apiFieldNameItemChild ) = explode( '/', $apiFieldNameItem );
                    if (!strpos( $apiFieldNameItem, '/' )) {
                        throw new Exception( "Invalid nested property defined [ $classFieldName => $apiFieldNameItem ] " );
                    }

                    if (property_exists( $oResponse, $apiFieldNameItemParent )) {
                        if (( $apiFieldNameItemChild ==='*' ) && ( count( get_object_vars( $oResponse->$apiFieldNameItemParent ) ) > 0 )) {
                            // anything is valid then
                            foreach (array_values( get_object_vars( $oResponse->$apiFieldNameItemParent ) ) as $itemChildValue) {
                                if ($itemChildValue != null) {
                                    $this->$classFieldName = $itemChildValue;
                                    break;
                                }
                            }
                        } else {
                            if (property_exists( $oResponse->$apiFieldNameItemParent, $apiFieldNameItemChild )) {
                                $this->$classFieldName = $oResponse->$apiFieldNameItemParent->$apiFieldNameItemChild;
                                // in case we have multiple elements such as in web_profiles/ , we take the fist match
                                break;
                            }
                        }
                    }
                }
            } else {
                // simple property
                $this->$classFieldName = $oResponse->$apiFieldName;
            }
        }
    }

    public static function getApiRequestFields()
    {
        $xingApiFields = array_values( self::$xingUser_xingapi_fields_map );
        $apiFields = array();
        foreach ($xingApiFields as $xingApiField) {
            if (is_array( $xingApiField )) {
                // nested property
                $xingApiField = explode( '/', $xingApiField[ 0 ] )[ 0 ];
            }
            $apiFields[] = $xingApiField;
        }

        return implode( ',', $apiFields );
    }
}
