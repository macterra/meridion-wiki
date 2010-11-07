<?php // -*-php-*-
rcs_id('$Id: FullTextSearch.php,v 1.11 2002/02/01 21:59:59 dairiki Exp $');

require_once('lib/TextSearchQuery.php');

/**
 */
class WikiPlugin_FullTextSearch
extends WikiPlugin
{
    function getName () {
        return _("FullTextSearch");
    }

    function getDescription () {
        return _("Full Text Search");
    }

    function getDefaultArguments() {
        return array('s'	=> false,
                     'noheader' => false);
        // TODO: multiple page exclude
    }

        
    function run($dbi, $argstr, $request) {

        $args = $this->getArgs($argstr, $request);
        if (empty($args['s']))
            return '';

        extract($args);
        
        $query = new TextSearchQuery($s);
        $pages = $dbi->fullSearch($query);
        $lines = array();
        $hilight_re = $query->getHighlightRegexp();
        $count = 0;
        $found = 0;

        $list = HTML::dl();

        while ($page = $pages->next()) {
            $count++;
            $name = $page->getName();
            $list->pushContent(HTML::dt(WikiLink($name)));
            if ($hilight_re)
                $list->pushContent($this->showhits($page, $hilight_re));
        }
        if (!$list->getContent())
            $list->pushContent(HTML::dd(_("<no matches>")));

        if ($noheader)
            return $list;
        
        return HTML(HTML::p(fmt("Full text search results for '%s'", $s)),
                    $list);
    }

    function showhits($page, $hilight_re) {
        $current = $page->getCurrentRevision();
        $matches = preg_grep("/$hilight_re/i", $current->getContent());
        $html = array();
        foreach ($matches as $line) {
            $line = $this->highlight_line($line, $hilight_re);
            $html[] = HTML::dd(HTML::small(false, $line));
        }
        return $html;
    }

    function highlight_line ($line, $hilight_re) {
        while (preg_match("/^(.*?)($hilight_re)/i", $line, $m)) {
            $line = substr($line, strlen($m[0]));
            $html[] = $m[1];    // prematch
            $html[] = HTML::strong($m[2]); // match
        }
        $html[] = $line;        // postmatch
        return $html;
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
