<?php // -*-php-*-
rcs_id('$Id:');

// Define ENABLE_RAW_HTML to true to enable the RawHtml plugin.
//
// IMPORTANT!!!: This plugin is currently insecure, as it's method of
// determining whether it was invoked from a locked page is flawed.
// (See the FIXME: comment below.)
//
// ENABLE AT YOUR OWN RISK!!!
//
//define('ENABLE_RAW_HTML', false);
define('ENABLE_RAW_HTML', true);

/**
 * A plugin to provide for raw HTML within wiki pages.
 */
class WikiPlugin_RawHtml
extends WikiPlugin
{
    function getName () {
        return "RawHtml";
    }
    
    function run($dbi, $argstr, $request) {
        if (!defined('ENABLE_RAW_HTML') || ! ENABLE_RAW_HTML) {
            return $this->error(_("Raw HTML is disabled in this wiki."));
        }

        // FIXME: this test for lockedness is badly flawed.  It checks
        // the requested pages locked state, not the page the plugin
        // invocation came from.  (These could be different in the
        // case of ActionPages, or where the IncludePage plugin is
        // used.)
        $page = $request->getPage();
        if (! $page->get('locked')) {
            return $this->error(_("Raw HTML is only allowed in locked pages."));
        }

        return HTML::raw($argstr);
    }
}


// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
