<?php // -*-php-*-
rcs_id('$Id: BackLinks.php,v 1.18 2002/01/31 01:14:14 dairiki Exp $');
/**
 */

require_once('lib/PageList.php');

class WikiPlugin_BackLinks
extends WikiPlugin
{
    function getName () {
        return _("BackLinks");
    }

    function getDescription () {
        return sprintf(_("Get BackLinks for %s"),'[pagename]');
    }
  
    function getDefaultArguments() {
        return array('exclude'		=> '',
                     'include_self'	=> 0,
                     'noheader'		=> 0,
                     'page'		=> '[pagename]',
                     'info'		=> false
                     );
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges
 
    function run($dbi, $argstr, $request) {
        $this->_args = $this->getArgs($argstr, $request);
        extract($this->_args);
        if (!$page)
            return '';
        
        $exclude = $exclude ? explode(",", $exclude) : array();
        if (!$include_self)
            $exclude[] = $page;

        $pagelist = new PageList($info, $exclude);

        $p = $dbi->getPage($page);
        $pagelist->addPages($p->getLinks());

        if (!$noheader) {
            $pagelink = WikiLink($page, 'auto');

            if ($pagelist->isEmpty())
                return HTML::p(fmt("No pages link to %s.", $pagelink));

            if ($pagelist->getTotal() == 1)
                $pagelist->setCaption(fmt("One page links to %s:",
                                          $pagelink));
            else
                $pagelist->setCaption(fmt("%s pages link to %s:",
                                          $pagelist->getTotal(), $pagelink));
        }

        return $pagelist;
    }

};

// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
        
?>
