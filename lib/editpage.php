<?php
rcs_id('$Id: editpage.php,v 1.2 2003/09/04 15:45:29 root Exp root $');

require_once('lib/Template.php');

class PageEditor
{
    function PageEditor (&$request) {
        $this->request = &$request;

        $this->user = $request->getUser();
        $this->page = $request->getPage();

        $this->current = $this->page->getCurrentRevision();

        $this->meta = array('author' => $this->user->getId(),
                            'locked' => $this->page->get('locked'),
                            'author_id' => $this->user->getAuthenticatedId());

        $version = $request->getArg('version');
        if ($version !== false) {
            $this->selected = $this->page->getRevision($version);
            $this->version = $version;
        }
        else {
            $this->selected = $this->current;
            $this->version = $this->current->getVersion();
        }

        if ($this->_restoreState()) {
            $this->_initialEdit = false;
        }
        else {
            $this->_initializeState();
            $this->_initialEdit = true;
        }
    }

    function editPage () {
        $saveFailed = false;
        $tokens = array();

        if ($this->canEdit()) {
            if ($this->isInitialEdit())
                return $this->viewSource();
            $tokens['PAGE_LOCKED_MESSAGE'] = $this->getLockedMessage();
        }
        elseif ($this->editaction == 'save') {
            if ($this->savePage())
                return true;    // Page saved.
            $saveFailed = true;
        }

        if ($saveFailed || $this->isConcurrentUpdate())
        {
            // Get the text of the original page, and the two conflicting edits
            // The diff3 class takes arrays as input.  So retrieve content as
            // an array, or convert it as necesary.
            $orig = $this->page->getRevision($this->_currentVersion);
            $orig_content = $orig->getContent();
            $this_content = explode("\n", $this->_content);
            $other_content = $this->current->getContent();
            include_once("lib/diff3.php");
            $diff = new diff3($orig_content, $this_content, $other_content);
            $output = $diff->merged_output(_("Your version"), _("Other version"));
            // Set the content of the textarea to the merged diff output, and update the version
            $this->_content = implode ("\n", $output);
            $this->_currentVersion = $this->current->getVersion();
            $this->version = $this->_currentVersion;
            $unresolved = $diff->ConflictingBlocks;
            $tokens['CONCURRENT_UPDATE_MESSAGE'] = $this->getConflictMessage($unresolved);
        }

        if ($this->editaction == 'preview')
            $tokens['PREVIEW_CONTENT'] = $this->getPreview(); // FIXME: convert to _MESSAGE?

        // FIXME: NOT_CURRENT_MESSAGE?

        $tokens = array_merge($tokens, $this->getFormElements());

        return $this->output('editpage', _("Edit: %s"), $tokens);
    }

    function output ($template, $title_fs, $tokens) {
        $selected = &$this->selected;
        $current = &$this->current;

        if ($selected && $selected->getVersion() != $current->getVersion()) {
            $rev = $selected;
            $pagelink = WikiLink($selected);
        }
        else {
            $rev = $current;
            $pagelink = WikiLink($this->page);
        }


        $title = new FormattedText ($title_fs, $pagelink);
        $template = Template($template, $tokens);

        GeneratePage($template, $title, $rev);
        return true;
    }


    function viewSource ($tokens = false) {
        assert($this->isInitialEdit());
        assert($this->selected);

        $tokens['PAGE_SOURCE'] = $this->_content;

        return $this->output('viewsource', _("View Source: %s"), $tokens);
    }

    function setPageLockChanged($isadmin, $lock, &$page) {
        if ($isadmin) {
            if (! $page->get('locked') == $lock) {
                $request = &$this->request;
                $request->setArg('lockchanged', true); //is it safe to add new args to $request like this?
            }
            $page->set('locked', $lock);
        }
    }

    function savePage () {
        $request = &$this->request;

        if ($this->isUnchanged()) {
            // Allow admin lock/unlock even if
            // no text changes were made.
            if ($isadmin = $this->user->isadmin()) {
                $page = &$this->page;
                $lock = $this->meta['locked'];
                $this->setPageLockChanged($isadmin, $lock, &$page);
            }
            // Save failed. No changes made.
            include_once('lib/display.php');
            // force browse of current version:
            $request->setArg('version', false);
            displayPage($request, 'nochanges');
            return true;
        }

        $page = &$this->page;
        $lock = $this->meta['locked'];
        $this->meta['locked'] = ''; // hackish

        // Save new revision
        $newrevision = $page->createRevision($this->_currentVersion + 1,
                                             $this->_content,
                                             $this->meta,
                                             ExtractWikiPageLinks($this->_content));
        if (!is_object($newrevision)) {
            // Save failed.  (Concurrent updates).
            return false;
        }
        // New contents successfully saved...
        if ($isadmin = $this->user->isadmin())
            $this->setPageLockChanged($isadmin, $lock, &$page);

        // Clean out archived versions of this page.
        include_once('lib/ArchiveCleaner.php');
        $cleaner = new ArchiveCleaner($GLOBALS['ExpireParams']);
        $cleaner->cleanPageRevisions($page);

        $dbi = $request->getDbh();
        $warnings = $dbi->GenericWarnings();

        global $Theme;
        if (empty($warnings) && ! $Theme->getImageURL('signature')) {
            // Do redirect to browse page if no signature has
            // been defined.  In this case, the user will most
            // likely not see the rest of the HTML we generate
            // (below).
            $request->redirect(WikiURL($page, false, 'absolute_url'));
        }

        // Force browse of current page version.
        $request->setArg('version', false);

        require_once('lib/PageType.php');
        $transformedText = PageType($newrevision);
        $template = Template('savepage',
                             array('CONTENT' => $transformedText));
        //include_once('lib/BlockParser.php');
        //$template = Template('savepage',
        //                     array('CONTENT' => TransformText($newrevision)));
        if (!empty($warnings))
            $template->replace('WARNINGS', $warnings);

        $pagelink = WikiLink($page);

        GeneratePage($template, fmt("Saved: %s", $pagelink), $newrevision);

        $minor = $newrevision->get('is_minor_edit');
        if ($minor) return true;

	$page = $page->getName();
	$author = $newrevision->get('author');
	$summary = $newrevision->get('summary');
	$version = $newrevision->getVersion();
	$href = "http://churchofvirus.org/wiki/$page?version=$version";

	$to = "virus-wiki";
	$subject = $page;
	$body = "$author saved version $version of $subject\r\n";
	$body .= "summary: $summary\r\n";
	$body .= "<$href>\r\n";
	$from = "wikid@lucifer.com";
	$hdrs = "From: WikiDaemon <$from>\r\n";

        mail($to, $subject, $body, $hdrs, "-f$from");
        return true;
    }

    function isConcurrentUpdate () {
        assert($this->current->getVersion() >= $this->_currentVersion);
        return $this->current->getVersion() != $this->_currentVersion;
    }

    function canEdit () {
        return $this->page->get('locked') && !$this->user->isAdmin();
    }

    function isInitialEdit () {
        return $this->_initialEdit;
    }

    function isUnchanged () {
        $current = &$this->current;

        if ($this->meta['markup'] !=  $current->get('markup'))
            return false;

        return $this->_content == $current->getPackedContent();
    }

    function getPreview () {
        require_once('lib/PageType.php');
        return PageType($this->_content, $this->page->getName(), $this->meta['markup']);

        //include_once('lib/BlockParser.php');
        //return TransformText($this->_content, $this->meta['markup']);
    }

    function getLockedMessage () {
        return
        HTML(HTML::h2(_("Page Locked")),
             HTML::p(_("This page has been locked by the administrator so your changes can not be saved.")),
             HTML::p(_("(Copy your changes to the clipboard. You can try editing a different page or save your text in a text editor.)")),
             HTML::p(_("Sorry for the inconvenience.")));
    }

    function getConflictMessage ($unresolved = false) {
        /*
         xgettext only knows about c/c++ line-continuation strings
         it does not know about php's dot operator.
         We want to translate this entire paragraph as one string, of course.
         */

        //$re_edit_link = Button('edit', _("Edit the new version"), $this->page);

        if ($unresolved)
            $message =  HTML::p(fmt("Some of the changes could not automatically be combined.  Please look for sections beginning with '%s', and ending with '%s'.  You will need to edit those sections by hand before you click Save.",
                                "<<<<<<< ". _("Your version"),
                                ">>>>>>> ". _("Other version")));
        else
            $message = HTML::p(_("Please check it through before saving."));



        /*$steps = HTML::ol(HTML::li(_("Copy your changes to the clipboard or to another temporary place (e.g. text editor).")),
          HTML::li(fmt("%s of the page. You should now see the most current version of the page. Your changes are no longer there.",
                       $re_edit_link)),
          HTML::li(_("Make changes to the file again. Paste your additions from the clipboard (or text editor).")),
          HTML::li(_("Save your updated changes.")));
        */
        return HTML(HTML::h2(_("Conflicting Edits!")),
                    HTML::p(_("In the time since you started editing this page, another user has saved a new version of it.")),
                    HTML::p(_("Your changes can not be saved as they are, since doing so would overwrite the other author's changes. So, your changes and those of the other author have been combined. The result is shown below.")),
                    $message);
    }


    function getTextArea () {
        $request = &$this->request;

        // wrap=virtual is not HTML4, but without it NS4 doesn't wrap long lines
        $readonly = $this->canEdit(); // || $this->isConcurrentUpdate();

        return HTML::textarea(array('class'    => 'wikiedit',
                                    'name'     => 'edit[content]',
                                    'rows'     => $request->getPref('editHeight'),
                                    'cols'     => $request->getPref('editWidth'),
                                    'readonly' => (bool) $readonly,
                                    'wrap'     => 'soft'),
                              $this->_content);
    }

    function getFormElements () {
        $request = &$this->request;
        $page = &$this->page;


        $h = array('action'   => 'edit',
                   'pagename' => $page->getName(),
                   'version'  => $this->version,
                   'edit[current_version]' => $this->_currentVersion);

        $el['HIDDEN_INPUTS'] = HiddenInputs($h);


        $el['EDIT_TEXTAREA'] = $this->getTextArea();

        $el['SUMMARY_INPUT']
            = HTML::input(array('type'  => 'text',
                                'class' => 'wikitext',
                                'name'  => 'edit[summary]',
                                'size'  => 50,
                                'maxlength' => 256,
                                'value' => $this->meta['summary']));
        $el['MINOR_EDIT_CB']
            = HTML::input(array('type' => 'checkbox',
                                'name'  => 'edit[minor_edit]',
                                'checked' => (bool) $this->meta['is_minor_edit']));
        $el['NEW_MARKUP_CB']
            = HTML::input(array('type' => 'checkbox',
                                'name' => 'edit[markup]',
                                'value' => 'new',
                                'checked' => $this->meta['markup'] >= 2.0));

        $el['LOCKED_CB']
            = HTML::input(array('type' => 'checkbox',
                                'name' => 'edit[locked]',
                                'disabled' => (bool) !$this->user->isadmin(),
                                'checked'  => (bool) $this->meta['locked']));

        $el['PREVIEW_B'] = Button('submit:edit[preview]', _("Preview"), 'wikiaction');

        //if (!$this->isConcurrentUpdate() && !$this->canEdit())
        $el['SAVE_B'] = Button('submit:edit[save]', _("Save"), 'wikiaction');

        $el['IS_CURRENT'] = $this->version == $this->current->getVersion();

        return $el;
    }


    function _restoreState () {
        $request = &$this->request;

        $posted = $request->getArg('edit');
        $request->setArg('edit', false);

        if (!$posted || !$request->isPost() || $request->getArg('action') != 'edit')
            return false;

        if (!isset($posted['content']) || !is_string($posted['content']))
            return false;
        $this->_content = preg_replace('/[ \t\r]+\n/', "\n",
                                        rtrim($posted['content']));

        $this->_currentVersion = (int) $posted['current_version'];

        if ($this->_currentVersion < 0)
            return false;
        if ($this->_currentVersion > $this->current->getVersion())
            return false;       // FIXME: some kind of warning?

        $is_new_markup = !empty($posted['markup']) && $posted['markup'] == 'new';
        $meta['markup'] = $is_new_markup ? 2.0: false;
        $meta['summary'] = trim(substr($posted['summary'], 0, 256));
        $meta['locked'] = !empty($posted['locked']);
        $meta['is_minor_edit'] = !empty($posted['minor_edit']);

        $this->meta = array_merge($this->meta, $meta);

        if (!empty($posted['preview']))
            $this->editaction = 'preview';
        elseif (!empty($posted['save']))
            $this->editaction = 'save';
        else
            $this->editaction = 'edit';

        return true;
    }

    function _initializeState () {
        $request = &$this->request;
        $current = &$this->current;
        $selected = &$this->selected;
        $user = &$this->user;

        if (!$selected)
            NoSuchRevision($request, $this->page, $this->version); // noreturn

        $this->_currentVersion = $current->getVersion();
        $this->_content = $selected->getPackedContent();

        $this->meta['summary'] = '';
        $this->meta['locked'] = $this->page->get('locked');

        // If author same as previous author, default minor_edit to on.
        $age = time() - $current->get('mtime');
        $this->meta['is_minor_edit'] = ( $age < MINOR_EDIT_TIMEOUT
                                         && $current->get('author') == $user->getId()
                                         );

        // Default for new pages is new-style markup.
        if ($selected->hasDefaultContents())
            $is_new_markup = true;
        else
            $is_new_markup = $selected->get('markup') >= 2.0;

        $this->meta['markup'] = $is_new_markup ? 2.0: false;
        $this->editaction = 'edit';
    }
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
