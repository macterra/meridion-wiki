<?php rcs_id('$Id: PageGroup.php,v 1.4 2002/02/16 02:08:26 carstenklapp Exp $');
/**
 * Usage:
 *
 * <?plugin PageGroup parent=MyTableOfContents ?>
 *
 * <?plugin PageGroup parent=MyTableOfContents label="Visit more pages in MyTableOfContents" ?>
 *
 * <?plugin PageGroup parent=MyTableOfContents section=PartTwo loop=true ?>
 *
 * <?plugin PageGroup parent=MyTableOfContents loop=1 ?>
 *
 *
 * Updated to use new HTML(). It mostly works, but it's still a giant hackish mess.
 */
class WikiPlugin_PageGroup
extends WikiPlugin
{
    function getName() {
        return _("PageGroup");
    }

    function getDescription() {
        return sprintf(_("PageGroup for %s"),'[pagename]');
    }

    function getDefaultArguments() {
        return array(
                     'parent'  => '',
                     'rev'     => false,
                     'section' => _("Contents"),
                     'label'   => '',
                     'loop'    => false,
                     );
    }

    // Stolen from IncludePage.php
    function extractSection ($section, $content, $page) {
        $qsection = preg_replace('/\s+/', '\s+', preg_quote($section, '/'));

        if (preg_match("/ ^(!{1,})\\s*$qsection" // section header
                       . "  \\s*$\\n?"           // possible blank lines
                       . "  ( (?: ^.*\\n? )*? )" // some lines
                       . "  (?= ^\\1 | \\Z)/xm", // sec header (same or higher level) (or EOF)
                       implode("\n", $content),
                       $match)) {
            // Strip trailing blanks lines and ---- <hr>s
            $text = preg_replace("/\\s*^-{4,}\\s*$/m", "", $match[2]);
            return explode("\n", $text);
        }
        return array(sprintf(_("<%s: no such section>"), $page ." ". $section));
    }

    function run($dbi, $argstr, $request) {

        $args = $this->getArgs($argstr, $request);
        extract($args);
        $html="";
        if (empty($parent)) {
            // FIXME: WikiPlugin has no way to report when
            // required args are missing?
            $error_text = fmt("%s: %s", "WikiPlugin_" .$this->getName(),
                              $error_text);
            $error_text .= " " . sprintf(_("A required argument '%s' is missing."),
                                         'parent');
            $html = $error_text;
            return $html;
        }

        $directions = array ('next'     => _("Next"),
                             'previous' => _("Previous"),
                             'contents' => _("Contents"),
                             'first'    => _("First"),
                             'last'     => _("Last")
                             );

        global $Theme;
        $sep = $Theme->getButtonSeparator();
        if (!$sep)
            $sep = " | "; // force some kind of separator

        // default label
        if (!$label)
            $label = $Theme->makeLinkButton($parent);

        // This is where the list extraction occurs from the named
        // $section on the $parent page.

        $p = $dbi->getPage($parent);
        if ($rev) {
            $r = $p->getRevision($rev);
            if (!$r) {
                $this->error(sprintf(_("%s(%d): no such revision"), $parent,
                                     $rev));
                return '';
            }
        } else {
            $r = $p->getCurrentRevision();
        }

        $c = $r->getContent();
        $c = $this->extractSection($section, $c, $parent);

        $pagename = $request->getArg('pagename');

        // The ordered list of page names determines the page
        // ordering. Right now it doesn't work with a WikiList, only
        // normal lines of text containing the page names.

        $thispage = array_search($pagename, $c);

        $go = array ('previous','next');
        $links = HTML();
        $links->pushcontent($label);
        $links->pushcontent(" [ "); // an experiment
        $lastindex = count($c) - 1; // array is 0-based, count is 1-based!

        foreach ( $go as $go_item ) {
            //yuck this smells, needs optimization.
            if ($go_item == 'previous') {
                if ($loop) {
                    if ($thispage == 0) {
                        $linkpage  = $c[$lastindex];
                    } else {
                        $linkpage  = $c[$thispage - 1];
                    }
                    // mind the French : punctuation
                    $text = fmt("%s: %s", $directions[$go_item], $Theme->makeLinkButton($linkpage));
                    $links->pushcontent($text);
                    $links->pushcontent($sep); //this works because there are only 2 go items, previous,next
                } else {
                    if ($thispage == 0) {
                        // skip it
                    } else {
                        $linkpage  = $c[$thispage - 1];
                        $text = fmt("%s: %s", $directions[$go_item], $Theme->makeLinkButton($linkpage));
                        $links->pushcontent($text);
                        $links->pushcontent($sep); //this works because there are only 2 go items, previous,next
                    }
                }
            } else if ($go_item == 'next') {
                if ($loop) {
                    if ($thispage == $lastindex) {
                        $linkpage  = $c[1];
                    } else {
                        $linkpage  = $c[$thispage + 1];
                    }
                    $text = fmt("%s: %s", $directions[$go_item], $Theme->makeLinkButton($linkpage));
                } else {
                    if ($thispage == $lastindex) {
                        // skip it
                    } else {
                        $linkpage = $c[$thispage + 1];
                        $text = fmt("%s: %s", $directions[$go_item], $Theme->makeLinkButton($linkpage));
                    }
                }
                $links->pushcontent($text);
            }
        }
        $links->pushcontent(" ] "); // an experiment
        return $links;

    }

};

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
