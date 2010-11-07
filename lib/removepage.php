<?php
rcs_id('$Id: removepage.php,v 1.12 2002/02/14 03:40:00 carstenklapp Exp $');
require_once('lib/Template.php');

function RemovePage (&$request) {
    global $Theme;

    $page = $request->getPage();
    $pagelink = WikiLink($page);

    if ($request->getArg('cancel')) {
        $request->redirect(WikiURL($page));
        // The user probably doesn't see the rest of this.
        $html = HTML(HTML::h2(_("Request Cancelled!")),
                     HTML::p(fmt("Return to %s.", $pagelink)));
    }

    $current = $page->getCurrentRevision();
    $version = $current->getVersion();

    if (!$request->isPost() || !$request->getArg('verify')) {

        // FIXME: button should be class wikiadmin
        $removeB = Button('submit:verify', _("Remove the page now"), 'wikiadmin');
        $cancelB = Button('submit:cancel', _("Cancel"), 'button'); // use generic wiki button look

        $html = HTML(HTML::h2(fmt("You are about to remove '%s' permanently!", $pagelink)),
                     HTML::form(array('method' => 'post',
                                      'action' => WikiURL($page)),
                                HTML::input(array('type' => 'hidden',
                                                  'name' => 'currentversion',
                                                  'value' => $version)),
                                HTML::input(array('type' => 'hidden',
                                                  'name' => 'action',
                                                  'value' => 'remove')),
                                HTML::div(array('class' => 'toolbar'),
                                          $removeB,
                                          $Theme->getButtonSeparator(),
                                          $cancelB)));
    }
    elseif ($request->getArg('currentversion') != $version) {
        $html = HTML(HTML::h2(_("Someone has edited the page!")),
                     HTML::p(fmt("Since you started the deletion process, someone has saved a new version of %s.  Please check to make sure you still want to permanently remove the page from the database.", $pagelink)));
    }
    else {
        // Real delete.
        $pagename = $page->getName();
        $dbi = $request->getDbh();
        $dbi->deletePage($pagename);
        $html = HTML(HTML::h2(fmt("Removed page '%s' succesfully.", $pagename)));
    }

    GeneratePage($html, _("Remove page"));
}


// For emacs users
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
