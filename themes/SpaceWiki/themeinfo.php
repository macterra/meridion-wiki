<?php // -*-php-*-

rcs_id('$Id: themeinfo.php,v 1.1 2002/02/22 23:46:56 carstenklapp Exp $');

/**
 * This PhpWiki theme is experimental and will likely not appear as
 * part of any release ("accessories not included"--download
 * seperately.)
 *
 * This theme is by design completely css-based so unfortunately it
 * doesn't render properly or even the same across different browsers.
 * A preview screen snapshot is also included for comparison testing.
 *
 * The reverse coloring of this theme was chosen to provide an extreme
 * example of a heavily customized PhpWiki, through which any
 * potential visual problems can be identified. The intention is to
 * elimate as many non-html elements from the html templates as
 * possible.
 *
 * This theme does not render properly in all browsers. In particular,
 * OmniWeb renders some text as black-on-black. Netscape 4 will
 * probably choke on it too.
 * * * * * * * * * * * * */

require_once('lib/Theme.php');

class Theme_SpaceWiki extends Theme {
    function getRecentChangesFormatter ($format) {
        include_once($this->file('lib/RecentChanges.php'));
        if (preg_match('/^rss/', $format))
            return false;       // use default
        return '_SpaceWiki_RecentChanges_Formatter';
    }

    function getPageHistoryFormatter ($format) {
        include_once($this->file('lib/RecentChanges.php'));
        if (preg_match('/^rss/', $format))
            return false;       // use default
        return '_SpaceWiki_PageHistory_Formatter';
    }
}

$Theme = new Theme_SpaceWiki('SpaceWiki');

// CSS file defines fonts, colors and background images for this
// style.  The companion '*-heavy.css' file isn't defined, it's just
// expected to be in the same directory that the base style is in.

$Theme->setDefaultCSS('SpaceWiki', 'SpaceWiki.css');
$Theme->addAlternateCSS(_("Printer"), 'phpwiki-printer.css', 'print, screen');
$Theme->addAlternateCSS(_("Modern"), 'phpwiki-modern.css');
$Theme->addAlternateCSS('PhpWiki', 'phpwiki.css');

/**
 * The logo image appears on every page and links to the HomePage.
 */
//$Theme->addImageAlias('logo', 'logo.png');
$Theme->addImageAlias('logo', 'Ufp-logo.jpg');

/**
 * The Signature image is shown after saving an edited page. If this
 * is not set, any signature defined in index.php will be used. If it
 * is not defined by index.php or in here then the "Thank you for
 * editing..." screen will be omitted.
 */
$Theme->addImageAlias('signature', 'lights.gif');

$Theme->addImageAlias('hr', 'hr.png');

$Theme->setButtonSeparator(" ");

/**
 * WikiWords can automatically be split by inserting spaces between
 * the words. The default is to leave WordsSmashedTogetherLikeSo.
 */
//$Theme->setAutosplitWikiWords(false);

/**
 * The "stardate" format here is really just metricdate.24hourtime. A
 * "real" date2startdate conversion function might be fun but not very
 * useful on a wiki.
 */
$Theme->setTimeFormat("%H%M%S");
$Theme->setDateFormat("%Y%m%d"); // must not contain time


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
