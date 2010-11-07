<?php // -*-php-*-
rcs_id('$Id: RandomPage.php,v 1.7 2002/01/31 01:34:02 carstenklapp Exp $');

require_once('lib/PageList.php');

/**
 */
class WikiPlugin_RandomPage
extends WikiPlugin
{
    function getName () {
        return _("RandomPage");
    }

    function getDescription () {
        return _("RandomPage");
    }

    function getDefaultArguments() {
        return array('pages'        => 1,
                     'redirect'     => false,
                     'hidename'     => false, // only for pages=1
                     'exclude'      => $this->default_exclude(),
                     'info'         => '');
    }

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $allpages = $dbi->getAllPages();

        $exclude = $exclude ? explode(",", $exclude) : array();

        while ($page = $allpages->next()) {
            if (!in_array($page->getName(), $exclude))
                $pagearray[] = $page;
        }

        better_srand(); // Start with a good seed.

        if ($pages == 1 && $pagearray) {
            $page = $pagearray[array_rand($pagearray)];
            if ($redirect)
                $request->redirect(WikiURL($page, false, 'absurl')); // noreturn
            if ($hidename)
                return WikiLink($page, false, _("RandomPage"));
            else
                return WikiLink($page);
        }

        $pages = min( max(1, (int)$pages), 20, count($pagearray));
        $pagelist = new PageList($info);
        $shuffle = array_rand($pagearray, $pages);
        foreach ($shuffle as $i)
            $pagelist->addPage($pagearray[$i]);
        return $pagelist;
    }

    function default_exclude() {
        // Some useful default pages to exclude.
        $default_exclude = 'RandomPage,HomePage,AllPages,RecentChanges,RecentEdits,FullRecentChanges';
        foreach (explode(",", $default_exclude) as $e) {
            $_exclude[] = gettext($e);
        }
        return implode(",", $_exclude);
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
