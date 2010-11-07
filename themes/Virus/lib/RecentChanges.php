<?php rcs_id('$Id: RecentChanges.php,v 1.4 2002/01/28 21:49:24 carstenklapp Exp $');
/*
 * Extensions/modifications to the stock RecentChanges (and PageHistory) format.
 */


require_once('lib/plugin/RecentChanges.php');
require_once('lib/plugin/PageHistory.php');

function Virus_RC_revision_formatter (&$fmt, &$rev) {
    $class = 'rc-' . $fmt->importance($rev);
        
    return HTML::li(array('class' => $class),
                    $fmt->diffLink($rev), ' ',
                    $fmt->pageLink($rev), ' ',
                    $rev->get('is_minor_edit') ? $fmt->time($rev) : HTML::strong($fmt->time($rev)),
                    ' . . . ',
                    $fmt->summaryAsHTML($rev),
                    ' -- ',
                    $fmt->authorLink($rev));
}

function Virus_PH_revision_formatter (&$fmt, &$rev) {
    $class = 'rc-' . $fmt->importance($rev);

    return HTML::li(array('class' => $class),
                    $fmt->diffLink($rev), ' ',
                    $fmt->pageLink($rev), ' ',
                    $rev->get('is_minor_edit') ? $fmt->time($rev) : HTML::strong($fmt->time($rev)),
                    ' . . . ',
                    $fmt->summaryAsHTML($rev),
                    ' -- ',
                    $fmt->authorLink($rev),
                    $rev->get('is_minor_edit') ? HTML::em(" (" . _("minor edit") . ")") : '');
}

class _Virus_RecentChanges_Formatter
extends _RecentChanges_HtmlFormatter
{
    function format_revision (&$rev) {
        return Virus_RC_revision_formatter($this, $rev);
    }
}

class _Virus_PageHistory_Formatter
extends _PageHistory_HtmlFormatter
{
    function format_revision (&$rev) {
        return Virus_PH_revision_formatter($this, $rev);
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
