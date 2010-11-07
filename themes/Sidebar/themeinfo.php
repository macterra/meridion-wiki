<?php
rcs_id('$Id: themeinfo.php,v 1.10 2002/02/14 06:56:40 carstenklapp Exp $');

/*
 * This file defines the Sidebar appearance ("theme") of PhpWiki.
 */

require_once('lib/Theme.php');

class Theme_Sidebar extends Theme {

    function findTemplate ($name) {
        // hack for navbar.tmpl to hide the buttonseparator
        if ($name == "navbar" || $name == "actionbar" || $name == "signin") {
            //$old = $Theme->getButtonSeparator();
            //$this->setButtonSeparator(HTML::br());
            $this->setButtonSeparator(' ');
            //$Theme->setButtonSeparator($old);
        }

        return $this->_path . $this->_findFile("templates/$name.tmpl");
    }
}
$Theme = new Theme_Sidebar('Sidebar');

// CSS file defines fonts, colors and background images for this
// style.  The companion '*-heavy.css' file isn't defined, it's just
// expected to be in the same directory that the base style is in.

$Theme->setDefaultCSS(_("Sidebar"), 'sidebar.css');
$Theme->addAlternateCSS('PhpWiki', 'phpwiki.css');
$Theme->addAlternateCSS(_("Printer"), 'phpwiki-printer.css', 'print, screen');
$Theme->addAlternateCSS(_("Modern"), 'phpwiki-modern.css');

/**
 * The logo image appears on every page and links to the HomePage.
 */
//$Theme->addImageAlias('logo', 'logo.png');

/**
 * The Signature image is shown after saving an edited page. If this
 * is not set, any signature defined in index.php will be used. If it
 * is not defined by index.php or in here then the "Thank you for
 * editing..." screen will be omitted.
 */

// Comment this next line out to enable signature.
$Theme->addImageAlias('signature', false);

/*
 * Link icons.
 */
$Theme->setLinkIcon('http');
$Theme->setLinkIcon('https');
$Theme->setLinkIcon('ftp');
$Theme->setLinkIcon('mailto');
$Theme->setLinkIcon('interwiki');
$Theme->setLinkIcon('*', 'url');

//$Theme->setButtonSeparator(' | ');

/**
 * WikiWords can automatically be split by inserting spaces between
 * the words. The default is to leave WordsSmashedTogetherLikeSo.
 */
//$Theme->setAutosplitWikiWords(false);

/*
 * You may adjust the formats used for formatting dates and times
 * below.  (These examples give the default formats.)
 * Formats are given as format strings to PHP strftime() function See
 * http://www.php.net/manual/en/function.strftime.php for details.
 * Do not include the server's zone (%Z), times are converted to the
 * user's time zone.
 */
//$Theme->setDateFormat("%B %e, %Y");


// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
