<?php rcs_id('$Id: Theme.php,v 1.43 2002/02/22 20:03:50 carstenklapp Exp $');

require_once('lib/HtmlElement.php');


/**
 * Make a link to a wiki page (in this wiki).
 *
 * This is a convenience function.
 *
 * @param $page_or_rev mixed
 * Can be:<dl>
 * <dt>A string</dt><dd>The page to link to.</dd>
 * <dt>A WikiDB_Page object</dt><dd>The page to link to.</dd>
 * <dt>A WikiDB_PageRevision object</dt><dd>A specific version of the page to link to.</dd>
 * </dl>
 *
 * @param $type string
 * One of:<dl>
 * <dt>'unknown'</dt><dd>Make link appropriate for a non-existant page.</dd>
 * <dt>'known'</dt><dd>Make link appropriate for an existing page.</dd>
 * <dt>'auto'</dt><dd>Either 'unknown' or 'known' as appropriate.</dd>
 * <dt>'button'</dt><dd>Make a button-style link.</dd>
 * </dl>
 * Unless $type of of the latter form, the link will be of class 'wiki', 'wikiunknown',
 * 'named-wiki', or 'named-wikiunknown', as appropriate.
 *
 * @param $label mixed (string or XmlContent object)
 * Label for the link.  If not given, defaults to the page name.
 * (Label is ignored for $type == 'button'.)
 */
function WikiLink ($page_or_rev, $type = 'known', $label = false) {
    global $Theme;

    if ($type == 'button') {
        return $Theme->makeLinkButton($page_or_rev);
    }

    $version = false;

    if (isa($page_or_rev, 'WikiDB_PageRevision')) {
        $version = $page_or_rev->getVersion();
        $page = $page_or_rev->getPage();
        $pagename = $page->getName();
        $exists = true;
    }
    elseif (isa($page_or_rev, 'WikiDB_Page')) {
        $page = $page_or_rev;
        $pagename = $page->getName();
    }
    else {
        $pagename = $page_or_rev;
    }

    if ($type == 'auto') {
        if (isset($page)) {
            $current = $page->getCurrentRevision();
            $exists = ! $current->hasDefaultContents();
        }
        else {
            global $request;
            $dbi = $request->getDbh();
            $exists = $dbi->isWikiPage($pagename);
        }
    }
    elseif ($type == 'unknown') {
        $exists = false;
    }
    else {
        $exists = true;
    }


    if ($exists)
        return $Theme->linkExistingWikiWord($pagename, $label, $version);
    else
        return $Theme->linkUnknownWikiWord($pagename, $label);
}



/**
 * Make a button.
 *
 * This is a convenience function.
 *
 * @param $action string
 * One of <dl>
 * <dt>[action]</dt><dd>Perform action (e.g. 'edit') on the selected page.</dd>
 * <dt>[ActionPage]</dt><dd>Run the actionpage (e.g. 'BackLinks') on the selected page.</dd>
 * <dt>'submit:'[name]</dt><dd>Make a form submission button with the given name.
 *      ([name] can be blank for a nameless submit button.)</dd>
 * <dt>a hash</dt><dd>Query args for the action. E.g.<pre>
 *      array('action' => 'diff', 'previous' => 'author')
 * </pre></dd>
 * </dl>
 *
 * @param $label string
 * A label for the button.  If ommited, a suitable default (based on the valued of $action)
 * will be picked.
 *
 * @param $page_or_rev mixed
 * Which page (& version) to perform the action on.
 * Can be one of:<dl>
 * <dt>A string</dt><dd>The pagename.</dd>
 * <dt>A WikiDB_Page object</dt><dd>The page.</dd>
 * <dt>A WikiDB_PageRevision object</dt><dd>A specific version of the page.</dd>
 * </dl>
 * ($Page_or_rev is ignored for submit buttons.)
 */
function Button ($action, $label = false, $page_or_rev = false) {
    global $Theme;

    if (!is_array($action) && preg_match('/submit:(.*)/A', $action, $m))
        return $Theme->makeSubmitButton($label, $m[1], $class = $page_or_rev);
    else
        return $Theme->makeActionButton($action, $label, $page_or_rev);
}




class Theme {
    function Theme ($theme_name = 'default') {
        $this->_name = $theme_name;
        $themes_dir = defined('PHPWIKI_DIR') ? PHPWIKI_DIR . "/themes" : "themes";

        $this->_path  = defined('PHPWIKI_DIR') ? PHPWIKI_DIR . "/" : "";
        $this->_theme = "themes/$theme_name";

        if ($theme_name != 'default')
            $this->_default_theme = new Theme;
    }

    function file ($file) {
        return $this->_path . "$this->_theme/$file";
    }

    function _findFile ($file, $missing_okay = false) {
        if (file_exists($this->_path . "$this->_theme/$file"))
            return "$this->_theme/$file";

        // FIXME: this is a short-term hack.  Delete this after all files
        // get moved into themes/...
        if (file_exists($this->_path . $file))
            return $file;


        if (isset($this->_default_theme)) {
            return $this->_default_theme->_findFile($file, $missing_okay);
        }
        else if (!$missing_okay) {
            trigger_error("$file: not found", E_USER_NOTICE);
        }
        return false;
    }

    function _findData ($file, $missing_okay = false) {
        $path = $this->_findFile($file, $missing_okay);
        if (!$path)
            return false;

        if (defined('DATA_PATH'))
            return DATA_PATH . "/$path";
        return $path;
    }

    ////////////////////////////////////////////////////////////////
    //
    // Date and Time formatting
    //
    ////////////////////////////////////////////////////////////////

    var $_dateFormat = "%B %e, %Y";
    var $_timeFormat = "%I:%M %p";
    var $_showModTime = true;

    /**
     * Set format string used for dates.
     *
     * @param $fs string Format string for dates.
     *
     * @param $show_mod_time bool If true (default) then times
     * are included in the messages generated by getLastModifiedMessage(),
     * otherwise, only the date of last modification will be shown.
     */
    function setDateFormat ($fs, $show_mod_time = true) {
        $this->_dateFormat = $fs;
        $this->_showModTime = $show_mod_time;
    }

    /**
     * Set format string used for times.
     *
     * @param $fs string Format string for times.
     */
    function setTimeFormat ($fs) {
        $this->_timeFormat = $fs;
    }

    /**
     * Format a date.
     *
     * Any time zone offset specified in the users preferences is
     * taken into account by this method.
     *
     * @param $time_t integer Unix-style time.
     *
     * @return string The date.
     */
    function formatDate ($time_t) {
        global $request;
        
        $offset_time = $time_t + 3600 * $request->getPref('timeOffset');
        return strftime($this->_dateFormat, $offset_time);
    }

    /**
     * Format a date.
     *
     * Any time zone offset specified in the users preferences is
     * taken into account by this method.
     *
     * @param $time_t integer Unix-style time.
     *
     * @return string The time.
     */
    function formatTime ($time_t) {
        //FIXME: make 24-hour mode configurable?
        global $request;
        $offset_time = $time_t + 3600 * $request->getPref('timeOffset');
        return preg_replace('/^0/', ' ',
                            strtolower(strftime($this->_timeFormat, $offset_time)));
    }

    /**
     * Format a date and time.
     *
     * Any time zone offset specified in the users preferences is
     * taken into account by this method.
     *
     * @param $time_t integer Unix-style time.
     *
     * @return string The date and time.
     */
    function formatDateTime ($time_t) {
        return $this->formatDate($time_t) . ' ' . $this->formatTime($time_t);
    }

    /**
     * Format a (possibly relative) date.
     *
     * If enabled in the users preferences, this method might
     * return a relative day (e.g. 'Today', 'Yesterday').
     *
     * Any time zone offset specified in the users preferences is
     * taken into account by this method.
     *
     * @param $time_t integer Unix-style time.
     *
     * @return string The day.
     */
    function getDay ($time_t) {
        global $request;
        
        if ($request->getPref('relativeDates') && ($date = $this->_relativeDay($time_t))) {
            return ucfirst($date);
        }
        return $this->formatDate($time_t);
    }
    
    /**
     * Format the "last modified" message for a page revision.
     *
     * @param $revision object A WikiDB_PageRevision object.
     *
     * @param $show_version bool Should the page version number
     * be included in the message.  (If this argument is omitted,
     * then the version number will be shown only iff the revision
     * is not the current one.
     *
     * @return string The "last modified" message.
     */
    function getLastModifiedMessage ($revision, $show_version = 'auto') {
        global $request;

        $mtime = $revision->get('mtime');
        
        if ($show_version == 'auto')
            $show_version = !$revision->isCurrent();
            
        if ($request->getPref('relativeDates') && ($date = $this->_relativeDay($mtime))) {
            if ($this->_showModTime)
                $date =  sprintf(_("%s at %s"),
                                 $date, $this->formatTime($mtime));
            
            if ($show_version)
                return fmt("Version %s, saved %s.", $revision->getVersion(), $date);
            else
                return fmt("Last edited %s.", $date);
        }

        if ($this->_showModTime)
            $date = $this->formatDateTime($mtime);
        else
            $date = $this->formatDate($mtime);
        
        if ($show_version)
            return fmt("Version %s, saved on %s.", $revision->getVersion(), $date);
        else
            return fmt("Last edited on %s.", $date);
    }
    
    function _relativeDay ($time_t) {
        global $request;
        $offset = 3600 * $request->getPref('timeOffset');

        $now = time() + $offset;
        $today = localtime($now, true);
        $time = localtime($time_t + $offset, true);

        if ($time['tm_yday'] == $today['tm_yday'] && $time['tm_year'] == $today['tm_year'])
            return _("today");
        
        // Note that due to daylight savings chages (and leap seconds), $now minus
        // 24 hours is not guaranteed to be yesterday.
        $yesterday = localtime($now - (12 + $today['tm_hour']) * 3600, true);
        if ($time['tm_yday'] == $yesterday['tm_yday'] && $time['tm_year'] == $yesterday['tm_year'])
            return _("yesterday");

        return false;
    }

    ////////////////////////////////////////////////////////////////
    //
    // Hooks for other formatting
    //
    ////////////////////////////////////////////////////////////////

    //FIXME: PHP 4.1 Warnings
    //lib/Theme.php:84: Notice[8]: The call_user_method() function is deprecated,
    //use the call_user_func variety with the array(&$obj, "method") syntax instead

    function getFormatter ($type, $format) {
        $method = strtolower("get${type}Formatter");
        if (method_exists($this, $method))
            return $this->{$method}($format);
        return false;
    }

    ////////////////////////////////////////////////////////////////
    //
    // Links
    //
    ////////////////////////////////////////////////////////////////

    var $_autosplitWikiWords = false;

    function setAutosplitWikiWords($autosplit=false) {
        $this->_autosplitWikiWords = $autosplit ? true : false;
    }

    function maybeSplitWikiWord ($wikiword) {
        if ($this->_autosplitWikiWords)
            return split_pagename($wikiword);
        else
            return $wikiword;
    }

    function linkExistingWikiWord($wikiword, $linktext = '', $version = false) {
        if ($version !== false)
            $url = WikiURL($wikiword, array('version' => $version));
        else
            $url = WikiURL($wikiword);

        $link = HTML::a(array('href' => $url));

        if (!empty($linktext)) {
            $link->pushContent($linktext);
            $link->setAttr('class', 'named-wiki');
            $link->setAttr('title', $this->maybeSplitWikiWord($wikiword));
        }
        else {
            $link->pushContent($this->maybeSplitWikiWord($wikiword));
            $link->setAttr('class', 'wiki');
        }
        return $link;
    }

    function linkUnknownWikiWord($wikiword, $linktext = '') {
        $url = WikiURL($wikiword, array('action' => 'edit'));
        //$link = HTML::span(HTML::a(array('href' => $url), '?'));
        $button = $this->makeButton('?', $url);
        $button->addTooltip(sprintf(_("Edit: %s"), $wikiword));
        $link = HTML::span($button);


        if (!empty($linktext)) {
            $link->pushContent(HTML::u($linktext));
            $link->setAttr('class', 'named-wikiunknown');
        }
        else {
            $link->pushContent(HTML::u($this->maybeSplitWikiWord($wikiword)));
            $link->setAttr('class', 'wikiunknown');
        }

        return $link;
    }

    ////////////////////////////////////////////////////////////////
    //
    // Images and Icons
    //
    ////////////////////////////////////////////////////////////////

    /**
        *
     * (To disable an image, alias the image to <code>false</code>.
        */
    function addImageAlias ($alias, $image_name) {
        $this->_imageAliases[$alias] = $image_name;
    }

    function getImageURL ($image) {
        $aliases = &$this->_imageAliases;

        if (isset($aliases[$image])) {
            $image = $aliases[$image];
            if (!$image)
                return false;
        }

        // If not extension, default to .png.
        if (!preg_match('/\.\w+$/', $image))
            $image .= '.png';

        // FIXME: this should probably be made to fall back
        //        automatically to .gif, .jpg.
        //        Also try .gif before .png if browser doesn't like png.

        return $this->_findData("images/$image", 'missing okay');
    }

    function setLinkIcon($proto, $image = false) {
        if (!$image)
            $image = $proto;

        $this->_linkIcons[$proto] = $image;
    }

    function getLinkIconURL ($proto) {
        $icons = &$this->_linkIcons;
        if (!empty($icons[$proto]))
            return $this->getImageURL($icons[$proto]);
        elseif (!empty($icons['*']))
            return $this->getImageURL($icons['*']);
        return false;
    }

    function addButtonAlias ($text, $alias = false) {
        $aliases = &$this->_buttonAliases;

        if (is_array($text))
            $aliases = array_merge($aliases, $text);
        elseif ($alias === false)
            unset($aliases[$text]);
        else
            $aliases[$text] = $alias;
    }

    function getButtonURL ($text) {
        $aliases = &$this->_buttonAliases;
        if (isset($aliases[$text]))
            $text = $aliases[$text];

        $qtext = urlencode($text);
        $url = $this->_findButton("$qtext.png");
        if ($url && strstr($url, '%')) {
            $url = preg_replace('|([^/]+)$|e', 'urlencode("\\1")', $url);
        }
        return $url;
    }

    function _findButton ($button_file) {
        if (!isset($this->_button_path))
            $this->_button_path = $this->_getButtonPath();

        foreach ($this->_button_path as $dir) {
            $path = "$this->_theme/$dir/$button_file";
            if (file_exists($this->_path . $path))
                return defined('DATA_PATH') ? DATA_PATH . "/$path" : $path;
        }
        return false;
    }

    function _getButtonPath () {
        $button_dir = $this->file("buttons");
        if (!file_exists($button_dir) || !is_dir($button_dir))
            return array();

        $path = array('buttons');

        $dir = dir($button_dir);
        while (($subdir = $dir->read()) !== false) {
            if ($subdir[0] == '.')
                continue;
            if (is_dir("$button_dir/$subdir"))
                $path[] = "buttons/$subdir";
        }
        $dir->close();

        return $path;
    }

    ////////////////////////////////////////////////////////////////
    //
    // Button style
    //
    ////////////////////////////////////////////////////////////////

    function makeButton ($text, $url, $class = false) {
        // FIXME: don't always try for image button?

        // Special case: URLs like 'submit:preview' generate form
        // submission buttons.
        if (preg_match('/^submit:(.*)$/', $url, $m))
            return $this->makeSubmitButton($text, $m[1], $class);

        $imgurl = $this->getButtonURL($text);
        if ($imgurl)
            return new ImageButton($text, $url, $class, $imgurl);
        else
            return new Button($text, $url, $class);
    }

    function makeSubmitButton ($text, $name, $class = false) {
        $imgurl = $this->getButtonURL($text);

        if ($imgurl)
            return new SubmitImageButton($text, $name, $class, $imgurl);
        else
            return new SubmitButton($text, $name, $class);
    }

    /**
     * Make button to perform action.
     *
     * This constructs a button which performs an action on the
     * currently selected version of the current page.
     * (Or anotherpage or version, if you want...)
     *
     * @param $action string The action to perform (e.g. 'edit', 'lock').
     * This can also be the name of an "action page" like 'LikePages'.
     * Alternatively you can give a hash of query args to be applied
     * to the page.
     *
     * @param $label string Textual label for the button.  If left empty,
     * a suitable name will be guessed.
     *
     * @param $page_or_rev mixed  The page to link to.  This can be
     * given as a string (the page name), a WikiDB_Page object, or as
     * WikiDB_PageRevision object.  If given as a WikiDB_PageRevision
     * object, the button will link to a specific version of the
     * designated page, otherwise the button links to the most recent
     * version of the page.
     *
     * @return object A Button object.
     */
    function makeActionButton ($action, $label = false, $page_or_rev = false) {
        extract($this->_get_name_and_rev($page_or_rev));

        if (is_array($action)) {
            $attr = $action;
            $action = isset($attr['action']) ? $attr['action'] : 'browse';
        }
        else
            $attr['action'] = $action;

        $class = is_safe_action($action) ? 'wikiaction' : 'wikiadmin';
        if (!$label)
            $label = $this->_labelForAction($action);

        if ($version)
            $attr['version'] = $version;

        if ($action == 'browse')
            unset($attr['action']);

        return $this->makeButton($label, WikiURL($pagename, $attr), $class);
    }

    /**
     * Make a "button" which links to a wiki-page.
     *
     * These are really just regular WikiLinks, possibly
     * disguised (e.g. behind an image button) by the theme.
     *
     * This method should probably only be used for links
     * which appear in page navigation bars, or similar places.
     *
     * Use linkExistingWikiWord, or LinkWikiWord for normal links.
     *
     * @param $page_or_rev mixed The page to link to.  This can be
     * given as a string (the page name), a WikiDB_Page object, or as
     * WikiDB_PageRevision object.  If given as a WikiDB_PageRevision
     * object, the button will link to a specific version of the
     * designated page, otherwise the button links to the most recent
     * version of the page.
     *
     * @return object A Button object.
     */
    function makeLinkButton ($page_or_rev) {
        extract($this->_get_name_and_rev($page_or_rev));

        $args = $version ? array('version' => $version) : false;

        return $this->makeButton($pagename, WikiURL($pagename, $args), 'wiki');
    }

    function _get_name_and_rev ($page_or_rev) {
        $version = false;

        if (empty($page_or_rev)) {
            global $request;
            $pagename = $request->getArg("pagename");
            $version = $request->getArg("version");
        }
        elseif (is_object($page_or_rev)) {
            if (isa($page_or_rev, 'WikiDB_PageRevision')) {
                $rev = $page_or_rev;
                $page = $rev->getPage();
                $version = $rev->getVersion();
            }
            else {
                $page = $page_or_rev;
            }
            $pagename = $page->getName();
        }
        else {
            $pagename = (string) $page_or_rev;
        }
        return compact('pagename', 'version');
    }

    function _labelForAction ($action) {
        switch ($action) {
            case 'edit':   return _("Edit");
            case 'diff':   return _("Diff");
            case 'logout': return _("Sign Out");
            case 'login':  return _("Sign In");
            case 'lock':   return _("Lock Page");
            case 'unlock': return _("Unlock Page");
            case 'remove': return _("Remove Page");
            default:
                // I don't think the rest of these actually get used.
                // 'setprefs'
                // 'upload' 'dumpserial' 'loadfile' 'zip'
                // 'save' 'browse'
                return ucfirst($action);
        }
    }

    //----------------------------------------------------------------
    var $_buttonSeparator = ' | ';

    function setButtonSeparator($separator) {
        $this->_buttonSeparator = $separator;
    }

    function getButtonSeparator() {
        return $this->_buttonSeparator;
    }


    ////////////////////////////////////////////////////////////////
    //
    // CSS
    //
    ////////////////////////////////////////////////////////////////

    function _CSSlink($title, $css_file, $media, $is_alt = false) {
        $html = new HTML;
        $link = $html->link(array('rel'     => $is_alt ? 'alternate stylesheet' : 'stylesheet',
                                 'title'   => $title,
                                 'type'    => 'text/css',
                                 'charset' => CHARSET,
                                 'href'    => $this->_findData($css_file)));
        if ($media)
            $link->setAttr('media', $media);
        return $link;
    }

    function setDefaultCSS ($title, $css_file, $media = false) {
        if (isset($this->_defaultCSS)) {
            $oldtitle = $this->_defaultCSS->_attr['title'];
            $error = sprintf("'%s' -> '%s'", $oldtitle, $title);
            trigger_error(sprintf(_("Redefinition of %s: %s"), "'default CSS'", $error),
                          E_USER_NOTICE);
        }
        if (isset($this->_alternateCSS))
            unset($this->_alternateCSS[$title]);
        $this->_defaultCSS = $this->_CSSlink($title, $css_file, $media);
    }

    function addAlternateCSS ($title, $css_file, $media = false) {
        $this->_alternateCSS[$title] = $this->_CSSlink($title, $css_file, $media, true);
    }

    /**
        * @return string HTML for CSS.
     */
    function getCSS () {
        $css = HTML($this->_defaultCSS);
        if (!empty($this->_alternateCSS))
            $css->pushContent($this->_alternateCSS);
        return $css;
    }

    function findTemplate ($name) {
        return $this->_path . $this->_findFile("templates/$name.tmpl");
    }
};


/**
 * A class representing a clickable "button".
 *
 * In it's simplest (default) form, a "button" is just a link associated
 * with some sort of wiki-action.
 */
class Button extends HtmlElement {
    /** Constructor
     *
     * @param $text string The text for the button.
     * @param $url string The url (href) for the button.
     * @param $class string The CSS class for the button.
     */
    function Button ($text, $url, $class = false) {
        $this->HtmlElement('a', array('href' => $url));
        if ($class)
            $this->setAttr('class', $class);
        $this->pushContent($text);
    }

};


/**
 * A clickable image button.
 */
class ImageButton extends Button {
    /** Constructor
     *
     * @param $text string The text for the button.
     * @param $url string The url (href) for the button.
     * @param $class string The CSS class for the button.
     * @param $img_url string URL for button's image.
     * @param $img_attr array Additional attributes for the &lt;img&gt; tag.
     */
    function ImageButton ($text, $url, $class, $img_url, $img_attr = false) {
        $this->HtmlElement('a', array('href' => $url));
        if ($class)
            $this->setAttr('class', $class);

        if (!is_array($img_attr))
            $img_attr = array();
        $img_attr['src'] = $img_url;
        $img_attr['alt'] = $text;
        $img_attr['class'] = 'wiki-button';
        $img_attr['border'] = 0;
        $this->pushContent(HTML::img($img_attr));
    }
};

/**
 * A class representing a form <samp>submit</samp> button.
 */
class SubmitButton extends HtmlElement {
    /** Constructor
     *
     * @param $text string The text for the button.
     * @param $name string The name of the form field.
     * @param $class string The CSS class for the button.
     */
    function SubmitButton ($text, $name = false, $class = false) {
        $this->HtmlElement('input', array('type' => 'submit',
                                          'value' => $text));
        if ($name)
            $this->setAttr('name', $name);
        if ($class)
            $this->setAttr('class', $class);
    }

};


/**
 * A class representing an image form <samp>submit</samp> button.
 */
class SubmitImageButton extends SubmitButton {
    /** Constructor
     *
     * @param $text string The text for the button.
     * @param $name string The name of the form field.
     * @param $class string The CSS class for the button.
     * @param $img_url string URL for button's image.
     * @param $img_attr array Additional attributes for the &lt;img&gt; tag.
     */
    function SubmitImageButton ($text, $name = false, $class = false, $img_url) {
        $this->HtmlElement('input', array('type'  => 'image',
                                          'src'   => $img_url,
                                          'value' => $text,
                                          'alt'   => $text));
        if ($name)
            $this->setAttr('name', $name);
        if ($class)
            $this->setAttr('class', $class);
    }

};


// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
