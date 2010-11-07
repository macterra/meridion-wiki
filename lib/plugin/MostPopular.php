<?php // -*-php-*-
rcs_id('$Id: MostPopular.php,v 1.20 2002/02/08 20:30:48 lakka Exp $');
/**
 */

require_once('lib/PageList.php');

class WikiPlugin_MostPopular
extends WikiPlugin
{
    function getName () {
        return _("MostPopular");
    }

    function getDescription () {
        return _("List the most popular pages");
    }

    function getDefaultArguments() {
        return array('pagename'	    => '[pagename]', // hackish
                     'exclude'      => '',
                     'limit'        => 20, // limit <0 returns least popular pages
                     'noheader'	    => 0,
                     'info'         => false
                    );
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $columns = $info ? explode(",", $info) : array();
        array_unshift($columns, 'hits');
        
        $pagelist = new PageList($columns, $exclude);

        $pages = $dbi->mostPopular($limit);

        while ($page = $pages->next()) {
            $hits = $page->get('hits');
            if ($hits == 0 && $limit > 0)  // don't show pages with no hits if most
										   // popular pages wanted
                break;
            $pagelist->addPage($page);
        }
        $pages->free();
        
        if (! $noheader) {
            if ($limit > 0) {
                $pagelist->setCaption(_("The %d most popular pages of this wiki:"));
            } else {
			if ($limit < 0) {
				$pagelist->setCaption(_("The %d least popular pages of this wiki:"));
			} else {
                $pagelist->setCaption(_("Visited pages on this wiki, ordered by popularity:"));
            }}
        }

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
