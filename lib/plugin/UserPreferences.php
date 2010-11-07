<?php // -*-php-*-
rcs_id('$Id: UserPreferences.php,v 1.2 2002/01/24 00:45:28 dairiki Exp $');
/**
 * Plugin to allow user to adjust his preferences.
 */
class WikiPlugin_UserPreferences
extends WikiPlugin
{
    function getName () {
        return _("UserPreferences");
    }

    /*
    function getDefaultArguments() {
        return array();
    }
    */
    
    function run($dbi, $argstr, $request) {
        return Template('userprefs');
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
