<?php // -*-php-*-
rcs_id('$Id: PageHistory.php,v 1.20 2002/02/22 22:57:03 carstenklapp Exp $');
/**
 */
require_once('lib/plugin/RecentChanges.php');

class _PageHistory_PageRevisionIter
extends WikiDB_PageRevisionIterator
{
    function _PageHistory_PageRevisionIter($rev_iter, $params) {

        $this->_iter = $rev_iter;

        extract($params);

        if (isset($since))
            $this->_since = $since;

        $this->_include_major = empty($exclude_major_revisions);
        if (! $this->_include_major)
            $this->_include_minor = true;
        else
            $this->_include_minor = !empty($include_minor_revisions);

        if (empty($include_all_revisions))
            $this->_limit = 1;
        else if (isset($limit))
            $this->_limit = $limit;
    }

    function next() {
        if (!$this->_iter)
            return false;

        if (isset($this->_limit)) {
            if ($this->_limit <= 0) {
                $this->free();
                return false;
            }
            $this->_limit--;
        }

        while ( ($rev = $this->_iter->next()) ) {
            if (isset($this->_since) && $rev->get('mtime') < $this->_since) {
                $this->free();
                return false;
            }
            if ($rev->get('is_minor_edit') ? $this->_include_minor : $this->_include_major)
                return $rev;
        }
        return false;
    }


    function free() {
        if ($this->_iter)
            $this->_iter->free();
        $this->_iter = false;
    }
}


class _PageHistory_HtmlFormatter
extends _RecentChanges_HtmlFormatter
{
    function include_versions_in_URLs() {
        return true;
    }

    function title() {
        return array(fmt("PageHistory for %s",
                         WikiLink($this->_args['page'])),
                     "\n",
                     $this->rss_icon());
    }

    function _javascript($script) {
        return HTML::script(array('language' => 'JavaScript',
                                  'type'     => 'text/javascript'),
                            new RawXml("<!-- //\n$script\n// -->"));
    }

    function description() {
        // Doesn't work (PHP bug?): $desc = parent::description() . "\n";
        $button = HTML::input(array('type'  => 'submit',
                                    'value' => _("compare revisions"),
                                    'class' => 'wikiaction'));
        return array(_RecentChanges_HtmlFormatter::description(), "\n",
                     $this->_javascript(sprintf('document.write("%s");',
                                                _("Check any two boxes to compare revisions."))),
                     HTML::noscript(fmt("Check any two boxes then %s.", $button)));
    }


    function format ($changes) {
        $this->_itemcount = 0;

        $pagename = $this->_args['page'];

        $html[] = _RecentChanges_HtmlFormatter::format($changes);

        $html[] = HTML::input(array('type'  => 'hidden',
                                    'name'  => 'action',
                                    'value' => 'diff'));
        if (USE_PATH_INFO) {
            $action = WikiURL($pagename);
        }
        else {
            $action = SCRIPT_NAME;
            $html[] = HTML::input(array('type'  => 'hidden',
                                        'name'  => 'pagename',
                                        'value' => $pagename));
        }

        return HTML(HTML::form(array('method' => 'get',
                                     'action' => $action,
                                     'name'   => 'diff-select'),
                               $html),
                    "\n",
                    $this->_javascript('
                                       var diffCkBoxes = document.forms["diff-select"].elements["versions[]"];

                                       function diffCkBox_onclick() {
                                           // If two checkboxes are checked, submit form
                                           var nchecked = 0;
                                           for (i = 0; i < diffCkBoxes.length; i++)
                                               if (diffCkBoxes[i].checked && ++nchecked >= 2)
                                                   this.form.submit();
                                       }

                                       for (i = 0; i < diffCkBoxes.length; i++)
                                       diffCkBoxes[i].onclick = diffCkBox_onclick;'));
    }

    function diffLink ($rev) {
        return HTML::input(array('type'  => 'checkbox',
                                 'name'  => 'versions[]',
                                 'value' => $rev->getVersion()));
    }

    function pageLink ($rev) {
        return HTML::a(array('href'  => $this->pageURL($rev),
                             'class' => 'wiki'),
                       fmt("Version %d", $rev->getVersion()));
    }

    function format_revision ($rev) {
        $class = 'rc-' . $this->importance($rev);

        $time = $this->time($rev);
        if ($rev->get('is_minor_edit')) {
            $minor_flag = HTML(" ",
                               HTML::span(array('class' => 'pageinfo-minoredit'),
                                          "(" . _("minor edit") . ")"));
        }
        else {
            $time = HTML::strong(array('class' => 'pageinfo-majoredit'), $time);
            $minor_flag = '';
        }

        return HTML::li(array('class' => $class),
                        $this->diffLink($rev), ' ',
                        $this->pageLink($rev), ' ',
                        $time, ' ',
                        $this->summaryAsHTML($rev),
                        ' ... ',
                        $this->authorLink($rev),
                        $minor_flag);
    }
}


class _PageHistory_RssFormatter
extends _RecentChanges_RssFormatter
{
    function include_versions_in_URLs() {
        return true;
    }

    function image_properties () {
        return false;
    }

    function textinput_properties () {
        return false;
    }

    function channel_properties () {
        global $request;

        $rc_url = WikiURL($request->getArg('pagename'), false, 'absurl');

        $title = sprintf("%s: %s",
                         WIKI_NAME,
                         split_pagename($this->_args['page']));

        return array('title'          => $title,
                     'dc:description' => _("History of changes."),
                     'link'           => $rc_url,
                     'dc:date'        => Iso8601DateTime(time()));
    }


    function item_properties ($rev) {
        if (!($title = $this->summary($rev)))
            $title = sprintf(_("Version %d"), $rev->getVersion());

        return array( 'title'		=> $title,
                      'link'		=> $this->pageURL($rev),
                      'dc:date'		=> $this->time($rev),
                      'dc:contributor'	=> $rev->get('author'),
                      'wiki:version'	=> $rev->getVersion(),
                      'wiki:importance' => $this->importance($rev),
                      'wiki:status'	=> $this->status($rev),
                      'wiki:diff'	=> $this->diffURL($rev),
                      );
    }
}

class WikiPlugin_PageHistory
extends WikiPlugin_RecentChanges
{
    function getName () {
        return _("PageHistory");
    }

    function getDescription () {
        return sprintf(_("List PageHistory for %s"),'[pagename]');
    }

    function getDefaultArguments() {
        return array('days'		=> false,
                     'show_minor'	=> true,
                     'show_major'	=> true,
                     'limit'		=> false,
                     'page'		=> '[pagename]',
                     'format'		=> false);
    }

    function getDefaultFormArguments() {
        $dflts = WikiPlugin_RecentChanges::getDefaultFormArguments();
        $dflts['textinput'] = 'page';
        return $dflts;
    }

    function getMostRecentParams ($args) {
        $params = WikiPlugin_RecentChanges::getMostRecentParams($args);
        $params['include_all_revisions'] = true;
        return $params;
    }

    function getChanges ($dbi, $args) {
        $page = $dbi->getPage($args['page']);
        $iter = $page->getAllRevisions();
        $params = $this->getMostRecentParams($args);
        return new _PageHistory_PageRevisionIter($iter, $params);
    }

    function format ($changes, $args) {
        global $Theme;
        $format = $args['format'];

        $fmt_class = $Theme->getFormatter('PageHistory', $format);
        if (!$fmt_class) {
            if ($format == 'rss')
                $fmt_class = '_PageHistory_RssFormatter';
            else
                $fmt_class = '_PageHistory_HtmlFormatter';
        }

        $fmt = new $fmt_class($args);
        return $fmt->format($changes);
    }

    function run ($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        if (empty($args['page']))
            return $this->makeForm("", $request);
        // Hack alert: format() is a NORETURN for rss formatters.
        return HTML::div(array('class' => 'wikitext'), $this->format($this->getChanges($dbi, $args), $args));
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
