<?php rcs_id('$Id: interwiki.php,v 1.20 2002/02/08 02:58:32 dairiki Exp $');

class InterWikiMap {
    function InterWikiMap (&$request) {
        $dbi = $request->getDbh();

        $intermap = $this->_getMapFromWikiPage($dbi->getPage(_("InterWikiMap")));

        if (!$intermap && defined('INTERWIKI_MAP_FILE'))
            $intermap = $this->_getMapFromFile(INTERWIKI_MAP_FILE);

        $this->_map = $this->_parseMap($intermap);
        $this->_regexp = $this->_getRegexp();
    }

    function GetMap (&$request) {
        static $map;
        if (empty($map))
            $map = new InterWikiMap($request);
        return $map;
    }
    
    function getRegexp() {
        return $this->_regexp;
    }

    function link ($link, $linktext = false) {

        list ($moniker, $page) = split (":", $link, 2);
        
        if (!isset($this->_map[$moniker])) {
            return HTML::span(array('class' => 'bad-interwiki'),
                              $linktext ? $linktext : $link);
        }

        $url = $this->_map[$moniker];
        
        // Urlencode page only if it's a query arg.
        // FIXME: this is a somewhat broken heuristic.
        $page_enc = strstr($url, '?') ? rawurlencode($page) : $page;

        if (strstr($url, '%s'))
            $url = sprintf($url, $page_enc);
        else
            $url .= $page_enc;

        $link = HTML::a(array('href' => $url, 'target' => '_blank'),
                        IconForLink('interwiki'));
        if (!$linktext) {
            $link->pushContent("$moniker:",
                               HTML::span(array('class' => 'wikipage'), $page));
            $link->setAttr('class', 'interwiki');
        }
        else {
            $link->pushContent($linktext);
            $link->setAttr('class', 'named-interwiki');
        }
        
        return $link;
    }


    function _parseMap ($text) {
        global $AllowedProtocols;
        if (!preg_match_all("/^\s*(\S+)\s+(\S+)/m",
                            $text, $matches, PREG_SET_ORDER))
            return false;
        foreach ($matches as $m) {
            $map[$m[1]] = $m[2];
        }
        return $map;
    }

    function _getMapFromWikiPage ($page) {
        if (! $page->get('locked'))
            return false;
        
        $current = $page->getCurrentRevision();
        
        if (preg_match('|^<verbatim>\n(.*)^</verbatim>|ms',
                       $current->getPackedContent(), $m)) {
            return $m[1];
        }
        return false;
    }

    function _getMapFromFile ($filename) {
        $error_html = sprintf(_("Loading InterWikiMap from external file %s."), $filename);
        trigger_error( $error_html, E_USER_NOTICE );

        @$fd = fopen ($filename, "rb");
        @$data = fread ($fd, filesize($filename));
        @fclose ($fd);

        return $data;
    }

    function _getRegexp () {
        if (!$this->_map)
            return '(?:(?!a)a)'; //  Never matches.
        
        foreach (array_keys($this->_map) as $moniker)
            $qkeys[] = preg_quote($moniker, '/');
        return "(?:" . join("|", $qkeys) . ")";
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
