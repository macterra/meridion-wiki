<?php
rcs_id('$Id: config.php,v 1.56 2002/02/15 14:14:13 lakka Exp $');
/*
 * NOTE: the settings here should probably not need to be changed.
*
*
* (The user-configurable settings have been moved to index.php.)
*/

if (!defined("LC_ALL")) {
    // Backward compatibility (for PHP < 4.0.5)
    define("LC_ALL", "LC_ALL");
    define("LC_CTYPE", "LC_CTYPE");
}

// essential internal stuff
set_magic_quotes_runtime(0);

// Some constants.

// "\x80"-"\x9f" (and "\x00" - "\x1f") are non-printing control
// chars in iso-8859-*
// $FieldSeparator = "\263"; //this is a superscript 3 in ISO-8859-1.
$FieldSeparator = "\x81";

require_once('lib/FileFinder.php');
// Search PHP's include_path to find file or directory.
function FindFile ($file, $missing_okay = false)
{
    static $finder;
    if (!isset($finder))
        $finder = new FileFinder;
    return $finder->findFile($file, $missing_okay);
}

// Search PHP's include_path to find file or directory.
// Searches for "locale/$LANG/$file", then for "$file".
function FindLocalizedFile ($file, $missing_okay = false)
{
    static $finder;
    if (!isset($finder))
        $finder = new LocalizedFileFinder;
    return $finder->findFile($file, $missing_okay);
}

function FindLocalizedButtonFile ($file, $missing_okay = false)
{
    static $buttonfinder;
    if (!isset($buttonfinder))
        $buttonfinder = new LocalizedButtonFinder;
    return $buttonfinder->findFile($file, $missing_okay);
}


// Setup localisation
setlocale(LC_ALL, "$LANG");

// I think this putenv is unnecessary, and it causes trouble
// if PHP's safe_mode is enabled:
//putenv("LC_ALL=$LANG");

if (!function_exists ('bindtextdomain'))
{
    $locale = array();

    function gettext ($text) { 
        global $locale;
        if (!empty ($locale[$text]))
            return $locale[$text];
        return $text;
    }

    function _ ($text) {
        return gettext($text);
    }

            
    if ( ($lcfile = FindLocalizedFile("LC_MESSAGES/phpwiki.php", 'missing_ok')) )
        {
            include($lcfile);
        }
}
else
{
    // Setup localisation
    bindtextdomain ("phpwiki", FindFile("locale"));
    textdomain ("phpwiki");
}



// To get the POSIX character classes in the PCRE's (e.g.
// [[:upper:]]) to match extended characters (e.g. GrüßGott), we have
// to set the locale, using setlocale().
//
// The problem is which locale to set?  We would like to recognize all
// upper-case characters in the iso-8859-1 character set as upper-case
// characters --- not just the ones which are in the current $LANG.
//
// As it turns out, at least on my system (Linux/glibc-2.2) as long as
// you setlocale() to anything but "C" it works fine.  (I'm not sure
// whether this is how it's supposed to be, or whether this is a bug
// in the libc...)
//
// We don't currently use the locale setting for anything else, so for
// now, just set the locale to US English.
//
// FIXME: Not all environments may support en_US?  We should probably
// have a list of locales to try.
if (setlocale(LC_CTYPE, 0) == 'C')
     setlocale(LC_CTYPE, 'en_US.' . CHARSET );

/** string pcre_fix_posix_classes (string $regexp)
*
* Older version (pre 3.x?) of the PCRE library do not support
* POSIX named character classes (e.g. [[:alnum:]]).
*
* This is a helper function which can be used to convert a regexp
* which contains POSIX named character classes to one that doesn't.
*
* All instances of strings like '[:<class>:]' are replaced by the equivalent
* enumerated character class.
*
* Implementation Notes:
*
* Currently we use hard-coded values which are valid only for
* ISO-8859-1.  Also, currently on the classes [:alpha:], [:alnum:],
* [:upper:] and [:lower:] are implemented.  (The missing classes:
* [:blank:], [:cntrl:], [:digit:], [:graph:], [:print:], [:punct:],
* [:space:], and [:xdigit:] could easily be added if needed.)
*
* This is a hack.  I tried to generate these classes automatically
* using ereg(), but discovered that in my PHP, at least, ereg() is
* slightly broken w.r.t. POSIX character classes.  (It includes
* "\xaa" and "\xba" in [:alpha:].)
*
* So for now, this will do.  --Jeff <dairiki@dairiki.org> 14 Mar, 2001
*/
function pcre_fix_posix_classes ($regexp) {
    // First check to see if our PCRE lib supports POSIX character
    // classes.  If it does, there's nothing to do.
    if (preg_match('/[[:upper:]]/', 'Ä'))
        return $regexp;

    static $classes = array(
                            'alnum' => "0-9A-Za-z\xc0-\xd6\xd8-\xf6\xf8-\xff",
                            'alpha' => "A-Za-z\xc0-\xd6\xd8-\xf6\xf8-\xff",
                            'upper' => "A-Z\xc0-\xd6\xd8-\xde",
                            'lower' => "a-z\xdf-\xf6\xf8-\xff"
                            );

    $keys = join('|', array_keys($classes));

    return preg_replace("/\[:($keys):]/e", '$classes["\1"]', $regexp);
}

$WikiNameRegexp = pcre_fix_posix_classes($WikiNameRegexp);

//////////////////////////////////////////////////////////////////
// Autodetect URL settings:
//
if (!defined('SERVER_NAME')) define('SERVER_NAME', $HTTP_SERVER_VARS['SERVER_NAME']);
if (!defined('SERVER_PORT')) define('SERVER_PORT', $HTTP_SERVER_VARS['SERVER_PORT']);
if (!defined('SERVER_PROTOCOL')) {
    if (empty($HTTP_SERVER_VARS['HTTPS']) || $HTTP_SERVER_VARS['HTTPS'] == 'off')
        define('SERVER_PROTOCOL', 'http');
    else
        define('SERVER_PROTOCOL', 'https');
}

if (!defined('SCRIPT_NAME')) define('SCRIPT_NAME', $HTTP_SERVER_VARS['SCRIPT_NAME']);

if (!defined('DATA_PATH'))   define('DATA_PATH', dirname(SCRIPT_NAME));

if (!defined('USE_PATH_INFO'))
{
    /*
     * If SCRIPT_NAME does not look like php source file,
     * or user cgi we assume that php is getting run by an
     * action handler in /cgi-bin.  In this case,
     * I think there is no way to get Apache to pass
     * useful PATH_INFO to the php script (PATH_INFO
     * is used to the the php interpreter where the
     * php script is...)
     */
    if (php_sapi_name() == 'apache')
        define('USE_PATH_INFO', true);
    else
        define('USE_PATH_INFO', ereg('\.(php3?|cgi)$', $SCRIPT_NAME));
}


function IsProbablyRedirectToIndex () 
{
    // This might be a redirect to the DirectoryIndex,
    // e.g. REQUEST_URI = /dir/  got redirected
    // to SCRIPT_NAME = /dir/index.php

    // In this case, the proper virtual path is still
    // $SCRIPT_NAME, since pages appear at
    // e.g. /dir/index.php/HomePage.

//global $REQUEST_URI, $SCRIPT_NAME;
    extract($GLOBALS['HTTP_SERVER_VARS']);

    $requri = preg_quote($REQUEST_URI, '%');
    return preg_match("%^${requri}[^/]*$%", $SCRIPT_NAME);
}


if (!defined('VIRTUAL_PATH'))
{
    // We'd like to auto-detect when the cases where apaches
    // 'Action' directive (or similar means) is used to
    // redirect page requests to a cgi-handler.
    //
    // In cases like this, requests for e.g. /wiki/HomePage
    // get redirected to a cgi-script called, say,
    // /path/to/wiki/index.php.  The script gets all
    // of /wiki/HomePage as it's PATH_INFO.
    //
    // The problem is:
    //   How to detect when this has happened reliably?
    //   How to pick out the "virtual path" (in this case '/wiki')?
    //
    // (Another time an redirect might occur is to a DirectoryIndex
    // -- the requested URI is '/wikidir/', the request gets
    // passed to '/wikidir/index.php'.  In this case, the
    // proper VIRTUAL_PATH is '/wikidir/index.php', since the
    // pages will appear at e.g. '/wikidir/index.php/HomePage'.
    //

    $REDIRECT_URL = &$HTTP_SERVER_VARS['REDIRECT_URL'];
    if (USE_PATH_INFO and isset($REDIRECT_URL)
        and ! IsProbablyRedirectToIndex())
        {
            // FIXME: This is a hack, and won't work if the requested
            // pagename has a slash in it.
            define('VIRTUAL_PATH', dirname($REDIRECT_URL . 'x'));
        }
    else
        define('VIRTUAL_PATH', SCRIPT_NAME);
}

if (SERVER_PORT
    && SERVER_PORT != (SERVER_PROTOCOL == 'https' ? 443 : 80)) {
    define('SERVER_URL',
           SERVER_PROTOCOL . '://' . SERVER_NAME . ':' . SERVER_PORT);
}
else {
    define('SERVER_URL',
           SERVER_PROTOCOL . '://' . SERVER_NAME);
}

if (VIRTUAL_PATH != SCRIPT_NAME)
{
    // Apache action handlers are used.
    define('PATH_INFO_PREFIX', VIRTUAL_PATH . '/');
}
else
define('PATH_INFO_PREFIX', '/');


define('BASE_URL',
       SERVER_URL . (USE_PATH_INFO ? VIRTUAL_PATH . '/' : SCRIPT_NAME));

//////////////////////////////////////////////////////////////////
// Select database
//
if (empty($DBParams['dbtype']))
{
    $DBParams['dbtype'] = 'dba';
}

if (!defined('WIKI_NAME')) {
    define('WIKI_NAME', _("Virus Wiki"));
}
if (!defined('HomePage')) {
    define('HomePage', _("HomePage"));
}

// FIXME: delete
// Access log
if (!defined('ACCESS_LOG'))
     define('ACCESS_LOG', '');

// FIXME: delete
// Get remote host name, if apache hasn't done it for us
if (empty($HTTP_SERVER_VARS['REMOTE_HOST']) && ENABLE_REVERSE_DNS)
     $HTTP_SERVER_VARS['REMOTE_HOST'] = gethostbyaddr($HTTP_SERVER_VARS['REMOTE_ADDR']);

     
// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
