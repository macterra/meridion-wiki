<?php // -*-php-*-
rcs_id('$Id:');


/**
 * A plugin to provide for raw HTML within wiki pages.
 */
class WikiPlugin_EmbedPage
extends WikiPlugin
{
    function getName () {
        return "EmbedPage";
    }
    

    function getDefaultArguments() {
        return array('src'       => '',
                     'align'     => 'center',
                     'width'     => '80%',
                     'height'    => '400px'
                     );
    }
 
    function run($dbi, $argstr, $request) {
        $this->_args = $this->getArgs($argstr, $request);
        extract($this->_args);

        if (!$src)
            return '';

        return HTML::raw("<p align=$align><iframe src=$src width=$width height=$height></iframe></p>");
    }
}

?>
