<?php
rcs_id('$Id: main.php,v 1.62 2002/02/22 23:25:22 carstenklapp Exp $');


include "lib/config.php";
include "lib/stdlib.php";
require_once('lib/Request.php');
require_once("lib/WikiUser.php");
require_once('lib/WikiDB.php');

//define ('DEBUG', 1);

// FIXME: move to config?
if (defined('THEME')) {
    include("themes/" . THEME . "/themeinfo.php");
}
if (empty($Theme)) {
    include("themes/default/themeinfo.php");
}
assert(!empty($Theme));

class _UserPreference
{
    function _UserPreference ($default_value) {
        $this->default_value = $default_value;
    }

    function sanify ($value) {
        return (string) $value;
    }
}

class _UserPreference_numeric extends _UserPreference
{
    function _UserPreference_numeric ($default, $minval = false, $maxval = false) {
        $this->_UserPreference((double) $default);
        $this->_minval = (double) $minval;
        $this->_maxval = (double) $maxval;
    }

    function sanify ($value) {
        $value = (double) $value;
        if ($this->_minval !== false && $value < $this->_minval)
            $value = $this->_minval;
        if ($this->_maxval !== false && $value > $this->_maxval)
            $value = $this->_maxval;
        return $value;
    }
}

class _UserPreference_int extends _UserPreference_numeric
{
    function _UserPreference_int ($default, $minval = false, $maxval = false) {
        $this->_UserPreference_numeric((int) $default, (int)$minval, (int)$maxval);
    }

    function sanify ($value) {
        return (int) parent::sanify((int)$value);
    }
}

class _UserPreference_bool extends _UserPreference
{
    function _UserPreference_bool ($default = false) {
        $this->_UserPreference((bool) $default);
    }

    function sanify ($value) {
        if (is_array($value)) {
            /* This allows for constructs like:
             *
             *   <input type="hidden" name="pref[boolPref][]" value="0" />
             *   <input type="checkbox" name="pref[boolPref][]" value="1" />
             *
             * (If the checkbox is not checked, only the hidden input gets sent.
             * If the checkbox is sent, both inputs get sent.)
             */
            foreach ($value as $val) {
                if ($val)
                    return true;
            }
            return false;
        }
        return (bool) $value;
    }
}

$UserPreferences = array('editWidth' => new _UserPreference_int(80, 30, 150),
                         'editHeight' => new _UserPreference_int(22, 5, 80),
                         'timeOffset' => new _UserPreference_numeric(0, -26, 26),
                         'relativeDates' => new _UserPreference_bool(),
                         'userid' => new _UserPreference(''));

class UserPreferences {
    function UserPreferences ($saved_prefs = false) {
        $this->_prefs = array();

        if (isa($saved_prefs, 'UserPreferences')) {
            foreach ($saved_prefs->_prefs as $name => $value)
                $this->set($name, $value);
        }
    }

    function _getPref ($name) {
        global $UserPreferences;
        if (!isset($UserPreferences[$name])) {
            trigger_error("$name: unknown preference", E_USER_NOTICE);
            return false;
        }
        return $UserPreferences[$name];
    }

    function get ($name) {
        if (isset($this->_prefs[$name]))
            return $this->_prefs[$name];
        if (!($pref = $this->_getPref($name)))
            return false;
        return $pref->default_value;
    }

    function set ($name, $value) {
        if (!($pref = $this->_getPref($name)))
            return false;
        $this->_prefs[$name] = $pref->sanify($value);
    }
}


class WikiRequest extends Request {

    function WikiRequest () {
	global $authWikiName, $authLevel;
	global $HIDE_TOOLBARS;

        $this->Request();

        // Normalize args...
        $this->setArg('pagename', $this->_deducePagename());
        $this->setArg('action', $this->_deduceAction());

        // Restore auth state
        //$this->_user = new WikiUser($this->getSessionVar('wiki_user'));

        $this->_user = new WikiUser;
        $this->_user->_userid = $authWikiName;
        $this->_user->_level = $authLevel;

	$HIDE_TOOLBARS = ($authLevel < 1);

        // Restore saved preferences
        if (!($prefs = $this->getCookieVar('WIKI_PREFS2')))
            $prefs = $this->getSessionVar('wiki_prefs');
        $this->_prefs = new UserPreferences($prefs);
    }

    // This really maybe should be part of the constructor, but since it
    // may involve HTML/template output, the global $request really needs
    // to be initialized before we do this stuff.
    function updateAuthAndPrefs () {
        // Handle preference updates, an authentication requests, if any.
        if ($new_prefs = $this->getArg('pref')) {
            $this->setArg('pref', false);
            foreach ($new_prefs as $key => $val)
                $this->_prefs->set($key, $val);
        }

        // Handle authentication request, if any.
        if ($auth_args = $this->getArg('auth')) {
            $this->setArg('auth', false);
            $this->_handleAuthRequest($auth_args); // possible NORETURN
        }
        elseif ( ! $this->_user->isSignedIn() ) {
            // If not auth request, try to sign in as saved user.
            if (($saved_user = $this->getPref('userid')) != false)
                $this->_signIn($saved_user);
        }

        // Save preferences
        $this->setSessionVar('wiki_prefs', $this->_prefs);
        $this->setCookieVar('WIKI_PREFS2', $this->_prefs, 365);

        // Ensure user has permissions for action
        $require_level = $this->requiredAuthority($this->getArg('action'));
        if (! $this->_user->hasAuthority($require_level))
            $this->_notAuthorized($require_level); // NORETURN
    }

    function getUser () {
        return $this->_user;
    }

    function getPrefs () {
        return $this->_prefs;
    }

    // Convenience function:
    function getPref ($key) {
        return $this->_prefs->get($key);
    }

    function getDbh () {
        if (!isset($this->_dbi)) {
            $this->_dbi = WikiDB::open($GLOBALS['DBParams']);
        }
        return $this->_dbi;
    }

    /**
     * Get requested page from the page database.
     *
     * This is a convenience function.
     */
    function getPage () {
        if (!isset($this->_dbi))
            $this->getDbh();
        return $this->_dbi->getPage($this->getArg('pagename'));
    }

    function _handleAuthRequest ($auth_args) {
        if (!is_array($auth_args))
            return;

        // Ignore password unless POSTed.
        if (!$this->isPost())
            unset($auth_args['password']);

        $user = WikiUser::AuthCheck($auth_args);

        if (isa($user, 'WikiUser')) {
            // Successful login (or logout.)
            $this->_setUser($user);
        }
        elseif ($user) {
            // Login attempt failed.
            $fail_message = $user;
            // If no password was submitted, it's not really
            // a failure --- just need to prompt for password...
            if (!isset($auth_args['password']))
                $fail_message = false;
            WikiUser::PrintLoginForm($this, $auth_args, $fail_message);
            $this->finish();    //NORETURN
        }
        else {
            // Login request cancelled.
        }
    }

    /**
     * Attempt to sign in (bogo-login).
     *
     * Fails silently.
     *
     * @param $userid string Userid to attempt to sign in as.
     * @access private
     */
    function _signIn ($userid) {
        $user = WikiUser::AuthCheck(array('userid' => $userid));
        if (isa($user, 'WikiUser'))
            $this->_setUser($user); // success!
    }

    function _setUser ($user) {
        $this->_user = $user;
        $this->setSessionVar('wiki_user', $user);

        // Save userid to prefs..
        $this->_prefs->set('userid',
                           $user->isSignedIn() ? $user->getId() : '');
    }

    function _notAuthorized ($require_level) {
        // User does not have required authority.  Prompt for login.
        $what = HTML::em($this->getArg('action'));

        if ($require_level >= WIKIAUTH_FORBIDDEN) {
            $this->finish(fmt("Action %s is disallowed on this wiki", $what));
        }
        elseif ($require_level == WIKIAUTH_BOGO)
            $msg = fmt("You must sign in to %s this wiki", $what);
        elseif ($require_level == WIKIAUTH_USER)
            $msg = fmt("You must log in to %s this wiki", $what);
        else
            $msg = fmt("You must be an administrator to %s this wiki", $what);

WikiUser::PrintLoginForm($this, compact('require_level'), $msg);
        $this->finish();    // NORETURN
    }

    function requiredAuthority ($action) {
        // FIXME: clean up.
        switch ($action) {
            case 'browse':
            case 'viewsource':
            case 'diff':
                return WIKIAUTH_ANON;

            case 'zip':
                if (defined('ZIPDUMP_AUTH') && ZIPDUMP_AUTH)
                    return WIKIAUTH_ADMIN;
                return WIKIAUTH_ANON;

            case 'edit':
                if (defined('REQUIRE_SIGNIN_BEFORE_EDIT') && REQUIRE_SIGNIN_BEFORE_EDIT)
                    return WIKIAUTH_BOGO;
                return WIKIAUTH_ANON;
                // return WIKIAUTH_BOGO;

            case 'upload':
            case 'dumpserial':
            case 'dumphtml':
            case 'loadfile':
            case 'remove':
            case 'lock':
            case 'unlock':
                return WIKIAUTH_ADMIN;
            default:
                // Temp workaround for french single-word action page 'Historique'
                $singleWordActionPages = array("Historique", "Info");
                if (in_array($action, $singleWordActionPages))
                    return WIKIAUTH_ANON; // ActionPage.
                global $WikiNameRegexp;
                if (preg_match("/$WikiNameRegexp\Z/A", $action))
                    return WIKIAUTH_ANON; // ActionPage.
                else
                    return WIKIAUTH_ADMIN;
        }
    }

    function possiblyDeflowerVirginWiki () {
        if ($this->getArg('action') != 'browse')
            return;
        if ($this->getArg('pagename') != HomePage)
            return;

        $page = $this->getPage();
        $current = $page->getCurrentRevision();
        if ($current->getVersion() > 0)
            return;             // Homepage exists.

        include('lib/loadsave.php');
        SetupWiki($this);
        $this->finish();        // NORETURN
    }

    function handleAction () {
        $action = $this->getArg('action');
        $method = "action_$action";
        if (method_exists($this, $method)) {
            $this->{$method}();
        }
        elseif ($this->isActionPage($action)) {
            $this->actionpage($action);
        }
        else {
            $this->finish(fmt("%s: Bad action", $action));
        }
    }


    function finish ($errormsg = false) {
        static $in_exit = 0;

        if ($in_exit)
            exit();        // just in case CloseDataBase calls us
        $in_exit = true;

        if (!empty($this->_dbi))
            $this->_dbi->close();
        unset($this->_dbi);


        global $ErrorManager;
        $ErrorManager->flushPostponedErrors();

        if (!empty($errormsg)) {
            PrintXML(HTML::br(),
                     HTML::hr(),
                     HTML::h2(_("Fatal PhpWiki Error")),
                     $errormsg);
            // HACK:
            echo "\n</body></html>";
        }

        Request::finish();
        exit;
    }

    function _deducePagename () {
        if ($this->getArg('pagename'))
            return $this->getArg('pagename');

        if (USE_PATH_INFO) {
            $pathinfo = $this->get('PATH_INFO');
            $tail = substr($pathinfo, strlen(PATH_INFO_PREFIX));

            if ($tail && $pathinfo == PATH_INFO_PREFIX . $tail) {
                return $tail;
            }
        }

        $query_string = $this->get('QUERY_STRING');
        if (preg_match('/^[^&=]+$/', $query_string)) {
            return urldecode($query_string);
        }

        return HomePage;
    }

    function _deduceAction () {
        if (!($action = $this->getArg('action')))
            return 'browse';

        if (method_exists($this, "action_$action"))
            return $action;

        // Allow for, e.g. action=LikePages
        if ($this->isActionPage($action))
            return $action;

        trigger_error("$action: Unknown action", E_USER_NOTICE);
        return 'browse';
    }

    function isActionPage ($pagename) {
        // Temp workaround for french single-word action page 'Historique'
        $singleWordActionPages = array("Historique", "Info");
        if (! in_array($pagename, $singleWordActionPages)) {
            // Allow for, e.g. action=LikePages
            global $WikiNameRegexp;
            if (!preg_match("/$WikiNameRegexp\\Z/A", $pagename))
                return false;
        }
        $dbi = $this->getDbh();
        $page = $dbi->getPage($pagename);
        $rev = $page->getCurrentRevision();
        // FIXME: more restrictive check for sane plugin?
        if (strstr($rev->getPackedContent(), '<?plugin'))
            return true;
        trigger_error("$pagename: Does not appear to be an 'action page'", E_USER_NOTICE);
        return false;
    }

    function action_browse () {
        $this->compress_output();
        include_once("lib/display.php");
        displayPage($this);
    }

    function actionpage ($action) {
        $this->compress_output();
        include_once("lib/display.php");
        actionPage($this, $action);
    }

    function action_diff () {
        $this->compress_output();
        include_once "lib/diff.php";
        showDiff($this);
    }

    function action_search () {
        // This is obsolete: reformulate URL and redirect.
        // FIXME: this whole section should probably be deleted.
        if ($this->getArg('searchtype') == 'full') {
            $search_page = _("FullTextSearch");
        }
        else {
            $search_page = _("TitleSearch");
        }
        $this->redirect(WikiURL($search_page,
                                array('s' => $this->getArg('searchterm')),
                                'absolute_url'));
    }

    function action_edit () {
        $this->compress_output();
        include "lib/editpage.php";
        $e = new PageEditor ($this);
        $e->editPage();
    }

    function action_viewsource () {
        $this->compress_output();
        include "lib/editpage.php";
        $e = new PageEditor ($this);
        $e->viewSource();
    }

    function action_lock () {
        $page = $this->getPage();
        $page->set('locked', true);
        $this->action_browse();
    }

    function action_unlock () {
        // FIXME: This check is redundant.
        //$user->requireAuth(WIKIAUTH_ADMIN);
        $page = $this->getPage();
        $page->set('locked', false);
        $this->action_browse();
    }

    function action_remove () {
        // FIXME: This check is redundant.
        //$user->requireAuth(WIKIAUTH_ADMIN);
        include('lib/removepage.php');
        RemovePage($this);
    }


    function action_upload () {
        include_once("lib/loadsave.php");
        LoadPostFile($this);
    }

    function action_zip () {
        include_once("lib/loadsave.php");
        MakeWikiZip($this);
        // I don't think it hurts to add cruft at the end of the zip file.
        echo "\n========================================================\n";
        echo "PhpWiki " . PHPWIKI_VERSION . " source:\n$GLOBALS[RCS_IDS]\n";
    }

    function action_dumpserial () {
        include_once("lib/loadsave.php");
        DumpToDir($this);
    }

    function action_dumphtml () {
        include_once("lib/loadsave.php");
        DumpHtmlToDir($this);
    }

    function action_loadfile () {
        include_once("lib/loadsave.php");
        LoadFileOrDir($this);
    }
}

//FIXME: deprecated
function is_safe_action ($action) {
    return WikiRequest::requiredAuthority($action) < WIKIAUTH_ADMIN;
}


function main () {
    global $request;

    $request = new WikiRequest();
    $request->updateAuthAndPrefs();

    /* FIXME: is this needed anymore?
        if (USE_PATH_INFO && ! $request->get('PATH_INFO')
            && ! preg_match(',/$,', $request->get('REDIRECT_URL'))) {
            $request->redirect(SERVER_URL
                               . preg_replace('/(\?|$)/', '/\1',
                                              $request->get('REQUEST_URI'),
                                              1));
            exit;
        }
    */

    // Enable the output of most of the warning messages.
    // The warnings will screw up zip files though.
    global $ErrorManager;
    if ($request->getArg('action') != 'zip') {
        $ErrorManager->setPostponedErrorMask(E_NOTICE|E_USER_NOTICE);
        //$ErrorManager->setPostponedErrorMask(0);
    }

    //FIXME:
    //if ($user->is_authenticated())
    //  $LogEntry->user = $user->getId();

    $request->possiblyDeflowerVirginWiki();

    $request->handleAction();
    $request->finish();
}

// Used for debugging purposes
function getmicrotime(){
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
if (defined ('DEBUG')) $GLOBALS['debugclock'] = getmicrotime();

main();


// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
