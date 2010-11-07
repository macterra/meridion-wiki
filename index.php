<?php // -*-php-*-

/*
Copyright 1999, 2000, 2001, 2002 $ThePhpWikiProgrammingTeam = array(
"Steve Wainstead", "Clifford A. Adams", "Lawrence Akka", 
"Scott R. Anderson", "Jon �slund", "Neil Brown", "Jeff Dairiki",
"St�phane Gourichon", "Jan Hidders", "Arno Hollosi", "John Jorgensen",
"Antti Kaihola", "Jeremie Kass", "Carsten Klapp", "Marco Milanesi",
"Grant Morgan", "Jan Nieuwenhuizen", "Aredridel Niothke", 
"Pablo Roca Rozas", "Sandino Araico S�nchez", "Joel Uckelman", 
"Reini Urban", "Tim Voght");

This file is part of PhpWiki.

PhpWiki is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

PhpWiki is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with PhpWiki; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


include_once ("../bbs/Settings.php");
include_once ("../bbs/auth.php");

define('SCRIPT_NAME', '/wiki');
//define('DATA_PATH', 'http://virus.lucifer.com/wikidata');
define('DATA_PATH', 'http://www.churchofvirus.org/wikidata');
//define('DATA_PATH', 'http://localhost/wikidata');


/////////////////////////////////////////////////////////////////////
/*
  This is the starting file for PhpWiki. All this file does is set
  configuration options, and at the end of the file it includes() the
  file lib/main.php, where the real action begins.

  This file is divided into six parts: Parts Zero, One, Two, Three,
  Four and Five. Each one has different configuration settings you can
  change; in all cases the default should work on your system,
  however, we recommend you tailor things to your particular setting.
*/

/////////////////////////////////////////////////////////////////////
// Part Zero: If PHP needs help in finding where you installed the
//   rest of the PhpWiki code, you can set the include_path here.

// NOTE: phpwiki uses the PEAR library of php code for SQL database
// access. Your PHP is probably already configured to set
// include_path so that PHP can find the pear code. If not (or if you
// change include_path here) make sure you include the path to the
// PEAR code in include_path. (To find the PEAR code on your system,
// search for a file named 'PEAR.php'. Some common locations are:
//
//   Unixish systems:
//     /usr/share/php
//     /usr/local/share/php
//   Mac OS X:
//     /System/Library/PHP
//
// The above examples are already included by PhpWiki. You shouldn't
// have to change this unless you see a WikiFatalError:
//
//     lib/FileFinder.php:82: Fatal[256]: DB.php: file not found
//
// Define the include path for this wiki: pear plus the phpwiki path
// $include_path = '.:/Apache/php/pear:/prog/php/phpwiki';
//
// // Windows needs ';' as path delimiter. cygwin, mac and unix ':'
// if (substr(PHP_OS,0,3) == 'WIN') {
//     $include_path = implode(';',explode(':',$include_path));
// } elseif (substr(PHP_OS,0,6) == 'CYGWIN') {
//     $include_path = '.:/usr/local/lib/php/pear:/usr/src/php/phpwiki';
// } else {
//     ;
// }
//ini_set('include_path', $include_path);

/////////////////////////////////////////////////////////////////////
// Part Null: Don't touch this!

define ('PHPWIKI_VERSION', '1.3.2-jeffs-hacks');
require "lib/prepend.php";
rcs_id('$Id: index.php,v 1.80 2002/02/23 00:16:20 carstenklapp Exp $');

/////////////////////////////////////////////////////////////////////
//
// Part One:
// Authentication and security settings:
// 
/////////////////////////////////////////////////////////////////////

// The name of your wiki.
// This is used to generate a keywords meta tag in the HTML templates,
// in bookmark titles for any bookmarks made to pages in your wiki,
// and during RSS generation for the <title> of the RSS channel.
define('WIKI_NAME', 'VirusWiki');

// If set, we will perform reverse dns lookups to try to convert the
// users IP number to a host name, even if the http server didn't do
// it for us.
define('ENABLE_REVERSE_DNS', true);

// Username and password of administrator.
// Set these to your preferences. For heaven's sake
// pick a good password!
define('ADMIN_USER', "DavidLucifer");
define('ADMIN_PASSWD', "n0m1c");

// If true, only the admin user can make zip dumps, else zip dumps
// require no authentication.
define('ZIPDUMP_AUTH', false);


// If you define this to true, (MIME-type) page-dumps (either zip dumps,
// or "dumps to directory" will be encoded using the quoted-printable
// encoding.  If you're actually thinking of mailing the raw page dumps,
// then this might be useful, since (among other things,) it ensures
// that all lines in the message body are under 80 characters in length.
//
// Also, setting this will cause a few additional mail headers
// to be generated, so that the resulting dumps are valid
// RFC 2822 e-mail messages.
//
// Probably, you can just leave this set to false, in which case you get
// raw ('binary' content-encoding) page dumps.
define('STRICT_MAILABLE_PAGEDUMPS', false);

// The maximum file upload size.
define('MAX_UPLOAD_SIZE', 16 * 1024 * 1024);

// If the last edit is older than MINOR_EDIT_TIMEOUT seconds, the
// default state for the "minor edit" checkbox on the edit page form
// will be off.
define("MINOR_EDIT_TIMEOUT", 7 * 24 * 3600);

// Actions listed in this array will not be allowed.
//$DisabledActions = array('dumpserial', 'loadfile');

// PhpWiki can generate an access_log (in "NCSA combined log" format)
// for you. If you want one, define this to the name of the log file.
//define('ACCESS_LOG', '/var/log/httpd/wiki_access_log');


// If ALLOW_BOGO_LOGIN is true, users are allowed to login (with
// any/no password) using any userid which: 1) is not the ADMIN_USER,
// 2) is a valid WikiWord (matches $WikiNameRegexp.)
define('ALLOW_BOGO_LOGIN', false);

// If set, then if an anonymous user attempts to edit a page he will
// be required to sign in.  (If ALLOW_BOGO_LOGIN is true, of course,
// no password is required, but the user must still sign in under
// some sort of BogoUserId.)
define('REQUIRE_SIGNIN_BEFORE_EDIT', true);

// The login code now uses PHP's session support. Usually, the default
// configuration of PHP is to store the session state information in
// /tmp. That probably will work fine, but fails e.g. on clustered
// servers where each server has their own distinct /tmp (this is the
// case on SourceForge's project web server.) You can specify an
// alternate directory in which to store state information like so
// (whatever user your httpd runs as must have read/write permission
// in this directory):

//ini_set('session.save_path', 'some_other_directory');

// If your php was compiled with --enable-trans-sid it tries to
// add a PHPSESSID query argument to all URL strings when cookie
// support isn't detected in the client browser.  For reasons
// which aren't entirely clear (PHP bug) this screws up the URLs
// generated by PhpWiki.  Therefore, transparent session ids
// should be disabled.  This next line does that.
//
// (At the present time, you will not be able to log-in to PhpWiki,
// or set any user preferences, unless your browser supports cookies.)
@ini_set('session.use_trans_sid', 0);

/////////////////////////////////////////////////////////////////////
//
// Part Two:
// Database Selection
//
/////////////////////////////////////////////////////////////////////

//
// This array holds the parameters which select the database to use.
//
// Not all of these parameters are used by any particular DB backend.
//
$DBParams = array(
   // Select the database type:
   'dbtype' => 'SQL',
   //'dbtype'   => 'dba',
   
   // For SQL based backends, specify the database as a DSN
   // The most general form of a DSN looks like:
   //
   //   phptype(dbsyntax)://username:password@protocol+hostspec/database
   //
   // For a MySQL database, the following should work:
   //
   //   mysql://user:password@host/databasename
   //
   // FIXME: My version Pear::DB seems to be broken enough that there
   //        is no way to connect to a mysql server over a socket right now.
   //'dsn' => 'mysql://guest@:/var/lib/mysql/mysql.sock/test',
   //'dsn' => 'mysql://guest@localhost/test',
   //'dsn' => 'pgsql://localhost/test',
   'dsn' => 'mysql://root@localhost/wiki_virus',
   
   // Used by all DB types:

   // prefix for filenames or table names
   /* 
    * currently you MUST EDIT THE SQL file too (in the schemas/
    * directory because we aren't doing on the fly sql generation
    * during the installation.
   */
   //'prefix' => 'phpwiki_',
   
   // Used by 'dba'
   'directory'     => "/tmp",
   'dba_handler'   => 'gdbm',   // Either of 'gdbm' or 'db2' work great for me.
   //'dba_handler' => 'db2',
   //'dba_handler' => 'db3',    // doesn't work at all for me....
   'timeout'   => 20,
   //'timeout' => 5
);

/////////////////////////////////////////////////////////////////////
//
// The next section controls how many old revisions of each page are
// kept in the database.
//
// There are two basic classes of revisions: major and minor. Which
// class a revision belongs in is determined by whether the author
// checked the "this is a minor revision" checkbox when they saved the
// page.
// 
// There is, additionally, a third class of revisions: author
// revisions. The most recent non-mergable revision from each distinct
// author is and author revision.
//
// The expiry parameters for each of those three classes of revisions
// can be adjusted seperately. For each class there are five
// parameters (usually, only two or three of the five are actually
// set) which control how long those revisions are kept in the
// database.
//
//   max_keep: If set, this specifies an absolute maximum for the
//             number of archived revisions of that class. This is
//             meant to be used as a safety cap when a non-zero
//             min_age is specified. It should be set relatively high,
//             and it's purpose is to prevent malicious or accidental
//             database overflow due to someone causing an
//             unreasonable number of edits in a short period of time.
//
//   min_age:  Revisions younger than this (based upon the supplanted
//             date) will be kept unless max_keep is exceeded. The age
//             should be specified in days. It should be a
//             non-negative, real number,
//
//   min_keep: At least this many revisions will be kept.
//
//   keep:     No more than this many revisions will be kept.
//
//   max_age:  No revision older than this age will be kept.
//
// Supplanted date: Revisions are timestamped at the instant that they
// cease being the current revision. Revision age is computed using
// this timestamp, not the edit time of the page.
//
// Merging: When a minor revision is deleted, if the preceding
// revision is by the same author, the minor revision is merged with
// the preceding revision before it is deleted. Essentially: this
// replaces the content (and supplanted timestamp) of the previous
// revision with the content after the merged minor edit, the rest of
// the page metadata for the preceding version (summary, mtime, ...)
// is not changed.
//
// Keep up to 8 major edits, but keep them no longer than a month.
$ExpireParams['major'] = array('max_age' => 10000,
                               'keep'    => 10000);
// Keep up to 4 minor edits, but keep them no longer than a week.
$ExpireParams['minor'] = array('max_age' => 7,
                               'keep'    => 4);
// Keep the latest contributions of the last 8 authors up to a year.
// Additionally, (in the case of a particularly active page) try to
// keep the latest contributions of all authors in the last week (even
// if there are more than eight of them,) but in no case keep more
// than twenty unique author revisions.
$ExpireParams['author'] = array('max_age'  => 365,
                                'keep'     => 8,
                                'min_age'  => 7,
                                'max_keep' => 20);

/////////////////////////////////////////////////////////////////////
// 
// Part Three:
// Page appearance and layout
//
/////////////////////////////////////////////////////////////////////

/* THEME
 *
 * Most of the page appearance is controlled by files in the theme
 * subdirectory.
 *
 * There are a number of pre-defined themes shipped with PhpWiki.
 * Or you may create your own (e.g. by copying and then modifying one of
 * stock themes.)
 *
 * Pick one.
 */
//define('THEME', 'default');
//define('THEME', 'Hawaiian');
//define('THEME', 'MacOSX');
//define('THEME', 'Portland');
//define('THEME', 'Sidebar');
//define('THEME', 'SpaceWiki');
define('THEME', 'Virus');

// Select a valid charset name to be inserted into the xml/html pages,
// and to reference links to the stylesheets (css). For more info see:
// <http://www.iana.org/assignments/character-sets>. Note that PhpWiki
// has been extensively tested only with the latin1 (iso-8859-1)
// character set.
//
// If you change the default from iso-8859-1 PhpWiki may not work
// properly and it will require code modifications. However, character
// sets similar to iso-8859-1 may work with little or no modification
// depending on your setup. The database must also support the same
// charset, and of course the same is true for the web browser. (Some
// work is in progress hopefully to allow more flexibility in this
// area in the future).
define("CHARSET", "iso-8859-1");

// Select your language/locale - default language is "C" for English.
// Other languages available:
// English "C"  (English    - HomePage)
// Dutch   "nl" (Nederlands - ThuisPagina)
// Spanish "es" (Espa�ol    - P�ginaPrincipal)
// French  "fr" (Fran�ais   - Accueil)
// German  "de" (Deutsch    - StartSeite)
// Swedish "sv" (Svenska    - Framsida)
// Italian "it" (Italiano   - PaginaPrincipale)
//
// If you set $LANG to the empty string, your systems default language
// (as determined by the applicable environment variables) will be
// used.
//
// Note that on some systems, apprently using these short forms for
// the locale won't work. On my home system 'LANG=de' won't result in
// german pages. Somehow the system must recognize the locale as a
// valid locale before gettext() will work, i.e., use 'de_DE',
// 'nl_NL'.
$LANG='C';
//$LANG='nl_NL';

// Setting the LANG environment variable (accomplished above) may or
// may not be sufficient to cause PhpWiki to produce dates in your
// native language. (It depends on the configuration of the operating
// system on your http server.)  The problem is that, e.g. 'de' is
// often not a valid locale.
//
// A standard locale name is typically of  the  form
// language[_territory][.codeset][@modifier],  where  language is
// an ISO 639 language code, territory is an ISO 3166 country code,
// and codeset  is  a  character  set or encoding identifier like
// ISO-8859-1 or UTF-8.
//
// You can tailor the locale used for time and date formatting by
// setting the LC_TIME environment variable. You'll have to experiment
// to find the correct setting:
//putenv('LC_TIME=de_DE');


/* WIKI_PGSRC -- specifies the source for the initial page contents of
 * the Wiki. The setting of WIKI_PGSRC only has effect when the wiki is
 * accessed for the first time (or after clearing the database.)
 * WIKI_PGSRC can either name a directory or a zip file. In either case
 * WIKI_PGSRC is scanned for files -- one file per page.
 */
define('WIKI_PGSRC', "pgsrc"); // Default (old) behavior.
//define('WIKI_PGSRC', 'wiki.zip'); // New style.
//define('WIKI_PGSRC', '../../../Logs/Hamwiki/hamwiki-20010830.zip'); // New style.

/*
 * DEFAULT_WIKI_PGSRC is only used when the language is *not* the
 * default (English) and when reading from a directory: in that case
 * some English pages are inserted into the wiki as well.
 * DEFAULT_WIKI_PGSRC defines where the English pages reside.
 */
// FIXME: is this really needed?  Can't we just copy these pages into
// the localized pgsrc?
define('DEFAULT_WIKI_PGSRC', "pgsrc");
// These are the pages which will get loaded from DEFAULT_WIKI_PGSRC.	
$GenericPages = array("ReleaseNotes", "SteveWainstead", "TestPage");

/////////////////////////////////////////////////////////////////////
//
// Part four:
// Mark-up options.
// 
/////////////////////////////////////////////////////////////////////

// allowed protocols for links - be careful not to allow "javascript:"
// URL of these types will be automatically linked.
// within a named link [name|uri] one more protocol is defined: phpwiki
$AllowedProtocols = "http|https|mailto|ftp|news|nntp|ssh|gopher";

// URLs ending with the following extension should be inlined as images
$InlineImages = "png|jpg|gif";

// Perl regexp for WikiNames ("bumpy words")
// (?<!..) & (?!...) used instead of '\b' because \b matches '_' as well
$WikiNameRegexp = "(?<![[:alnum:]])(?:[[:upper:]][[:lower:]]+){2,}(?![[:alnum:]])";

// InterWiki linking -- wiki-style links to other wikis on the web
//
// The map will be taken from a page name InterWikiMap.
// If that page is not found (or is not locked), or map
// data can not be found in it, then the file specified
// by INTERWIKI_MAP_FILE (if any) will be used.
define('INTERWIKI_MAP_FILE', "lib/interwiki.map");

/////////////////////////////////////////////////////////////////////
//
// Part five:
// URL options -- you can probably skip this section.
//
/////////////////////////////////////////////////////////////////////
/******************************************************************
 *
 * The following section contains settings which you can use to tailor
 * the URLs which PhpWiki generates.
 *
 * Any of these parameters which are left undefined will be deduced
 * automatically. You need only set them explicitly if the
 * auto-detected values prove to be incorrect.
 *
 * In most cases the auto-detected values should work fine, so
 * hopefully you don't need to mess with this section.
 *
 ******************************************************************/

/*
 * Canonical name and httpd port of the server on which this PhpWiki
 * resides.
 */
define('SERVER_NAME', 'www.churchofvirus.org');
//define('SERVER_NAME', 'localhost');
//define('SERVER_PORT', 80);

/*
 * Relative URL (from the server root) of the PhpWiki
 * script.
 */
//define('SCRIPT_NAME', '/some/where/index.php');

/*
 * URL of the PhpWiki install directory.  (You only need to set this
 * if you've moved index.php out of the install directory.)  This can
 * be either a relative URL (from the directory where the top-level
 * PhpWiki script is) or an absolute one.
 */
//define('DATA_PATH', '/some/where/phpwiki');

/*
 * Path to the PhpWiki install directory.  This is the local
 * filesystem counterpart to DATA_PATH.  (If you have to set
 * DATA_PATH, your probably have to set this as well.)  This can be
 * either an absolute path, or a relative path interpreted from the
 * directory where the top-level PhpWiki script (normally index.php)
 * resides.
 */
//define('PHPWIKI_DIR', '/htdocs/some/where/phpwiki');


/*
 * Define to 'true' to use PATH_INFO to pass the pagename's.
 * e.g. http://www.some.where/index.php/HomePage instead
 * of http://www.some.where/index.php?pagename=HomePage
 * FIXME: more docs (maybe in README).
 */
//define('USE_PATH_INFO', false);
define('USE_PATH_INFO', true);

/*
 * VIRTUAL_PATH is the canonical URL path under which your your wiki
 * appears. Normally this is the same as dirname(SCRIPT_NAME), however
 * using, e.g. apaches mod_actions (or mod_rewrite), you can make it
 * something different.
 *
 * If you do this, you should set VIRTUAL_PATH here.
 *
 * E.g. your phpwiki might be installed at at /scripts/phpwiki/index.php,
 * but  * you've made it accessible through eg. /wiki/HomePage.
 *
 * One way to do this is to create a directory named 'wiki' in your
 * server root. The directory contains only one file: an .htaccess
 * file which reads something like:
 *
 *    Action x-phpwiki-page /scripts/phpwiki/index.php
 *    SetHandler x-phpwiki-page
 *    DirectoryIndex /scripts/phpwiki/index.php
 *
 * In that case you should set VIRTUAL_PATH to '/wiki'.
 *
 * (VIRTUAL_PATH is only used if USE_PATH_INFO is true.)
 */
//define('VIRTUAL_PATH', '/SomeWiki');


////////////////////////////////////////////////////////////////
// Okay... fire up the code:
////////////////////////////////////////////////////////////////

include "lib/main.php";

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
