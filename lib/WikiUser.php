<?php rcs_id('$Id: WikiUser.php,v 1.13 2002/01/24 01:26:55 dairiki Exp $');

// It is anticipated that when userid support is added to phpwiki,
// this object will hold much more information (e-mail, home(wiki)page,
// etc.) about the user.
   
// There seems to be no clean way to "log out" a user when using
// HTTP authentication.
// So we'll hack around this by storing the currently logged
// in username and other state information in a cookie.

define('WIKIAUTH_ANON', 0);
define('WIKIAUTH_BOGO', 1);
define('WIKIAUTH_USER', 2);     // currently unused.
define('WIKIAUTH_ADMIN', 10);
define('WIKIAUTH_FORBIDDEN', 11); // Completely not allowed.

class WikiUser 
{
    var $_userid = false;
    var $_level  = false;

    /**
     * Constructor.
     */
    function WikiUser ($userid = false, $authlevel = false) {
        if (isa($userid, 'WikiUser')) {
            $this->_userid = $userid->_userid;
            $this->_level = $userid->_level;
        }
        else {
            $this->_userid = $userid;
            $this->_level = $authlevel;
        }

        if (!$this->_ok()) {
            // Paranoia: if state is at all inconsistent, log out...
            $this->_userid = false;
            $this->_level = false;
        }
    }

    /** Invariant
     */
    function _ok () {
        if (empty($this->_userid) || empty($this->_level)) {
            // This is okay if truly logged out.
            return $this->_userid === false && $this->_level === false;
        }
        // User is logged in...
        
        // Check for valid authlevel.
        if (!in_array($this->_level, array(WIKIAUTH_BOGO, WIKIAUTH_USER, WIKIAUTH_ADMIN)))
            return false;

        // Check for valid userid.
        if (!is_string($this->_userid))
            return false;
        return true;
    }

    function getId () {
        
        return ( $this->isSignedIn()
                 ? $this->_userid
                 : $GLOBALS['request']->get('REMOTE_ADDR') ); // FIXME: globals
    }

    function getAuthenticatedId() {
        return ( $this->isAuthenticated()
                 ? $this->_userid
                 : $GLOBALS['request']->get('REMOTE_ADDR') ); // FIXME: globals
    }

    function isSignedIn () {
        return $this->_level >= WIKIAUTH_BOGO;
    }
        
    function isAuthenticated () {
        return $this->_level >= WIKIAUTH_USER;
    }
	 
    function isAdmin () {
        return $this->_level == WIKIAUTH_ADMIN;
    }

    function hasAuthority ($require_level) {
        return $this->_level >= $require_level;
    }

    
    function AuthCheck ($postargs) {
        // Normalize args, and extract.
        $keys = array('userid', 'password', 'require_level', 'login', 'logout', 'cancel');
        foreach ($keys as $key) 
            $args[$key] = isset($postargs[$key]) ? $postargs[$key] : false;
        extract($args);
        $require_level = max(0, min(WIKIAUTH_ADMIN, (int) $require_level));

        if ($logout)
            return new WikiUser; // Log out
        elseif ($cancel)
            return false;        // User hit cancel button.
        elseif (!$login && !$userid)
            return false;       // Nothing to do?

        $authlevel = WikiUser::_pwcheck($userid, $password);
        if (!$authlevel)
            return _("Invalid password or userid.");
        elseif ($authlevel < $require_level)
            return _("Insufficient permissions.");

        // Successful login.
        $user = new WikiUser;
        $user->_userid = $userid;
        $user->_level = $authlevel;
        return $user;
    }
    
    function PrintLoginForm (&$request, $args, $fail_message = false) {
        include_once('lib/Template.php');
        
        $userid = '';
        $require_level = 0;
        extract($args);
        
        $require_level = max(0, min(WIKIAUTH_ADMIN, (int) $require_level));
        
        $login = new Template('login', $request,
                              compact('userid', 'require_level', 'fail_message'));

        $top = new Template('top', $request, array('TITLE' =>  _("Sign In")));
        $top->printExpansion($login);
    }
        
    /**
     * Check password.
     */
    function _pwcheck ($userid, $passwd) {
        global $WikiNameRegexp;
        
        if (!empty($userid) && $userid == ADMIN_USER) {
            if (!empty($passwd) && $passwd == ADMIN_PASSWD)
                return WIKIAUTH_ADMIN;
            return false;
        }
        elseif (ALLOW_BOGO_LOGIN
                && preg_match('/\A' . $WikiNameRegexp . '\z/', $userid)) {
            return WIKIAUTH_BOGO;
        }
        return false;
    }
}
    

// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
