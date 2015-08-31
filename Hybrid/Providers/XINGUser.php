<?php
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
