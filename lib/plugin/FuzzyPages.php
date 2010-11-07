<?php // -*-php-*-
rcs_id('$Id: FuzzyPages.php,v 1.3 2002/02/02 06:13:08 carstenklapp Exp $');

//require_once('lib/TextSearchQuery.php');
//require_once('lib/PageList.php');

/**
 * FuzzyChanges is an experimental plugin which looks for similar page titles.
 * 
 * Pages are considered similar if they sound similar - metaphone() (english only)
 * or if the name is written similar - levenshtein()
 *
 */
class WikiPlugin_FuzzyPages
extends WikiPlugin
{
    function getName() {
        return _("FuzzyPages");
    }

    function getDescription() {
        return sprintf(_("List FuzzyPages for %s"), '[pagename]');
    }

    function getDefaultArguments() {
        return array('page'	=> '[pagename]',
                     's'	=> false);
    }

    function run($dbi, $argstr, $request) {
        $args = $this->getArgs($argstr, $request);
        extract($args);
        if (empty($page))
            return '';

        $thispage = $page;
if ($s) $thispage = $s;
        $list = array();
        $pages = $dbi->getAllPages();

        while ($page = $pages->next()) {
            $name = $page->getName();

            // name length comparison
            $lengthdiff = abs(strlen($name) - strlen($thispage));

            // english soundex comparison, higher = similar
            $metaphone_similar = similar_text(metaphone($thispage), metaphone($name));

            // spelling similarity comparison, 0 = identical
            $levenshtein = levenshtein($thispage, $name);

            // fudge factors
            $metamin = 2;
            $lengthdiffweight = 0.5;
            $metaphone_priority_factor = 2.5;
            $levenmax = 5 + $lengthdiffweight * $lengthdiff;

            $similarity_score = $metaphone_priority_factor * $metaphone_similar - $levenshtein;

            if ($similarity_score < -20 || $levenshtein > $levenmax || $metaphone_similar < $metamin)
                continue;
            $offset = +8; // more fudge
            $list = array_merge($list, array($name => $similarity_score + $offset));
        }

        array_multisort($list, SORT_NUMERIC, SORT_DESC);

        $table = HTML::table(array('cellpadding' => 2,
                                   'cellspacing' => 1,
                                   'border'      => 0,
                                   'class'	 => 'pagelist'));
        $descrip = fmt("These page titles match fuzzy with '%s'",
                        WikiLink($thispage, 'auto'));
        $table->pushContent(HTML::caption(array('align'=>'top'), $descrip));
        foreach ($list as $key => $val) {
            $row = HTML::tr(HTML::td(WikiLink($key)),
                            HTML::td(array('align' => 'right'), $val));
            $table->pushContent($row);
        }
        return $table;
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
