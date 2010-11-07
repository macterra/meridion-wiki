<?php // -*-php-*-
rcs_id('$Id: AllPages.php,v 1.12 2002/02/15 17:53:01 carstenklapp Exp $');

require_once('lib/PageList.php');

/**
 */
class WikiPlugin_AllPages
extends WikiPlugin
{
    function getName () {
        return _("AllPages");
    }

    function getDescription () {
        return _("All Pages");
    }

    function getDefaultArguments() {
        return array('noheader'	     => false,
                     'include_empty' => false,
                     'exclude'       => '',
                     'info'          => '',
                     'debug'         => false
                     );
    }
    // info arg allows multiple columns info=mtime,hits,summary,version,author,locked,minor,markup or all
    // exclude arg allows multiple pagenames exclude=HomePage,RecentChanges

    function run($dbi, $argstr, $request) {
        extract($this->getArgs($argstr, $request));

        $pagelist = new PageList($info, $exclude);
        if (!$noheader)
            $pagelist->setCaption(_("Pages in this wiki (%d total):"));

        // deleted pages show up as version 0.
        if ($include_empty)
            $pagelist->_addColumn('version');

        if (defined('DEBUG'))
            $debug = true;

        if ($debug) $time_start = $this->getmicrotime();

        $pagelist->addPages( $dbi->getAllPages($include_empty) );

        if ($debug) $time_end = $this->getmicrotime();

        if ($debug) {
            $time = round($time_end - $time_start, 3);
            return HTML::p(fmt("elapsed time: %s s", $time), $pagelist);
        } else {
            return $pagelist;
        }
    }

    function getmicrotime(){
        list($usec, $sec) = explode(" ",microtime());
        return (float)$usec + (float)$sec;
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
