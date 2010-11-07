<?php // -*-php-*-
rcs_id('$Id: Info.php,v 1.4 2002/02/22 22:59:18 carstenklapp Exp $');
/**
 *
 */
class WikiPlugin_Info
extends WikiPlugin
{
    function getName () {
        return _("Info");
    }

    function getDescription () {
        return sprintf(_("Show extra page Info and statistics for %s"), '[pagename]');
    }

    function getDefaultArguments() {
        return array('page' => '[pagename]');
    }

    function run ($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);

        $pagename = $page;

        $page = $request->getPage();
    
        if (!empty($version)) {
            if (!($revision = $page->getRevision($version)))
                NoSuchRevision($request, $page, $version);
        }
        else {
            $revision = $page->getCurrentRevision();
        }

        $t = new Template('info', $request,
                          array('revision' => $revision));
        return HTML::div(array('class' => 'wikitext'), $t);
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
