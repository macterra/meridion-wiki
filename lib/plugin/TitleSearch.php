<?php // -*-php-*-
rcs_id('$Id: TitleSearch.php,v 1.13 2002/01/31 01:14:14 dairiki Exp $');

require_once('lib/TextSearchQuery.php');
require_once('lib/PageList.php');

/**
 */
class WikiPlugin_TitleSearch
extends WikiPlugin
{
    function getName () {
        return _("TitleSearch");
    }

    function getDescription () {
        return _("Title Search");
    }
    
    function getDefaultArguments() {
        return array('s'		=> false,
                     'auto_redirect'	=> false,
                     'noheader'		=> false,
                     'exclude'          => '',
                     'info'             => false
                     );
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        if (empty($args['s']))
            return '';

        extract($args);
        
        $query = new TextSearchQuery($s);
        $pages = $dbi->titleSearch($query);

        $pagelist = new PageList($info, $exclude);
        if (!$noheader)
            $pagelist->setCaption(fmt("Title search results for '%s'", $s));

        while ($page = $pages->next()) {
            $pagelist->addPage($page);
            $last_name = $page->getName();
        }

        if ($auto_redirect && ($pagelist->getTotal() == 1))
            $request->redirect(WikiURL($last_name, false, 'absurl'));

        return $pagelist;
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
