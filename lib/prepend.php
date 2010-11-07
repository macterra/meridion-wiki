<?php
/* lib/prepend.php
 *
 * Things which must be done and defined before anything else.
 */
$RCS_IDS = '';
function rcs_id ($id) { $GLOBALS['RCS_IDS'] .= "$id\n"; }
rcs_id('$Id: prepend.php,v 1.11 2002/01/28 18:49:08 dairiki Exp $');

error_reporting(E_ALL);
require_once('lib/ErrorManager.php');
require_once('lib/WikiCallback.php');

// FIXME: deprecated
function ExitWiki($errormsg = false)
{
    global $request;
    static $in_exit = 0;

    if (is_object($request))
        $request->finish($errormsg); // NORETURN

    if ($in_exit)
        exit;
    
    $in_exit = true;

    global $ErrorManager;
    $ErrorManager->flushPostponedErrors();
   
    if(!empty($errormsg)) {
        PrintXML(HTML::br(), $errormsg);
        print "\n</body></html>";
    }
    exit;
}

$ErrorManager->setPostponedErrorMask(E_ALL);
$ErrorManager->setFatalHandler(new WikiFunctionCb('ExitWiki'));

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
