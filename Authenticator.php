<?php
/**
 * This class will enable the developer to authenticate that their users
 * have a valid University of Manchester account.
 * 
 * @author Iain Hart (iain.hart@manchester.ac.uk)
 * 
 * @date Version released December 2023.
 */

class Authenticator
{    
    /**
     * A static function to validate that a user has a University of Manchester
     * account. If the user is not authenticated the program will exit.
     */

    public static function validateUser()
    {
        // If the user is already authenticated return.
        if (self::isAuthenticated())
        {
            return;
        }
        
        // Else if the GET parameter csticket is empty this is a new user who 
        // we need to send for authentication.
        else if (!isset($_GET["csticket"])
                    || empty($_GET["csticket"])
                        || !isset($_SESSION["csticket"])
                            || empty($_SESSION["csticket"]))
        {
            self::sendForAuthentication();
        }
        
        // Else if the GET parameter csticket is populated but doesn't match
        // the session csticket send the user for authentication.
        else if ($_GET["csticket"] != $_SESSION["csticket"])
        {
            self::sendForAuthentication();
        }

        // Else if the data supplied by the GET parameters matches those authenticated
        // by the server record the user's details.
        else if (self::isGETParametersMatchingServerAuthentication())
        {
            self::recordAuthenticatedUser();
        }
    }
    
   
    /**
     * A static function to determine whether a user is already authenticated.
     * @return boolean (true if authenticated, false if not)
     */
    
    private static function isAuthenticated()
    {
        // When a user is authenticated the $_SESSION["authenticated"] is 
        // populated with a timestamp. If a numerical value is held return true.
        $authenticatedtimestamp = self::getTimeAuthenticated();
        if (!empty($authenticatedtimestamp) && is_numeric($authenticatedtimestamp))
        {
            return true;
        }
        
        // Else the user is not already authenticated so return false.
        else
        {
            return false;
        }
    }
    
    /**
     * A static function to send a user to the authentication service.
     */
    private static function sendForAuthentication()
    {
        // Generate a unique ticket.
        $csticket = uniqid();
    
        // Save the ticket so we can confirm the same user is returning from 
        // the authentication service.
        $_SESSION["csticket"] = $csticket;
    
        // Send the user to the School of Computer Science's server to validate.
        // Append to the url the GET parameters 'url' which tells the 
        // authentication service where to return and append the csticket which 
        // will be used to confirm that the same user is returning.
        $url = self::getAuthenticationURL("validate");

        header("Location: $url");
        exit;
    }
    
    /**
     * A static function to construct the URL required to send the client for 
     * authorisation.
     * @param string the command to send with the URL, either validate, or confirm.
     * @return string
     */
    
    private static function getAuthenticationURL($command)
    {
        $csticket = $_SESSION["csticket"];
        $url = AUTHENTICATION_SERVICE_URL . "?url=" . DEVELOPER_URL . "&csticket=$csticket&version=3&command=$command";
        return $url;
    }    
  
    /**
     * A static function to record that a user is authenticated.
     */
    private static function recordAuthenticatedUser()
    {
        // Record the time authenticated.
        $_SESSION["authenticated"] = time();
        
        // Record the user's username.
        $_SESSION["username"] = filter_input(INPUT_GET, 'username', FILTER_SANITIZE_STRING);
        
        // Record the user's full name.
        $_SESSION["fullname"] = filter_input(INPUT_GET, 'fullname', FILTER_SANITIZE_STRING);
    }    
    
    /**
     * A static function to check that the data suplied by the GET parameters matches
     * the data authenticated by the Computer Science server. This is necessary
     * to prevent a man-in-the-middle attack whereby the user provides the 
     * returned GET parametrs by hand.
     * 
     * @return boolean (program will exit on false).
     */
    
    private static function isGETParametersMatchingServerAuthentication()
    {
        $url = self::getAuthenticationURL("confirm");
        $url .= "&username=" . urlencode(filter_input(INPUT_GET, 'username', FILTER_SANITIZE_STRING)) 
                  . "&fullname=" . urlencode(filter_input(INPUT_GET, 'fullname', FILTER_SANITIZE_STRING));

        // Check the confirmation from the Computer Science server returns true
        // but note that it returns a string saying 'true', not a boolean.
        if (file_get_contents($url) != "true")
        {
            self::failAuthentication();
        }
        
        else
        {
            return true;
        }
    }
    
    private static function failAuthentication()
    {
        $errormessage = "<h1>ERROR</h1><p>Authentication failed.</p>";
        $errormessage .= "<p>Suspected man-in-the-middle attack.</p>";
        $errormessage .= "<p>The data in the URL GET parameters do not match those authenticated on the CAS proxy server.</p>";
        exit("$errormessage");        
    }
    
    /**
     * A static function to get the timestamp when the user authenticated.
     * @return string
     */
    
    public static function getTimeAuthenticated()
    {
        return isset($_SESSION["authenticated"])
                    ? $_SESSION["authenticated"]
                        : null;
    }

    /**
     * A static function to get the user's username as returned by the 
     * authentication service.
     * @return string
     */    
    
    public static function getUsername()
    {
        return $_SESSION["username"];
    }
    
    /**
     * A static function to get the user's full name as returned by the 
     * authentication service.
     * @return string
     */    
    
    public static function getFullName()
    {
        return $_SESSION["fullname"];
    }
    
    /**
     * A static function to invalidate a user. This function will remove the
     * data from the global variable $_SESSION
     */
    
    public static function invalidateUser()
    {
        unset($_SESSION);
        session_destroy();
        
        $logouturl = AUTHENTICATION_LOGOUT_URL;
        header("Location: $logouturl");
        exit;
    }
}
?>