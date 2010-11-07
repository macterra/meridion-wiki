<?php rcs_id('$Id: InlineParser.php,v 1.10 2002/02/08 05:44:54 dairiki Exp $');
/* Copyright (C) 2002, Geoffrey T. Dairiki <dairiki@dairiki.org>
 *
 * This file is part of PhpWiki.
 * 
 * PhpWiki is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * PhpWiki is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with PhpWiki; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
require_once('lib/HtmlElement.php');
require_once('lib/interwiki.php');

//FIXME: intubate ESCAPE_CHAR into BlockParser.php.
define('ESCAPE_CHAR', '~');

/**
 * Return type from RegexpSet::match and RegexpSet::nextMatch.
 *
 * @see RegexpSet
 */
class RegexpSet_match {
    /**
     * The text leading up the the next match.
     */
    var $prematch;

    /**
     * The matched text.
     */
    var $match;

    /**
     * The text following the matched text.
     */
    var $postmatch;

    /**
     * Index of the regular expression which matched.
     */
    var $regexp_ind;
}

/**
 * A set of regular expressions.
 *
 * This class is probably only useful for InlineTransformer.
 */
class RegexpSet
{
    /** Constructor
     *
     * @param $regexps array A list of regular expressions.  The
     * regular expressions should not include any sub-pattern groups
     * "(...)".  (Anonymous groups, like "(?:...)", as well as
     * look-ahead and look-behind assertions are fine.)
     */
    function RegexpSet ($regexps) {
        $this->_regexps = $regexps;
    }

    /**
     * Search text for the next matching regexp from the Regexp Set.
     *
     * @param $text string The text to search.
     *
     * @return object  A RegexpSet_match object, or false if no match.
     */
    function match ($text) {
        return $this->_match($text, $this->_regexps, '*?');
    }

    /**
     * Search for next matching regexp.
     *
     * Here, 'next' has two meanings:
     *
     * Match the next regexp(s) in the set, at the same position as the last match.
     *
     * If that fails, match the whole RegexpSet, starting after the position of the
     * previous match.
     *
     * @param $text string Text to search.
     *
     * @param $prevMatch A RegexpSet_match object
     *
     * $prevMatch should be a match object obtained by a previous
     * match upon the same value of $text.
     *
     * @return object  A RegexpSet_match object, or false if no match.
     */
    function nextMatch ($text, $prevMatch) {
        // Try to find match at same position.
        $pos = strlen($prevMatch->prematch);
        $regexps = array_slice($this->_regexps, $prevMatch->regexp_ind + 1);
        if ($regexps) {
            $repeat = sprintf('{%d}', $pos);
            if ( ($match = $this->_match($text, $regexps, $repeat)) ) {
                $match->regexp_ind += $prevMatch->regexp_ind + 1;
                return $match;
            }
            
        }
        
        // Failed.  Look for match after current position.
        $repeat = sprintf('{%d,}?', $pos + 1);
        return $this->_match($text, $this->_regexps, $repeat);
    }
    

    function _match ($text, $regexps, $repeat) {
        $pat= "/ ( . $repeat ) ( (" . join(')|(', $regexps) . ") ) /Axs";

        if (! preg_match($pat, $text, $m)) {
            return false;
        }
        
        $match = new RegexpSet_match;
        $match->postmatch = substr($text, strlen($m[0]));
        $match->prematch = $m[1];
        $match->match = $m[2];
        $match->regexp_ind = count($m) - 4;

        /* DEBUGGING
        PrintXML(HTML::dl(HTML::dt("input"),
                          HTML::dd(HTML::pre($text)),
                          HTML::dt("match"),
                          HTML::dd(HTML::pre($match->match)),
                          HTML::dt("regexp"),
                          HTML::dd(HTML::pre($regexps[$match->regexp_ind])),
                          HTML::dt("prematch"),
                          HTML::dd(HTML::pre($match->prematch))));
        */
        return $match;
    }
}



/**
 * A simple markup rule (i.e. terminal token).
 *
 * These are defined by a regexp.
 *
 * When a match is found for the regexp, the matching text is replaced.
 * The replacement content is obtained by calling the SimpleMarkup::markup method.
 */ 
class SimpleMarkup
{
    var $_match_regexp;

    /** Get regexp.
     *
     * @return string Regexp which matches this token.
     */
    function getMatchRegexp () {
        return $this->_match_regexp;
    }

    /** Markup matching text.
     *
     * @param $match string The text which matched the regexp
     * (obtained from getMatchRegexp).
     *
     * @return mixed The expansion of the matched text.
     */
    function markup ($match /*, $body */) {
        trigger_error("pure virtual", E_USER_ERROR);
    }
}

/**
 * A balanced markup rule.
 *
 * These are defined by a start regexp, and and end regexp.
 */ 
class BalancedMarkup
{
    var $_start_regexp;

    /** Get the starting regexp for this rule.
     *
     * @return string The starting regexp.
     */
    function getStartRegexp () {
        return $this->_start_regexp;
    }
    
    /** Get the ending regexp for this rule.
     *
     * @param $match string  The text which matched the starting regexp.
     *
     * @return string The ending regexp.
     */
    function getEndRegexp ($match) {
        return $this->_end_regexp;
    }

    /** Get expansion for matching input.
     *
     * @param $match string  The text which matched the starting regexp.
     *
     * @param $body mixed Transformed text found between the starting
     * and ending regexps.
     *
     * @return mixed The expansion of the matched text.
     */
    function markup ($match, $body) {
        trigger_error("pure virtual", E_USER_ERROR);
    }
}

class Markup_escape  extends SimpleMarkup
{
    function getMatchRegexp () {
        return ESCAPE_CHAR . ".";
    }
    
    function markup ($match) {
        return $match[1];
    }
}

class Markup_bracketlink  extends SimpleMarkup
{
    var $_match_regexp = "\\[ .*?\S.*? \\]";
    
    function markup ($match) {
        $link = LinkBracketLink($match);
        assert($link->isInlineElement());
        return $link;
    }
}

class Markup_url extends SimpleMarkup
{
    function getMatchRegexp () {
        global $AllowedProtocols;
        return "(?<![[:alnum:]]) (?:$AllowedProtocols) : [^\s<>\"']+ (?<![ ,.?; \] \) ])";
    }
    
    function markup ($match) {
        return LinkURL($match);
    }
}


class Markup_interwiki extends SimpleMarkup
{
    function getMatchRegexp () {
        global $request;
        $map = InterWikiMap::GetMap($request);
        return "(?<! [[:alnum:]])" . $map->getRegexp(). ": \S+ (?<![ ,.?; \] \) \" \' ])";
    }

    function markup ($match) {
        global $request;
        $map = InterWikiMap::GetMap($request);
        return $map->link($match);
    }
}

class Markup_wikiword extends SimpleMarkup
{
    function getMatchRegexp () {
        global $WikiNameRegexp;
        return " $WikiNameRegexp";
    }
        
    function markup ($match) {
        return WikiLink($match, 'auto');
    }
}

class Markup_linebreak extends SimpleMarkup
{
    var $_match_regexp = "(?: (?<! %) %%% (?! %) | <br> )";

    function markup () {
        return HTML::br();
    }
}

class Markup_old_emphasis  extends BalancedMarkup
{
    var $_start_regexp = "''|__";

    function getEndRegexp ($match) {
        return $match;
    }
    
    function markup ($match, $body) {
        $tag = $match == "''" ? 'em' : 'strong';
        return new HtmlElement($tag, $body);
    }
}

class Markup_nestled_emphasis extends BalancedMarkup
{
    //var $_start_regexp = "(?<! [[:alnum:]] ) [*_=] (?=[[:alnum:]])";
    var $_start_regexp = "(?<= \s | ^  ) [*_=] (?= \S)";

    function getEndRegexp ($match) {
        //return "(?<= [[:alnum:]]) \\$match (?![[:alnum:]])";
        return "(?<= \S) \\$match (?= \s | $)";
    }
    
    function markup ($match, $body) {
        switch ($match) {
        case '*': return new HtmlElement('b', $body);
        case '=': return new HtmlElement('tt', $body);
        default:  return new HtmlElement('i', $body);
        }
    }
}

class Markup_html_emphasis extends BalancedMarkup
{
    var $_start_regexp = "<(?: b|big|i|small|tt|
                               abbr|acronym|cite|code|dfn|kbd|samp|strong|var|
                               sup|sub )>";

    function getEndRegexp ($match) {
        return "<\\/" . substr($match, 1);
    }
    
    function markup ($match, $body) {
        $tag = substr($match, 1, -1);
        return new HtmlElement($tag, $body);
    }
}

// FIXME: Do away with magic phpwiki forms.  (Maybe phpwiki: links too?)
// FIXME: Do away with plugin-links.  They seem not to be used.
//Plugin link


class InlineTransformer
{
    var $_regexps = array();
    var $_markup = array();
    
    function InlineTransformer ($markup_types = false) {
        if (!$markup_types)
            $markup_types = array('escape', 'bracketlink', 'url',
                                  'interwiki', 'wikiword', 'linebreak',
                                  'old_emphasis', 'nestled_emphasis',
                                  'html_emphasis');

        foreach ($markup_types as $mtype) {
            $class = "Markup_$mtype";
            $this->_addMarkup(new $class);
        }
    }

    function _addMarkup ($markup) {
        if (isa($markup, 'SimpleMarkup'))
            $regexp = $markup->getMatchRegexp();
        else
            $regexp = $markup->getStartRegexp();

        assert(!isset($this->_markup[$regexp]));
        $this->_regexps[] = $regexp;
        $this->_markup[] = $markup;
    }
        
    function parse (&$text, $end_regexps = array('$')) {
        $regexps = $this->_regexps;

        // $end_re takes precedence: "favor reduce over shift"
        array_unshift($regexps, $end_regexps[0]);
        $regexps = new RegexpSet($regexps);
        
        $input = $text;
        $output = new XmlContent;

        $match = $regexps->match($input);
        
        while ($match) {
            if ($match->regexp_ind == 0) {
                // No start pattern found before end pattern.
                // We're all done!
                $output->pushContent($match->prematch);
                $text = $match->postmatch;
                return $output;
            }

            $markup = $this->_markup[$match->regexp_ind - 1];
            $body = $this->_parse_markup_body($markup, $match->match, $match->postmatch, $end_regexps);
            if (!$body) {
                // Couldn't match balanced expression.
                // Ignore and look for next matching start regexp.
                $match = $regexps->nextMatch($input, $match);
                continue;
            }

            // Matched markup.  Eat input, push output.
            // FIXME: combine adjacent strings.
            $input = $match->postmatch;
            $output->pushContent($match->prematch,
                                 $markup->markup($match->match, $body));

            $match = $regexps->match($input);
        }

        // No pattern matched, not even the end pattern.
        // Parse fails.
        return false;
    }

    function _parse_markup_body ($markup, $match, &$text, $end_regexps) {
        if (isa($markup, 'SimpleMarkup'))
            return true;        // Done. SimpleMarkup is simple.

        array_unshift($end_regexps, $markup->getEndRegexp($match));
        // Optimization: if no end pattern in text, we know the
        // parse will fail.  This is an important optimization,
        // e.g. when text is "*lots *of *start *delims *with
        // *no *matching *end *delims".
        $ends_pat = "/(?:" . join(").*(?:", $end_regexps) . ")/xs";
        if (!preg_match($ends_pat, $text))
            return false;
        return $this->parse($text, $end_regexps);
    }
}

class LinkTransformer extends InlineTransformer
{
    function LinkTransformer () {
        $this->InlineTransformer(array('escape', 'bracketlink', 'url',
                                       'interwiki', 'wikiword'));
    }
}

function TransformInline($text) {
    static $trfm;
    
    if (empty($trfm)) {
        $trfm = new InlineTransformer;
    }
    
    return $trfm->parse($text);
}

function TransformLinks($text, $markup = 2.0) {
    static $trfm;
    
    if (empty($trfm)) {
        $trfm = new LinkTransformer;
    }

    if ($markup < 2.0)
        $text = ConvertOldMarkup($text, 'links');
    
    return $trfm->parse($text);
}

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
