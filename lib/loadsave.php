<?php rcs_id('$Id: loadsave.php,v 1.61 2002/02/23 20:27:03 rurban Exp $');

require_once("lib/ziplib.php");
require_once("lib/Template.php");

function StartLoadDump(&$request, $title, $html = '')
{
    // FIXME: This is a hack
    $tmpl = Template('top', array('TITLE' => $title,
                                  'HEADER' => $title,
                                  'CONTENT' => '%BODY%'));
    echo ereg_replace('%BODY%.*', '', $tmpl->getExpansion($html));
}

function EndLoadDump(&$request)
{
    // FIXME: This is a hack
    $pagelink = WikiLink($request->getPage());

    PrintXML(HTML::p(HTML::strong(_("Complete."))),
             HTML::p(fmt("Return to %s", $pagelink)));
    echo "</body></html>\n";
}


////////////////////////////////////////////////////////////////
//
//  Functions for dumping.
//
////////////////////////////////////////////////////////////////

/**
 * For reference see:
 * http://www.nacs.uci.edu/indiv/ehood/MIME/2045/rfc2045.html
 * http://www.faqs.org/rfcs/rfc2045.html
 * (RFC 1521 has been superceeded by RFC 2045 & others).
 *
 * Also see http://www.faqs.org/rfcs/rfc2822.html
 */
function MailifyPage ($page, $nversions = 1)
{
    $current = $page->getCurrentRevision();
    $head = '';

    if (STRICT_MAILABLE_PAGEDUMPS) {
        $from = defined('SERVER_ADMIN') ? SERVER_ADMIN : 'foo@bar';
        //This is for unix mailbox format: (not RFC (2)822)
        // $head .= "From $from  " . CTime(time()) . "\r\n";
        $head .= "Subject: " . rawurlencode($page->getName()) . "\r\n";
        $head .= "From: $from (PhpWiki)\r\n";
        // RFC 2822 requires only a Date: and originator (From:)
        // field, however the obsolete standard RFC 822 also
        // requires a destination field.
        $head .= "To: $from (PhpWiki)\r\n";
    }
    $head .= "Date: " . Rfc2822DateTime($current->get('mtime')) . "\r\n";
    $head .= sprintf("Mime-Version: 1.0 (Produced by PhpWiki %s)\r\n",
                     PHPWIKI_VERSION);

    // This should just be entered by hand (or by script?)
    // in the actual pgsrc files, since only they should have
    // RCS ids.
    //$head .= "X-Rcs-Id: \$Id\$\r\n";

    $iter = $page->getAllRevisions();
    $parts = array();
    while ($revision = $iter->next()) {
        $parts[] = MimeifyPageRevision($revision);
        if ($nversions > 0 && count($parts) >= $nversions)
            break;
    }
    if (count($parts) > 1)
        return $head . MimeMultipart($parts);
    assert($parts);
    return $head . $parts[0];
}

/***
 * Compute filename to used for storing contents of a wiki page.
 *
 * Basically we do a rawurlencode() which encodes everything except
 * ASCII alphanumerics and '.', '-', and '_'.
 *
 * But we also want to encode leading dots to avoid filenames like
 * '.', and '..'. (Also, there's no point in generating "hidden" file
 * names, like '.foo'.)
 *
 * @param $pagename string Pagename.
 * @return string Filename for page.
 */
function FilenameForPage ($pagename)
{
    $enc = rawurlencode($pagename);
    return preg_replace('/^\./', '%2e', $enc);
}

/**
 * The main() function which generates a zip archive of a PhpWiki.
 *
 * If $include_archive is false, only the current version of each page
 * is included in the zip file; otherwise all archived versions are
 * included as well.
 */
function MakeWikiZip (&$request)
{
    if ($request->getArg('include') == 'all') {
        $zipname         = "wikidb.zip";
        $include_archive = true;
    }
    else {
        $zipname         = "wiki.zip";
        $include_archive = false;
    }



    $zip = new ZipWriter("Created by PhpWiki", $zipname);

    $dbi = $request->getDbh();
    $pages = $dbi->getAllPages();
    while ($page = $pages->next()) {
        if (! get_cfg_var('safe_mode'))
            set_time_limit(30);	// Reset watchdog.

        $current = $page->getCurrentRevision();
        if ($current->getVersion() == 0)
            continue;


        $attrib = array('mtime'    => $current->get('mtime'),
                        'is_ascii' => 1);
        if ($page->get('locked'))
            $attrib['write_protected'] = 1;

        if ($include_archive)
            $content = MailifyPage($page, 0);
        else
            $content = MailifyPage($page);

        $zip->addRegularFile( FilenameForPage($page->getName()),
                              $content, $attrib);
    }
    $zip->finish();
}

function DumpToDir (&$request)
{
    $directory = $request->getArg('directory');
    if (empty($directory))
        $request->finish(_("You must specify a directory to dump to"));

    // see if we can access the directory the user wants us to use
    if (! file_exists($directory)) {
        if (! mkdir($directory, 0755))
            $request->finish(fmt("Cannot create directory '%s'", $directory));
        else
            $html = HTML::p(fmt("Created directory '%s' for the page dump...",
                                $directory));
    } else {
        $html = HTML::p(fmt("Using directory '%s'", $directory));
    }

    StartLoadDump($request, _("Dumping Pages"), $html);

    $dbi = $request->getDbh();
    $pages = $dbi->getAllPages();

    while ($page = $pages->next()) {

        $filename = FilenameForPage($page->getName());

        $msg = HTML(HTML::br(), $page->getName(), ' ... ');

        if($page->getName() != $filename) {
            $msg->pushContent(HTML::small(fmt("saved as %s", $filename)),
                              " ... ");
        }

        if ($request->getArg('include') == 'all')
            $data = MailifyPage($page, 0);
        else
            $data = MailifyPage($page);

        if ( !($fd = fopen("$directory/$filename", "w")) ) {
            $msg->pushContent(HTML::strong(fmt("couldn't open file '%s' for writing",
                                               "$directory/$filename")));
            $request->finish($msg);
        }

        $num = fwrite($fd, $data, strlen($data));
        $msg->pushContent(HTML::small(fmt("%s bytes written", $num)));
        PrintXML($msg);

        flush();
        assert($num == strlen($data));
        fclose($fd);
    }

    EndLoadDump($request);
}


function DumpHtmlToDir (&$request)
{
    $directory = $request->getArg('directory');
    if (empty($directory))
        $request->finish(_("You must specify a directory to dump to"));

    // see if we can access the directory the user wants us to use
    if (! file_exists($directory)) {
        if (! mkdir($directory, 0755))
            $request->finish(fmt("Cannot create directory '%s'", $directory));
        else
            $html = HTML::p(fmt("Created directory '%s' for the page dump...",
                                $directory));
    } else {
        $html = HTML::p(fmt("Using directory '%s'", $directory));
    }

    StartLoadDump($request, _("Dumping Pages"), $html);

    $dbi = $request->getDbh();
    $pages = $dbi->getAllPages();

    while ($page = $pages->next()) {

        $filename = FilenameForPage($page->getName()); /* . ".html";*/

        $msg = HTML(HTML::br(), $page->getName(), ' ... ');

        if($page->getName() != $filename) {
            $msg->pushContent(HTML::small(fmt("saved as %s", $filename)),
                              " ... ");
        }

        $revision = $page->getCurrentRevision();

        require_once('lib/PageType.php');
        $transformedContent = PageType($revision);

        $template = new Template('browse', $request,
                                  array('revision' => $revision, 'CONTENT' => $transformedContent));

        $data = GeneratePageasXML($template, $page->getName());

        if ( !($fd = fopen("$directory/$filename", "w")) ) {
            $msg->pushContent(HTML::strong(fmt("couldn't open file '%s' for writing",
                                               "$directory/$filename")));
            $request->finish($msg);
        }

        $num = fwrite($fd, $data, strlen($data));
        $msg->pushContent(HTML::small(fmt("%s bytes written", $num)));
        PrintXML($msg);

        flush();
        assert($num == strlen($data));
        fclose($fd);
    }

    //CopyImageFiles() will go here;

    EndLoadDump($request);
}


////////////////////////////////////////////////////////////////
//
//  Functions for restoring.
//
////////////////////////////////////////////////////////////////

function SavePage (&$request, $pageinfo, $source, $filename)
{
    $pagedata    = $pageinfo['pagedata'];    // Page level meta-data.
    $versiondata = $pageinfo['versiondata']; // Revision level meta-data.

    if (empty($pageinfo['pagename'])) {
        PrintXML(HTML::dt(HTML::strong(_("Empty pagename!"))));
        return;
    }

    if (empty($versiondata['author_id']))
        $versiondata['author_id'] = $versiondata['author'];

    $pagename = $pageinfo['pagename'];
    $content  = $pageinfo['content'];

    if ($pagename ==_("InterWikiMap"))
        $content = _tryinsertInterWikiMap($content);

    $dbi = $request->getDbh();
    $page = $dbi->getPage($pagename);

    foreach ($pagedata as $key => $value) {
        if (!empty($value))
            $page->set($key, $value);
    }

    $mesg = HTML::dd();
    $skip = false;
    if ($source)
        $mesg->pushContent(' ', fmt("from %s", $source));


    $current = $page->getCurrentRevision();
    if ($current->getVersion() == 0) {
        $mesg->pushContent(' ', _("new page"));
        $isnew = true;
    }
    else {
        if ($current->getPackedContent() == $content
            && $current->get('author') == $versiondata['author']) {
            $mesg->pushContent(' ',
                               fmt("is identical to current version %d - skipped",
                                   $current->getVersion()));
            $skip = true;
        }
        $isnew = false;
    }

    if (! $skip) {
        $new = $page->createRevision(WIKIDB_FORCE_CREATE, $content,
                                     $versiondata,
                                     ExtractWikiPageLinks($content));

        $mesg->pushContent(' ', fmt("- saved to database as version %d",
                                    $new->getVersion()));
    }

    PrintXML(HTML::dt(WikiLink($pagename)), $mesg);
    flush();
}

function _tryinsertInterWikiMap($content) {
    $goback = false;
    if (strpos($content, "<verbatim>")) {
        //$error_html = " The newly loaded pgsrc already contains a verbatim block.";
        $goback = true;
    }
    if (!$goback && !defined('INTERWIKI_MAP_FILE')) {
        $error_html = sprintf(" "._("%s: not defined"), "INTERWIKI_MAP_FILE");
        $goback = true;
    }
    if (!$goback && !file_exists(INTERWIKI_MAP_FILE)) {
        $error_html = sprintf(" "._("%s: file not found"), INTERWIKI_MAP_FILE);
        $goback = true;
    }

    if (!empty($error_html))
        trigger_error(_("Default InterWiki map file not loaded.")
                      . $error_html, E_USER_NOTICE);

    if ($goback)
        return $content;

    $filename = INTERWIKI_MAP_FILE;
    trigger_error(sprintf(_("Loading InterWikiMap from external file %s."),
                          $filename), E_USER_NOTICE);

    $fd = fopen ($filename, "rb");
    $data = fread ($fd, filesize($filename));
    fclose ($fd);
    $content = $content . "\n<verbatim>\n$data</verbatim>\n";
    return $content;
}

function ParseSerializedPage($text, $default_pagename, $user)
{
    if (!preg_match('/^a:\d+:{[si]:\d+/', $text))
        return false;
    
    $pagehash = unserialize($text);
    
    // Split up pagehash into four parts:
    //   pagename
    //   content
    //   page-level meta-data
    //   revision-level meta-data
    
    if (!defined('FLAG_PAGE_LOCKED'))
        define('FLAG_PAGE_LOCKED', 1);
    $pageinfo = array('pagedata'    => array(),
                      'versiondata' => array());
    
    $pagedata = &$pageinfo['pagedata'];
    $versiondata = &$pageinfo['versiondata'];
    
    // Fill in defaults.
    if (empty($pagehash['pagename']))
        $pagehash['pagename'] = $default_pagename;
    if (empty($pagehash['author'])) {
        $pagehash['author'] = $user->getId();
    }
    
    foreach ($pagehash as $key => $value) {
        switch($key) {
            case 'pagename':
            case 'version':
            case 'hits':
                $pageinfo[$key] = $value;
                break;
            case 'content':
                $pageinfo[$key] = join("\n", $value);
                break;
            case 'flags':
                if (($value & FLAG_PAGE_LOCKED) != 0)
                    $pagedata['locked'] = 'yes';
                break;
            case 'created':
                $pagedata[$key] = $value;
                break;
            case 'lastmodified':
                $versiondata['mtime'] = $value;
                break;
            case 'author':
            case 'author_id':
            case 'summary':
                $versiondata[$key] = $value;
                break;
        }
    }
    return $pageinfo;
}

function SortByPageVersion ($a, $b) {
    return $a['version'] - $b['version'];
}

function LoadFile (&$request, $filename, $text = false, $mtime = false)
{
    if (!is_string($text)) {
        // Read the file.
        $stat  = stat($filename);
        $mtime = $stat[9];
        $text  = implode("", file($filename));
    }

    if (! get_cfg_var('safe_mode'))
        set_time_limit(30);	// Reset watchdog.

    // FIXME: basename("filewithnoslashes") seems to return garbage sometimes.
    $basename = basename("/dummy/" . $filename);

    if (!$mtime)
        $mtime = time();	// Last resort.

    $default_pagename = rawurldecode($basename);

    if ( ($parts = ParseMimeifiedPages($text)) ) {
        usort($parts, 'SortByPageVersion');
        foreach ($parts as $pageinfo)
            SavePage($request, $pageinfo, sprintf(_("MIME file %s"),
                                                  $filename), $basename);
    }
    else if ( ($pageinfo = ParseSerializedPage($text, $default_pagename,
                                               $request->getUser())) ) {
        SavePage($request, $pageinfo, sprintf(_("Serialized file %s"),
                                              $filename), $basename);
    }
    else {
        $user = $request->getUser();

        // Assume plain text file.
        $pageinfo = array('pagename' => $default_pagename,
                          'pagedata' => array(),
                          'versiondata'
                          => array('author' => $user->getId()),
                          'content'  => preg_replace('/[ \t\r]*\n/', "\n",
                                                     chop($text))
                          );
        SavePage($request, $pageinfo, sprintf(_("plain file %s"), $filename),
                 $basename);
    }
}

function LoadZip (&$request, $zipfile, $files = false, $exclude = false) {
    $zip = new ZipReader($zipfile);
    while (list ($fn, $data, $attrib) = $zip->readFile()) {
        // FIXME: basename("filewithnoslashes") seems to return
        // garbage sometimes.
        $fn = basename("/dummy/" . $fn);
        if ( ($files && !in_array($fn, $files)) || ($exclude && in_array($fn, $exclude)) ) {

            PrintXML(HTML::dt(WikiLink($fn)),
                     HTML::dd(_("Skipping")));
            continue;
        }

        LoadFile($request, $fn, $data, $attrib['mtime']);
    }
}

function LoadDir (&$request, $dirname, $files = false, $exclude = false) {
    $fileset = new LimitedFileSet($dirname, $files, $exclude);

    if (($skiplist = $fileset->getSkippedFiles())) {
        PrintXML(HTML::dt(HTML::strong(_("Skipping"))));
        $list = HTML::ul();
        foreach ($skiplist as $file)
            $list->pushContent(HTML::li(WikiLink($file)));
        PrintXML(HTML::dd($list));
    }

    // Defer HomePage loading until the end. If anything goes wrong
    // the pages can still be loaded again.
    $files = $fileset->getFiles();
    if (in_array(HomePage, $files)) {
        $files = array_diff($files, array(HomePage));
        $files[] = HomePage;
    }
    foreach ($files as $file)
        LoadFile($request, "$dirname/$file");
}

class LimitedFileSet extends FileSet {
    function LimitedFileSet($dirname, $_include, $exclude) {
        $this->_includefiles = $_include;
        $this->_exclude = $exclude;
        $this->_skiplist = array();
        parent::FileSet($dirname);
    }

    function _filenameSelector($fn) {
        $incl = &$this->_include;
        $excl = &$this->_exclude;

        if (($incl && !in_array($fn, $incl)) || ($excl && in_array($fn, $excl))) {
            $this->_skiplist[] = $fn;
            return false;
        } else {
            return true;
        }
    }

    function getSkippedFiles () {
        return $this->_skiplist;
    }
}


function IsZipFile ($filename_or_fd)
{
    // See if it looks like zip file
    if (is_string($filename_or_fd))
    {
        $fd    = fopen($filename_or_fd, "rb");
        $magic = fread($fd, 4);
        fclose($fd);
    }
    else
    {
        $fpos  = ftell($filename_or_fd);
        $magic = fread($filename_or_fd, 4);
        fseek($filename_or_fd, $fpos);
    }

    return $magic == ZIP_LOCHEAD_MAGIC || $magic == ZIP_CENTHEAD_MAGIC;
}


function LoadAny (&$request, $file_or_dir, $files = false, $exclude = false)
{
    // Try urlencoded filename for accented characters.
    if (!file_exists($file_or_dir)) {
        // Make sure there are slashes first to avoid confusing phps
        // with broken dirname or basename functions.
        // FIXME: windows uses \ and :
        if (is_integer(strpos($file_or_dir, "/"))) {
            $file_or_dir = dirname($file_or_dir) ."/".
            urlencode(basename($file_or_dir));
        } else {
            // This is probably just a file.
            $file_or_dir = urlencode($file_or_dir);
        }
    }

    $type = filetype($file_or_dir);
    if ($type == 'link') {
        // For symbolic links, use stat() to determine
        // the type of the underlying file.
        list(,,$mode) = stat($file_or_dir);
        $type = ($mode >> 12) & 017;
        if ($type == 010)
            $type = 'file';
        elseif ($type == 004)
            $type = 'dir';
    }

    if ($type == 'dir') {
        LoadDir($request, $file_or_dir, $files, $exclude);
    }
    else if ($type != 'file' && !preg_match('/^(http|ftp):/', $file_or_dir))
    {
        $request->finish(fmt("Bad file type: %s", $type));
    }
    else if (IsZipFile($file_or_dir)) {
        LoadZip($request, $file_or_dir, $files, $exclude);
    }
    else /* if (!$files || in_array(basename($file_or_dir), $files)) */
    {
        LoadFile($request, $file_or_dir);
    }
}

function LoadFileOrDir (&$request)
{
    $source = $request->getArg('source');
    StartLoadDump($request, sprintf(_("Loading '%s'"), $source));
    echo "<dl>\n";
    LoadAny($request, $source);
    echo "</dl>\n";
    EndLoadDump($request);
}

function SetupWiki (&$request)
{
    global $GenericPages, $LANG;


    //FIXME: This is a hack (err, "interim solution")
    // This is a bogo-bogo-login:  Login without
    // saving login information in session state.
    // This avoids logging in the unsuspecting
    // visitor as "The PhpWiki programming team".
    //
    // This really needs to be cleaned up...
    // (I'm working on it.)
    $real_user = $request->_user;
    $request->_user = new WikiUser(_("The PhpWiki programming team"),
                                   WIKIAUTH_BOGO);

    StartLoadDump($request, _("Loading up virgin wiki"));
    echo "<dl>\n";

    LoadAny($request, FindLocalizedFile(WIKI_PGSRC));
    if ($LANG != "C")
        LoadAny($request, FindFile(DEFAULT_WIKI_PGSRC),
                $GenericPages);

    echo "</dl>\n";
    EndLoadDump($request);
}

function LoadPostFile (&$request)
{
    $upload = $request->getUploadedFile('file');

    if (!$upload)
        $request->finish(_("No uploaded file to upload?")); // FIXME: more concise message


    // Dump http headers.
    StartLoadDump($request, sprintf(_("Uploading %s"), $upload->getName()));
    echo "<dl>\n";

    $fd = $upload->open();
    if (IsZipFile($fd))
        LoadZip($request, $fd, false, array(_("RecentChanges")));
    else
        LoadFile($request, $upload->getName(), $upload->getContents());

    echo "</dl>\n";
    EndLoadDump($request);
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
