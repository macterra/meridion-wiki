<?php rcs_id('$Id: stdlib.php,v 1.2 2006/12/09 21:40:27 root Exp root $');

/*
  Standard functions for Wiki functionality
    WikiURL($pagename, $args, $get_abs_url)
    IconForLink($protocol_or_url)
    LinkURL($url, $linktext)
    LinkImage($url, $alt)

    MakeWikiForm ($pagename, $args, $class, $button_text)
    SplitQueryArgs ($query_args)
    LinkPhpwikiURL($url, $text)
    LinkBracketLink($bracketlink)
    ExtractWikiPageLinks($content)
    ConvertOldMarkup($content)
    
    class Stack { push($item), pop(), cnt(), top() }

    split_pagename ($page)
    NoSuchRevision ($request, $page, $version)
    TimezoneOffset ($time, $no_colon)
    Iso8601DateTime ($time)
    Rfc2822DateTime ($time)
    CTime ($time)
    __printf ($fmt)
    __sprintf ($fmt)
    __vsprintf ($fmt, $args)
    better_srand($seed = '')

  function: LinkInterWikiLink($link, $linktext)
  moved to: lib/interwiki.php
  function: linkExistingWikiWord($wikiword, $linktext, $version)
  moved to: lib/Theme.php
  function: LinkUnknownWikiWord($wikiword, $linktext)
  moved to: lib/Theme.php
  function: UpdateRecentChanges($dbi, $pagename, $isnewpage) 
  gone see: lib/plugin/RecentChanges.php
*/


function WikiURL($pagename, $args = '', $get_abs_url = false) {
    if (is_object($pagename)) {
        if (isa($pagename, 'WikiDB_Page')) {
            $pagename = $pagename->getName();
        }
        elseif (isa($pagename, 'WikiDB_PageRevision')) {
            $page = $pagename->getPage();
            $args['version'] = $pagename->getVersion();
            $pagename = $page->getName();
        }
    }
    
    if (is_array($args)) {
        $enc_args = array();
        foreach  ($args as $key => $val) {
            $enc_args[] = urlencode($key) . '=' . urlencode($val);
        }
        $args = join('&', $enc_args);
    }

    if (USE_PATH_INFO) {
        $url = $get_abs_url ? SERVER_URL . VIRTUAL_PATH . "/" : "";
        $url .= preg_replace('/%2f/i', '/', rawurlencode($pagename));
        if ($args)
            $url .= "?$args";
    }
    else {
        $url = $get_abs_url ? SERVER_URL . SCRIPT_NAME : basename(SCRIPT_NAME);
        $url .= "?pagename=" . rawurlencode($pagename);
        if ($args)
            $url .= "&$args";
    }
    return $url;
}

function IconForLink($protocol_or_url) {
    global $Theme;
    if ($filename_suffix = false) {
        // display apache style icon for file type instead of protocol icon
        // - archive: unix:gz,bz2,tgz,tar,z; mac:dmg,dmgz,bin,img,cpt,sit; pc:zip;
        // - document: html, htm, text, txt, rtf, pdf, doc
        // - non-inlined image: jpg,jpeg,png,gif,tiff,tif,swf,pict,psd,eps,ps
        // - audio: mp3,mp2,aiff,aif,au
        // - multimedia: mpeg,mpg,mov,qt
    } else {
        list ($proto) = explode(':', $protocol_or_url, 2);
        $src = $Theme->getLinkIconURL($proto);
        if ($src)
            return HTML::img(array('src' => $src, 'alt' => $proto, 'class' => 'linkicon', 'border' => 0));
        else
            return false;
    }
}

function LinkURL($url, $linktext = '') {
    // FIXME: Is this needed (or sufficient?)
    if(ereg("[<>\"]", $url)) {
        $link = HTML::strong(HTML::u(array('class' => 'baduri'),
                                     _("BAD URL -- remove all of <, >, \"")));
    }
    else {
        if (!$linktext)
            $linktext = preg_replace("/mailto:/A", "", $url);
        
        $link = HTML::a(array('href' => $url),
                        IconForLink($url), $linktext);
        
    }
    $link->setAttr('class', $linktext ? 'namedurl' : 'rawurl');
    $link->setAttr('target', '_blank');
    return $link;
}


function LinkImage($url, $alt = false) {
    // FIXME: Is this needed (or sufficient?)
    if(ereg("[<>\"]", $url)) {
        $link = HTML::strong(HTML::u(array('class' => 'baduri'),
                                     _("BAD URL -- remove all of <, >, \"")));
    }
    else {
        if (empty($alt))
            $alt = $url;
        $link = HTML::img(array('src' => $url, 'alt' => $alt));
    }
    $link->setAttr('class', 'inlineimage');
    return $link;
}



class Stack {
    var $items = array();
    var $size = 0;
    
    function push($item) {
        $this->items[$this->size] = $item;
        $this->size++;
        return true;
    }  
    
    function pop() {
        if ($this->size == 0) {
            return false; // stack is empty
        }  
        $this->size--;
        return $this->items[$this->size];
    }  
    
    function cnt() {
        return $this->size;
    }  
    
    function top() {
        if($this->size)
            return $this->items[$this->size - 1];
        else
            return '';
    }
    
}  
// end class definition


function MakeWikiForm ($pagename, $args, $class, $button_text = '') {
    // HACK: so as to not completely break old PhpWikiAdministration pages.
    trigger_error("MagicPhpWikiURL forms are no longer supported.  "
                  . "Use the WikiFormPlugin instead.", E_USER_NOTICE);

    global $request;
    $loader = new WikiPluginLoader;
    @$action = (string)$args['action'];
    return $loader->expandPI("<?plugin WikiForm action=$action ?>", $request);
}

function SplitQueryArgs ($query_args = '') 
{
    $split_args = split('&', $query_args);
    $args = array();
    while (list($key, $val) = each($split_args))
        if (preg_match('/^ ([^=]+) =? (.*) /x', $val, $m))
            $args[$m[1]] = $m[2];
    return $args;
}

function LinkPhpwikiURL($url, $text = '') {
    $args = array();
    
    if (!preg_match('/^ phpwiki: ([^?]*) [?]? (.*) $/x', $url, $m)) {
        return HTML::strong(array('class' => 'rawurl'),
                            HTML::u(array('class' => 'baduri'),
                                    _("BAD phpwiki: URL")));
    }

    if ($m[1])
        $pagename = urldecode($m[1]);
    $qargs = $m[2];
    
    if (empty($pagename) &&
        preg_match('/^(diff|edit|links|info)=([^&]+)$/', $qargs, $m)) {
        // Convert old style links (to not break diff links in
        // RecentChanges).
        $pagename = urldecode($m[2]);
        $args = array("action" => $m[1]);
    }
    else {
        $args = SplitQueryArgs($qargs);
    }

    if (empty($pagename))
        $pagename = $GLOBALS['request']->getArg('pagename');

    if (isset($args['action']) && $args['action'] == 'browse')
        unset($args['action']);
    
    /*FIXME:
      if (empty($args['action']))
      $class = 'wikilink';
      else if (is_safe_action($args['action']))
      $class = 'wikiaction';
    */
    if (empty($args['action']) || is_safe_action($args['action']))
        $class = 'wikiaction';
    else {
        // Don't allow administrative links on unlocked pages.
        $page = $GLOBALS['request']->getPage();
        if (!$page->get('locked'))
            return HTML::span(array('class' => 'wikiunsafe'),
                              HTML::u(_("Lock page to enable link")));
        $class = 'wikiadmin';
    }
    
    // FIXME: ug, don't like this
    if (preg_match('/=\d*\(/', $qargs))
        return MakeWikiForm($pagename, $args, $class, $text);
    if (!$text)
        $text = HTML::span(array('class' => 'rawurl'), $url);

    return HTML::a(array('href'  => WikiURL($pagename, $args),
                         'class' => $class),
                   $text);
}

function LinkBracketLink($bracketlink) {
    global $request, $AllowedProtocols, $InlineImages;

    include_once("lib/interwiki.php");
    $intermap = InterWikiMap::GetMap($request);
    
    // $bracketlink will start and end with brackets; in between will
    // be either a page name, a URL or both separated by a pipe.
    
    // strip brackets and leading space
    preg_match("/(\[\s*)(.+?)(\s*\])/", $bracketlink, $match);
    // match the contents 
    preg_match("/([^|]+)(\|)?([^|]+)?/", $match[2], $matches);
    
    if (isset($matches[3])) {
        // named link of the form  "[some link name | http://blippy.com/]"
        $URL = trim($matches[3]);
        $linkname = trim($matches[1]);
    } else {
        // unnamed link of the form "[http://blippy.com/] or [wiki page]"
        $URL = trim($matches[1]);
        $linkname = false;
    }

    $dbi = $request->getDbh();
    if ($dbi->isWikiPage($URL))
        return WikiLink($URL, 'known', $linkname);
    elseif (preg_match("#^($AllowedProtocols):#", $URL)) {
        // if it's an image, embed it; otherwise, it's a regular link
        if (preg_match("/($InlineImages)$/i", $URL))
            return LinkImage($URL, $linkname);
        else
            if (preg_match("/($InlineImages)$/i", $linkname))
                return LinkURL($URL, LinkImage($linkname, false));
            else
                return LinkURL($URL, $linkname);
    }
    elseif (preg_match("/^phpwiki:/", $URL))
        return LinkPhpwikiURL($URL, $linkname);
    elseif (preg_match("/^" . $intermap->getRegexp() . ":/", $URL))
        return $intermap->link($URL, $linkname);
    else {
        return WikiLink($URL, 'unknown', $linkname);
    }
    
}

/* FIXME: this should be done by the transform code */
function ExtractWikiPageLinks($content) {
    global $WikiNameRegexp;
    
    if (is_string($content))
        $content = explode("\n", $content);
    
    $wikilinks = array();
    foreach ($content as $line) {
        // remove plugin code
        $line = preg_replace('/<\?plugin\s+\w.*?\?>/', '', $line);
        // remove escaped '['
        $line = str_replace('[[', ' ', $line);
        // remove footnotes
        $line = preg_replace('/[\d+]/', ' ', $line);
        
        // bracket links (only type wiki-* is of interest)
        $numBracketLinks = preg_match_all("/\[\s*([^\]|]+\|)?\s*(\S.*?)\s*\]/",
                                          $line, $brktlinks);
        for ($i = 0; $i < $numBracketLinks; $i++) {
            $link = LinkBracketLink($brktlinks[0][$i]);
            if (preg_match('/^(named-)?wiki(unknown)?$/', $link->getAttr('class')))
                $wikilinks[$brktlinks[2][$i]] = 1;
            
            $brktlink = preg_quote($brktlinks[0][$i]);
            $line = preg_replace("|$brktlink|", '', $line);
        }
        
        // BumpyText old-style wiki links
        if (preg_match_all("/!?$WikiNameRegexp/", $line, $link)) {
            for ($i = 0; isset($link[0][$i]); $i++) {
                if($link[0][$i][0] <> '!')
                    $wikilinks[$link[0][$i]] = 1;
            }
        }
    }
    return array_keys($wikilinks);
}      

/**
 * Convert old page markup to new-style markup.
 *
 * @param $text string Old-style wiki markup.
 *
 * @param $just_links bool Only convert old-style links.
 * (Really this only converts escaped old-style links.)
 *
 * @return string New-style wiki markup.
 *
 * @bugs FIXME: footnotes and old-style tables are known to be broken.
 */
function ConvertOldMarkup ($text, $just_links = false) {

    static $orig, $repl, $link_orig, $link_repl;

    if (empty($orig)) {
        /*****************************************************************
         * Conversions for inline markup:
         */

        // escape tilde's
        $orig[] = '/~/';
        $repl[] = '~~';

        // escape escaped brackets
        $orig[] = '/\[\[/';
        $repl[] = '~[';

        // change ! escapes to ~'s.
        global $AllowedProtocols, $WikiNameRegexp, $request;
        include_once('lib/interwiki.php');
        $map = InterWikiMap::GetMap($request);
        $bang_esc[] = "(?:$AllowedProtocols):[^\s<>\[\]\"'()]*[^\s<>\[\]\"'(),.?]";
        $bang_esc[] = $map->getRegexp() . ":[^\\s.,;?()]+"; // FIXME: is this really needed?
        $bang_esc[] = $WikiNameRegexp;
        $orig[] = '/!((?:' . join(')|(', $bang_esc) . '))/';
        $repl[] = '~\\1';


        $link_orig = $orig;
        $link_repl = $repl;
        
        /*****************************************************************
         * Conversions for block markup
         */
        // convert indented blocks to <pre></pre>.
        $orig[] = '/^[ \t]+\S.*\n(?:(?:\s*\n)?^[ \t]+\S.*\n)*/m';
        $repl[] = "<pre>\n\\0</pre>\n";

        // convert lists
        $orig[] = '/^([#*;]*)([*#]|;.*?:) */me';
        $repl[] = "_ConvertOldListMarkup('\\1', '\\2')";
    }
    

    if ($just_links)
        return preg_replace($link_orig, $link_repl, $text);
    else
        return preg_replace($orig, $repl, $text);
}

function _ConvertOldListMarkup ($indent, $bullet) {
    $indent = str_repeat('     ', strlen($indent));
    if ($bullet[0] == ';') {
        $term = ltrim(substr($bullet, 1));
        return $indent . $term . "\n" . $indent . '     ';
    }
    else
        return $indent . $bullet . ' ';
}



/**
 * Split WikiWords in page names.
 *
 * It has been deemed useful to split WikiWords (into "Wiki Words") in
 * places like page titles. This is rumored to help search engines
 * quite a bit.
 *
 * @param $page string The page name.
 *
 * @return string The split name.
 */
function split_pagename ($page) {
    
    if (preg_match("/\s/", $page))
        return $page;           // Already split --- don't split any more.
    
    // FIXME: this algorithm is Anglo-centric.
    static $RE;
    if (!isset($RE)) {
        // This mess splits between a lower-case letter followed by
        // either an upper-case or a numeral; except that it wont
        // split the prefixes 'Mc', 'De', or 'Di' off of their tails.
        $RE[] = '/([[:lower:]])((?<!Mc|De|Di)[[:upper:]]|\d)/';
        // This the single-letter words 'I' and 'A' from any following
        // capitalized words.
        $RE[] = '/(?: |^)([AI])([[:upper:]][[:lower:]])/';
        // Split numerals from following letters.
        $RE[] = '/(\d)([[:alpha:]])/';
        
        foreach ($RE as $key => $val)
            $RE[$key] = pcre_fix_posix_classes($val);
    }
    
    foreach ($RE as $regexp)
        $page = preg_replace($regexp, '\\1 \\2', $page);
    return $page;
}

function NoSuchRevision (&$request, $page, $version) {
    $html = HTML(HTML::h2(_("Revision Not Found")),
                 HTML::p(fmt("I'm sorry.  Version %d of %s is not in my database.",
                             $version, WikiLink($page, 'auto'))));
    include_once('lib/Template.php');
    GeneratePage($html, _("Bad Version"), $page->getCurrentRevision());
    $request->finish();
}


/**
 * Get time offset for local time zone.
 *
 * @param $time time_t Get offset for this time. Default: now.
 * @param $no_colon boolean Don't put colon between hours and minutes.
 * @return string Offset as a string in the format +HH:MM.
 */
function TimezoneOffset ($time = false, $no_colon = false) {
    if ($time === false)
        $time = time();
    $secs = date('Z', $time);

    if ($secs < 0) {
        $sign = '-';
        $secs = -$secs;
    }
    else {
        $sign = '+';
    }
    $colon = $no_colon ? '' : ':';
    $mins = intval(($secs + 30) / 60);
    return sprintf("%s%02d%s%02d",
                   $sign, $mins / 60, $colon, $mins % 60);
}


/**
 * Format time in ISO-8601 format.
 *
 * @param $time time_t Time.  Default: now.
 * @return string Date and time in ISO-8601 format.
 */
function Iso8601DateTime ($time = false) {
    if ($time === false)
        $time = time();
    $tzoff = TimezoneOffset($time);
    $date  = date('Y-m-d', $time);
    $time  = date('H:i:s', $time);
    return $date . 'T' . $time . $tzoff;
}

/**
 * Format time in RFC-2822 format.
 *
 * @param $time time_t Time.  Default: now.
 * @return string Date and time in RFC-2822 format.
 */
function Rfc2822DateTime ($time = false) {
    if ($time === false)
        $time = time();
    return date('D, j M Y H:i:s ', $time) . TimezoneOffset($time, 'no colon');
}

/**
 * Format time to standard 'ctime' format.
 *
 * @param $time time_t Time.  Default: now.
 * @return string Date and time.
 */
function CTime ($time = false)
{
    if ($time === false)
        $time = time();
    return date("D M j H:i:s Y", $time);
}



/**
 * Internationalized printf.
 *
 * This is essentially the same as PHP's built-in printf
 * with the following exceptions:
 * <ol>
 * <li> It passes the format string through gettext().
 * <li> It supports the argument reordering extensions.
 * </ol>
 *
 * Example:
 *
 * In php code, use:
 * <pre>
 *    __printf("Differences between versions %s and %s of %s",
 *             $new_link, $old_link, $page_link);
 * </pre>
 *
 * Then in locale/po/de.po, one can reorder the printf arguments:
 *
 * <pre>
 *    msgid "Differences between %s and %s of %s."
 *    msgstr "Der Unterschiedsergebnis von %3$s, zwischen %1$s und %2$s."
 * </pre>
 *
 * (Note that while PHP tries to expand $vars within double-quotes,
 * the values in msgstr undergo no such expansion, so the '$'s
 * okay...)
 *
 * One shouldn't use reordered arguments in the default format string.
 * Backslashes in the default string would be necessary to escape the
 * '$'s, and they'll cause all kinds of trouble....
 */ 
function __printf ($fmt) {
    $args = func_get_args();
    array_shift($args);
    echo __vsprintf($fmt, $args);
}

/**
 * Internationalized sprintf.
 *
 * This is essentially the same as PHP's built-in printf with the
 * following exceptions:
 *
 * <ol>
 * <li> It passes the format string through gettext().
 * <li> It supports the argument reordering extensions.
 * </ol>
 *
 * @see __printf
 */ 
function __sprintf ($fmt) {
    $args = func_get_args();
    array_shift($args);
    return __vsprintf($fmt, $args);
}

/**
 * Internationalized vsprintf.
 *
 * This is essentially the same as PHP's built-in printf with the
 * following exceptions:
 *
 * <ol>
 * <li> It passes the format string through gettext().
 * <li> It supports the argument reordering extensions.
 * </ol>
 *
 * @see __printf
 */ 
function __vsprintf ($fmt, $args) {
    $fmt = gettext($fmt);
    // PHP's sprintf doesn't support variable with specifiers,
    // like sprintf("%*s", 10, "x"); --- so we won't either.
    
    if (preg_match_all('/(?<!%)%(\d+)\$/x', $fmt, $m)) {
        // Format string has '%2$s' style argument reordering.
        // PHP doesn't support this.
        if (preg_match('/(?<!%)%[- ]?\d*[^- \d$]/x', $fmt))
            // literal variable name substitution only to keep locale
            // strings uncluttered
            trigger_error(sprintf(_("Can't mix '%s' with '%s' type format strings"),
                                  '%1\$s','%s'), E_USER_WARNING); //php+locale error
        
        $fmt = preg_replace('/(?<!%)%\d+\$/x', '%', $fmt);
        $newargs = array();
        
        // Reorder arguments appropriately.
        foreach($m[1] as $argnum) {
            if ($argnum < 1 || $argnum > count($args))
                trigger_error(sprintf(_("%s: argument index out of range"), 
                                      $argnum), E_USER_WARNING);
            $newargs[] = $args[$argnum - 1];
        }
        $args = $newargs;
    }
    
    // Not all PHP's have vsprintf, so...
    array_unshift($args, $fmt);
    return call_user_func_array('sprintf', $args);
}


class fileSet {
    /**
     * Build an array in $this->_fileList of files from $dirname.
     * Subdirectories are not traversed.
     *
     * (This was a function LoadDir in lib/loadsave.php)
     * See also http://www.php.net/manual/en/function.readdir.php
     */
    function getFiles() {
        return $this->_fileList;
    }

    function _filenameSelector($filename) {
        // Default selects all filenames, override as needed.
        return true;
    }

    function fileSet($directory) {
        $this->_fileList = array();

        if (empty($directory)) {
            trigger_error(sprintf(_("%s is empty."), 'directoryname'),
                          E_USER_NOTICE);
            return; // early return
        }

        @ $dir_handle = opendir($dir=$directory);
        if (empty($dir_handle)) {
            trigger_error(sprintf(_("Unable to open directory '%s' for reading"),
                                  $dir), E_USER_NOTICE);
            return; // early return
        }

        while ($filename = readdir($dir_handle)) {
            if ($filename[0] == '.' || filetype("$dir/$filename") != 'file')
                continue;
            if ($this->_filenameSelector($filename)) {
                array_push($this->_fileList, "$filename");
                //trigger_error(sprintf(_("found file %s"), $filename),
                //                      E_USER_NOTICE); //debugging
            }
        }
        closedir($dir_handle);
    }
};


// Class introspections

/** Determine whether object is of a specified type.
 *
 * @param $object object An object.
 * @param $class string Class name.
 * @return bool True iff $object is a $class
 * or a sub-type of $class. 
 */
function isa ($object, $class) 
{
    $lclass = strtolower($class);

    return is_object($object)
        && ( get_class($object) == strtolower($lclass)
             || is_subclass_of($object, $lclass) );
}

/** Determine whether (possible) object has method.
 *
 * @param $object mixed Object
 * @param $method string Method name
 * @return bool True iff $object is an object with has method $method.
 */
function can ($object, $method) 
{
    return is_object($object) && method_exists($object, strtolower($method));
}

/**
 * Seed the random number generator.
 *
 * better_srand() ensures the randomizer is seeded only once.
 * 
 * How random do you want it? See:
 * http://www.php.net/manual/en/function.srand.php
 * http://www.php.net/manual/en/function.mt-srand.php
 */
function better_srand($seed = '') {
    static $wascalled = FALSE;
    if (!$wascalled) {
        $seed = $seed === '' ? (double) microtime() * 1000000 : $seed;
        srand($seed);
        $wascalled = TRUE;
        //trigger_error("new random seed", E_USER_NOTICE); //debugging
    }
}

/**
 * Recursively count all non-empty elements 
 * in array of any dimension or mixed - i.e. 
 * array('1' => 2, '2' => array('1' => 3, '2' => 4))
 * See http://www.php.net/manual/en/function.count.php
 */
function count_all($arg)
{
    // skip if argument is empty
    if ($arg) {
        //print_r($arg); //debugging
        $count = 0;
        // not an array, return 1 (base case) 
        if(!is_array($arg))
            return 1;
        // else call recursively for all elements $arg
        foreach($arg as $key => $val)
            $count += count_all($val);
        return $count;
    }
}


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
