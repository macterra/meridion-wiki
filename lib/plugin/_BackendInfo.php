<?php // -*-php-*-
rcs_id('$Id: _BackendInfo.php,v 1.13 2002/02/22 23:12:54 carstenklapp Exp $');
require_once('lib/Template.php');
/**
 */
class WikiPlugin__BackendInfo
extends WikiPlugin
{
    function getName () {
        return _("DebugInfo");
    }

    function getDescription () {
        return sprintf(_("Get debugging information for %s."), '[pagename]');
    }

    function getDefaultArguments() {
        return array('page' => '[pagename]');
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (empty($page))
            return '';

        $backend = &$dbi->_backend;

        $html = HTML(HTML::h3(fmt("Querying backend directly for '%s'", $page)));


        $table = HTML::table(array('border' => 1,
                                   'cellpadding' => 2,
                                   'cellspacing' => 0));
        $pagedata = $backend->get_pagedata($page);
        if (!$pagedata)
            $html->pushContent(HTML::p(fmt("No pagedata for %s", $page)));
        else {
            $table->pushContent($this->_showhash("get_pagedata('$page')",
                                                 $pagedata));
        }

        for ($version = $backend->get_latest_version($page);
             $version;
             $version = $backend->get_previous_version($page, $version))
        {
            $vdata = $backend->get_versiondata($page, $version, true);

            $content = &$vdata['%content'];
            if ($content === true)
                $content = '<true>';
            elseif (strlen($content) > 40)
                $content = substr($content,0,40) . " ...";

            $table->pushContent(HTML::tr(HTML::td(array('colspan' => 2))));
            $table->pushContent($this->_showhash("get_versiondata('$page',$version)",
                                                 $vdata));
        }

        $html->pushContent($table);
        return $html;
    }

    function _showhash ($heading, $hash) {
        $rows[] = HTML::tr(array('bgcolor' => '#ffcccc',
                                 'style' => 'color:#000000'),
                           HTML::td(array('colspan' => 2,
                                          'style' => 'color:#000000'), $heading));
        ksort($hash);
        foreach ($hash as $key => $val)
            $rows[] = HTML::tr(HTML::td(array('align' => 'right',
                                              'bgcolor' => '#cccccc',
                                              'style' => 'color:#000000'),
                                        NBSP . $key . NBSP),
                               HTML::td(array('bgcolor' => '#ffffff',
                                              'style' => 'color:#000000'),
                                        $val ? $val : NBSP));
        return $rows;
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
