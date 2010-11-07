<?php rcs_id('$Id: PageType.php,v 1.9 2002/02/21 08:52:56 carstenklapp Exp $');
/*
 Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam

 This file is part of PhpWiki.

 PhpWiki is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 PhpWiki is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with PhpWiki; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */


include_once('lib/BlockParser.php');


/**
 * Get a PageType
 *
 * usage:
 *
 * require_once('lib/PageType.php');
 * $transformedContent = PageType($pagerevisionhandle, $pagename, $markup);
 *
 * The pagename and markup args are only required when displaying the content
 * for an edit preview, otherwise they will be extracted from the page $revision
 * instance.
 *
 * See http://phpwiki.sourceforge.net/phpwiki/PageType
 */
function PageType(&$rev, $pagename = false, $markup = false) {

    if (isa($rev, 'WikiDB_PageRevision')) {
        $text = $rev->getPackedContent();
        $pagename = $rev->_pagename; //is this _ok?
        $markup = $rev->get('markup');

    } else {
        // Hopefully only an edit preview gets us here, else we might be screwed.
        if ($pagename == false) {
            $error_text = "DEBUG: \$rev was not a 'WikiDB_PageRevision'. (Are you not previewing a page edit?)"; //debugging message only
            trigger_error($error_text, E_USER_NOTICE);
        }
        $text = $rev;
    }

    // PageType currently only works with InterWikiMap.
    // Once a contentType field has been implemented in the
    // database then that can be used instead of this pagename check.
    $i = _("InterWikiMap");
    switch($pagename) {
        case $i:
            $ContentTemplateName = 'interwikimap';
            break;
        default:
            $ContentTemplateName = 'wikitext';
    }

    $_ContentTemplates = array('wikitext' => new PageType($text, $markup),
                               'interwikimap' => new interWikiMapPageType($text, $markup));

    // Start making the actual content
    $content_template = $_ContentTemplates[$ContentTemplateName];
    return $content_template->getContent();
}


/**
 *
 */
class PageType {
    /**
     * This is a simple WikiPage
     */
    var $_content = "";
    var $_markup = false;
    var $_divs = array();

    function PageType (&$content, $markup) {
        $this->_content = $content;
        $this->_markup = $markup;
        $this->_html = HTML();

        $this->_defineSections();
        $this->_populateSections();
    }

    function _defineSections() {
        /**
         * ... section_id => ('css_class', $this->_section_function)
         */
        $this->_divs = array('wikitext' => array('wikitext', $this->_extractText()));
    }

    function _populateSections() {
        foreach ($this->_divs as $section => $data) {
            list($class, $function) = $data;
            if (!empty($function))
                $this->_html->pushContent(HTML::div(array('class' => $class), $function));
        }
    }

    function _extractText() {
        /**
         * Custom text extractions might want to check if the section
         * contains any text using trim() before returning any
         * transformed text, to avoid displaying blank boxes.
         * See interWikiMapPageType->_extractStartText()
         * and interWikiMapPageType->_extractEndText() for examples.
         */
        return TransformText($this->_content, $this->_markup);
    }

    function getContent() {
        return $this->_html;
    }
};


class interWikiMapPageType extends PageType {

    function _defineSections() {
        /**
         * section_id => ('css_class', $this->_section_function)
         */
        $this->_divs = array('interwikimap-header' => array('wikitext', $this->_extractStartText()),
                             'interwikimap'        => array('wikitext', $this->_getMap()),
                             'interwikimap-footer' => array('wikitext', $this->_extractEndText()));
    }

    function _getMap() {
        global $request;
        // let interwiki.php get the map
        include_once("lib/interwiki.php");
        $map = InterWikiMap::GetMap($request);
        return $this->_arrayToTable($map->_map, $request);
    }

    function _arrayToTable ($array, &$request) {
        $thead = HTML::thead();
        $label[0] = _("Name");
        $label[1] = _("InterWiki Address");
        $thead->pushContent(HTML::tr(HTML::td($label[0]),
                                     HTML::td($label[1])));

        $tbody = HTML::tbody();
        $dbi = $request->getDbh();
        foreach ($array as $moniker => $interurl) {
            if ($dbi->isWikiPage($moniker)) {
                $moniker = WikiLink($moniker);
            }
            $moniker = HTML::td(array('class' => 'interwiki-moniker'), $moniker);
            $interurl = HTML::td(array('class' =>'interwiki-url'), HTML::tt($interurl));

            $tbody->pushContent(HTML::tr($moniker, $interurl));
        }
        $table = HTML::table();
        $table->setAttr('class', 'interwiki-map');
        $table->pushContent($thead);
        $table->pushContent($tbody);

        return $table;
    }

    function _extractStartText() {
        // get the start block of text
        $v = strpos($this->_content, "<verbatim>");
        if ($v)
            list($wikitext, $cruft) = explode("<verbatim>", $this->_content);
        else
            $wikitext = $this->_content;

        if (trim($wikitext))
            return TransformText($wikitext, $this->_markup);

        return "";
    }

    function _extractEndText() {
        // get the ending block of text
        $v = strpos($this->_content, "</verbatim>");
        if ($v) {
            list($cruft, $endtext) = explode("</verbatim>", $this->_content);
            if (trim($endtext))
                return TransformText($endtext, $this->_markup);
        }
        return "";
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
