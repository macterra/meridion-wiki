<?php rcs_id('$Id: PageList.php,v 1.37 2002/02/13 04:23:54 carstenklapp Exp $');

/**
 * This library relieves some work for these plugins:
 *
 * AllPages, BackLinks, LikePages, Mostpopular, TitleSearch
 *
 * It also allows dynamic expansion of those plugins to include more
 * columns in their output.
 *
 *
 * Column 'info=' arguments:
 *
 * 'pagename' _("Page Name")
 * 'mtime'    _("Last Modified")
 * 'hits'     _("Hits")
 * 'summary'  _("Last Summary")
 * 'version'  _("Version")),
 * 'author'   _("Last Author")),
 * 'locked'   _("Locked"), _("locked")
 * 'minor'    _("Minor Edit"), _("minor")
 * 'markup'   _("Markup")
 *
 * 'all'     All columns will be displayed. This argument must appear alone.
 *
 * FIXME: In this refactoring I have un-implemented _ctime, _cauthor, and
 * number-of-revision.  Note the _ctime and _cauthor as they were implemented
 * were somewhat flawed: revision 1 of a page doesn't have to exist in the
 * database.  If lots of revisions have been made to a page, it's more than likely
 * that some older revisions (include revision 1) have been cleaned (deleted).
 */
class _PageList_Column_base {
    function _PageList_Column_base ($default_heading, $align = false) {
        $this->_heading = $default_heading;

        $this->_tdattr = array();
        if ($align)
            $this->_tdattr['align'] = $align;
    }

    function format ($page_handle, &$revision_handle) {
        return HTML::td($this->_tdattr,
                        NBSP,
                        $this->_getValue($page_handle, &$revision_handle),
                        NBSP);
    }

    function setHeading ($heading) {
        $this->_heading = $heading;
    }

    function heading () {
        return HTML::td(array('align' => 'center'),
                        NBSP, HTML::u($this->_heading), NBSP);
    }
};

class _PageList_Column extends _PageList_Column_base {
    function _PageList_Column ($field, $default_heading, $align = false) {
        $this->_PageList_Column_base($default_heading, $align);

        $this->_need_rev = substr($field, 0, 4) == 'rev:';
        if ($this->_need_rev)
            $this->_field = substr($field, 4);
        else
            $this->_field = $field;
    }

    function _getValue ($page_handle, &$revision_handle) {
        if ($this->_need_rev) {
            if (!$revision_handle)
                $revision_handle = $page_handle->getCurrentRevision();
            return $revision_handle->get($this->_field);
        }
        else {
            return $page_handle->get($this->_field);
        }
    }
};

class _PageList_Column_bool extends _PageList_Column {
    function _PageList_Column_bool ($field, $default_heading, $text = 'yes') {
        $this->_PageList_Column($field, $default_heading, 'center');
        $this->_textIfTrue = $text;
        $this->_textIfFalse = new RawXml('&#8212;');
    }

    function _getValue ($page_handle, &$revision_handle) {
        $val = _PageList_Column::_getValue($page_handle, $revision_handle);
        return $val ? $this->_textIfTrue : $this->_textIfFalse;
    }
};

class _PageList_Column_time extends _PageList_Column {
    function _PageList_Column_time ($field, $default_heading) {
        $this->_PageList_Column($field, $default_heading, 'right');
    }

    function _getValue ($page_handle, &$revision_handle) {
        global $Theme;
        $time = _PageList_Column::_getValue($page_handle, $revision_handle);
        return $Theme->formatDateTime($time);
    }
};

class _PageList_Column_version extends _PageList_Column {
    function _getValue ($page_handle, &$revision_handle) {
        if (!$revision_handle)
            $revision_handle = $page_handle->getCurrentRevision();
        return $revision_handle->getVersion();
    }
};

class _PageList_Column_author extends _PageList_Column {
    function _getValue ($page_handle, &$revision_handle) {
        global $WikiNameRegexp, $request;
        $dbi = $request->getDbh();

        $author = _PageList_Column::_getValue($page_handle, $revision_handle);
        if (preg_match("/^$WikiNameRegexp\$/", $author) && $dbi->isWikiPage($author))
            return WikiLink($author);
        else
            return $author;
    }
};

class _PageList_Column_pagename extends _PageList_Column_base {
    function _PageList_Column_pagename () {
        $this->_PageList_Column_base(_("Page Name"));
    }

    function _getValue ($page_handle, &$revision_handle) {
        return WikiLink($page_handle);
    }
};



class PageList {
    var $_group_rows = 3;
    var $_columns = array();
    var $_excluded_pages = array();
    var $_rows = array();
    var $_caption = "";
    var $_pagename_seen = false;
    var $_types = array();

    function PageList ($columns = false, $exclude = false) {
        if ($columns == 'all') {
            $this->_initAvailableColumns();
            $columns = array_keys($this->_types);
        }

        if ($columns) {
            if (!is_array($columns))
                $columns = explode(',', $columns);
            foreach ($columns as $col)
                $this->_addColumn($col);
        }
        $this->_addColumn('pagename');

        if ($exclude) {
            if (!is_array($exclude))
                $exclude = explode(',', $exclude);
            $this->_excluded_pages = $exclude;
        }

        $this->_messageIfEmpty = _("<no matches>");
    }

    function setCaption ($caption_string) {
        $this->_caption = $caption_string;
    }

    function getCaption () {
        // put the total into the caption if needed
        if (is_string($this->_caption) && strstr($this->_caption, '%d'))
            return sprintf($this->_caption, $this->getTotal());
        return $this->_caption;
    }

    function setMessageIfEmpty ($msg) {
        $this->_messageIfEmpty = $msg;
    }


    function getTotal () {
        return count($this->_rows);
    }

    function isEmpty () {
        return empty($this->_rows);
    }

    function addPage ($page_handle) {
        if (in_array($page_handle->getName(), $this->_excluded_pages))
            return;             // exclude page.

        $group = (int)(count($this->_rows) / $this->_group_rows);
        $class = ($group % 2) ? 'oddrow' : 'evenrow';
        $revision_handle = false;

        if (count($this->_columns) > 1) {
            $row = HTML::tr(array('class' => $class));
            foreach ($this->_columns as $col)
                $row->pushContent($col->format($page_handle, $revision_handle));
        }
        else {
            $col = $this->_columns[0];
            $row = HTML::li(array('class' => $class),
                            $col->_getValue($page_handle, $revision_handle));
        }

        $this->_rows[] = $row;
    }

    function addPages ($page_iter) {
        while ($page = $page_iter->next())
            $this->addPage($page);
    }


    function getContent() {
        // Note that the <caption> element wants inline content.
        $caption = $this->getCaption();

        if ($this->isEmpty())
            return $this->_emptyList($caption);
        elseif (count($this->_columns) == 1)
            return $this->_generateList($caption);
        else
            return $this->_generateTable($caption);
    }

    function printXML() {
        PrintXML($this->getContent());
    }

    function asXML() {
        return AsXML($this->getContent());
    }


    ////////////////////
    // private
    ////////////////////
    function _initAvailableColumns() {
        if (!empty($this->_types))
            return;

        $this->_types =
            array('pagename'
                  => new _PageList_Column_pagename,

                  'mtime'
                  => new _PageList_Column_time('rev:mtime',
                                               _("Last Modified")),
                  'hits'
                  => new _PageList_Column('hits', _("Hits"), 'right'),

                  'summary'
                  => new _PageList_Column('rev:summary', _("Last Summary")),

                  'version'
                  => new _PageList_Column_version('rev:version', _("Version"),
                                                  'right'),
                  'author'
                  => new _PageList_Column_author('rev:author',
                                                 _("Last Author")),
                  'locked'
                  => new _PageList_Column_bool('locked', _("Locked"),
                                               _("locked")),
                  'minor'
                  => new _PageList_Column_bool('rev:is_minor_edit',
                                               _("Minor Edit"), _("minor")),
                  'markup'
                  => new _PageList_Column('rev:markup', _("Markup"))
                  );
    }

    function _addColumn ($column) {

        $this->_initAvailableColumns();

        if (isset($this->_columns_seen[$column]))
            return false;       // Already have this one.
        $this->_columns_seen[$column] = true;






        if (strstr($column, ':'))
            list ($column, $heading) = explode(':', $column, 2);

        if (!isset($this->_types[$column])) {
            trigger_error(sprintf("%s: Bad column", $column), E_USER_NOTICE);
            return false;
        }

        $col = $this->_types[$column];
        if (!empty($heading))
            $col->setHeading($heading);

        $this->_columns[] = $col;

        return true;
    }

    // make a table given the caption
    function _generateTable($caption) {
        $table = HTML::table(array('cellpadding' => 0,
                                   'cellspacing' => 1,
                                   'border'      => 0,
                                   'class'       => 'pagelist'));
        if ($caption)
            $table->pushContent(HTML::caption(array('align'=>'top'), $caption));

        $row = HTML::tr();
        foreach ($this->_columns as $col) {
            $row->pushContent($col->heading());
            $table_summary[] = $col->_heading;
        }
        // Table summary for non-visual browsers.
        $table->setAttr('summary', sprintf(_("Columns: %s."), implode(", ", $table_summary)));

        $table->pushContent(HTML::thead($row),
                            HTML::tbody(false, $this->_rows));
        return $table;
    }

    function _generateList($caption) {
        $list = HTML::ul(array('class' => 'pagelist'), $this->_rows);
        return $caption ? HTML(HTML::p($caption), $list) : $list;
    }

    function _emptyList($caption) {
        $html = HTML();
        if ($caption)
            $html->pushContent(HTML::p($caption));
        if ($this->_messageIfEmpty)
            $html->pushContent(HTML::blockquote(HTML::p($this->_messageIfEmpty)));
        return $html;
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
