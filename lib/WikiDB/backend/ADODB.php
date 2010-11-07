<?php // -*-php-*-
rcs_id('$Id: ADODB.php,v 1.7 2002/02/09 23:07:01 lakka Exp $');

/*This file is part of PhpWiki.

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
//  Based on PearDB.php
//
//  Comments:
//  1)  ADODB's GetRow() is slightly different from that in PEAR.  It does not accept a fetchmode parameter
//      That doesn't matter too much here, since we only ever use FETCHMODE_ASSOC
//  
//  2)  No need for ''s arond strings in sprintf arguments - qstr puts them there automatically
//  
//  3)  ADODB has a version of GetOne, but it is difficult to use it when FETCH_ASSOC is in effect.
//      Instead, use $rs = Execute($query); $value = $rs->fields["$colname"]
//  4)  No error handling yet - could use ADOConnection->raiseErrorFn

//require_once('DB.php');
require_once('lib/WikiDB/backend.php');
//require_once('lib/FileFinder.php');
//require_once('lib/ErrorManager.php');
// Error handling - calls trigger_error.  NB - does not close the connection.  Does it need to?
include_once('lib/WikiDB/adodb/adodb-errorhandler.inc.php');
// include the main adodb file
require_once('lib/WikiDB/adodb/adodb.inc.php');

class WikiDB_backend_ADODB
extends WikiDB_backend
{
    function WikiDB_backend_ADODB ($dbparams) {
        // Find and include PEAR's DB.php.
        //$pearFinder = new PearFileFinder;
        //$pearFinder->includeOnce('DB.php');

        // Install filter to handle bogus error notices from buggy DB.php's.
//        global $ErrorManager;
//        $ErrorManager->pushErrorHandler(new WikiMethodCb($this, '_pear_notice_filter'));
        
        // Open connection to database
//        $this->_dsn = $dbparams['dsn'];
     /*   $dboptions = array('persistent' => true,
                           'debug' => 2);
        $this->_dbh = DB::connect($this->_dsn, $dboptions);
        $dbh = &$this->_dbh;
        if (DB::isError($dbh)) {
            trigger_error(sprintf("Can't connect to database: %s",
                                  $this->_pear_error_message($dbh)),
                          E_USER_ERROR);
        }
        $dbh->setErrorHandling(PEAR_ERROR_CALLBACK,
                               array($this, '_pear_error_callback'));
        $dbh->setFetchMode(ADODB_FETCH_ASSOC);
*/
		$parsed = parseDSN($dbparams['dsn']);
        $this->_dbh = &ADONewConnection($parsed['phptype']); // Probably only MySql works just now
		$conn = $this->_dbh->PConnect($parsed['hostspec'],$parsed['username'], 
					$parsed['password'], $parsed['database']);

//  Error handling not needed here -all dealt with by adodb-errorhandler.inc.php		
/*		if ($conn === false)  {
			trigger_error(sprintf("Can't connect to database: %s",
                                  $this->_pear_error_message($conn)),
                          E_USER_ERROR);}
*/


//  Uncomment the following line to enable debugging output (not very pretty!)		
//		$this->_dbh->debug = true;
		
		$GLOBALS['ADODB_FETCH_MODE'] = ADODB_FETCH_ASSOC;

//  The next line should speed up queries if enabled, but:
//  1)  It only works with PHP >= 4.0.6; and
//  2)  At the moment, I haven't figured out why thw wrong results are returned'
//		$GLOBALS['ADODB_COUNTRECS'] = false;

        $prefix = isset($dbparams['prefix']) ? $dbparams['prefix'] : '';

        $this->_table_names
            = array('page_tbl'     => $prefix . 'page',
                    'version_tbl'  => $prefix . 'version',
                    'link_tbl'     => $prefix . 'link',
                    'recent_tbl'   => $prefix . 'recent',
                    'nonempty_tbl' => $prefix . 'nonempty');

        $this->_expressions
            = array('maxmajor'     => "MAX(CASE WHEN minor_edit=0 THEN version END)",
                    'maxminor'     => "MAX(CASE WHEN minor_edit<>0 THEN version END)",
                    'maxversion'   => "MAX(version)");
        
        $this->_lock_count = 0;
    }
    
    /**
     * Close database connection.
     */
    function close () {
        if (!$this->_dbh)
            return;
        if ($this->_lock_count) {
            trigger_error( "WARNING: database still locked " . '(lock_count = $this->_lock_count)' . "\n<br />",
                          E_USER_WARNING);
        }
//        $this->_dbh->setErrorHandling(PEAR_ERROR_PRINT);	// prevent recursive loops.
        $this->unlock('force');

        $this->_dbh->close();
        $this->_dbh = false;
    }


    /*
     * Test fast wikipage.
     */
    function is_wiki_page($pagename) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        $rs = $dbh->Execute(sprintf("SELECT $page_tbl.id AS id"
                                    . " FROM $nonempty_tbl, $page_tbl"
                                    . " WHERE $nonempty_tbl.id=$page_tbl.id"
                                    . "   AND pagename=%s",
                                    $dbh->qstr($pagename)));
		if (!$rs->EOF)
		{
			$result = $rs->fields["id"];
			$rs->Close();
			return $result;
		}
		else 
		{
			$rs->Close();
			return false;
    	}
	}
        
    function get_all_pagenames() {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
      //return $dbh->getCol("SELECT pagename"
      //                    . " FROM $nonempty_tbl, $page_tbl"
      //                    . " WHERE $nonempty_tbl.id=$page_tbl.id");
	  

//Original code (above) return the column in an indexed array - 0 based
//So, hopefully, does this
	    $result = $dbh->Execute("SELECT pagename"
                            . " FROM $nonempty_tbl, $page_tbl"
                            . " WHERE $nonempty_tbl.id=$page_tbl.id");
	    return $result->GetArray();
    }
            
    /**
     * Read page information from database.
     */
    function get_pagedata($pagename) {
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];

        //trigger_error("GET_PAGEDATA $pagename", E_USER_NOTICE);

        $result = $dbh->GetRow(sprintf("SELECT * FROM $page_tbl WHERE pagename=%s",
                                       $dbh->qstr($pagename)));
        if (!$result)
            return false;
        return $this->_extract_page_data($result);
    }

    function  _extract_page_data(&$query_result) {
        extract($query_result);
        $data = empty($pagedata) ? array() : unserialize($pagedata);
        $data['hits'] = $hits;
        return $data;
    }

    function update_pagedata($pagename, $newdata) {
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];

        // Hits is the only thing we can update in a fast manner.
        if (count($newdata) == 1 && isset($newdata['hits'])) {
            // Note that this will fail silently if the page does not
            // have a record in the page table.  Since it's just the
            // hit count, who cares?
            $dbh->Execute(sprintf("UPDATE $page_tbl SET hits=%d WHERE pagename=%s",
                                $newdata['hits'], $dbh->qstr($pagename)));
            return;
        }

        $this->lock();
        $data = $this->get_pagedata($pagename);
        if (!$data) {
            $data = array();
            $this->_get_pageid($pagename, true); // Creates page record
        }
        
        @$hits = (int)$data['hits'];
        unset($data['hits']);

        foreach ($newdata as $key => $val) {
            if ($key == 'hits')
                $hits = (int)$val;
            else if (empty($val))
                unset($data[$key]);
            else
                $data[$key] = $val;
        }

        $dbh->Execute(sprintf("UPDATE $page_tbl"
                            . " SET hits=%d, pagedata=%s"
                            . " WHERE pagename=%s",
                            $hits,
                            $dbh->qstr(serialize($data)),
                            $dbh->qstr($pagename)));

        $this->unlock();
    }

    function _get_pageid($pagename, $create_if_missing = false) {
        
        $dbh = &$this->_dbh;
        $page_tbl = $this->_table_names['page_tbl'];
        
        $query = sprintf("SELECT id FROM $page_tbl WHERE pagename=%s",
                         $dbh->qstr($pagename));

        if (!$create_if_missing)
            {
			 	$rs = $dbh->Execute($query);
				return $rs->fields['id'];
			}
        $this->lock();
        $rs = $dbh->Execute($query);
		$id = $rs->fields['id'];
	    if (empty($id)) {
			// kludge necessary because an assoc array is returned with a reserved name as the key
            $rs = $dbh->Execute("SELECT MAX(id) AS M FROM $page_tbl");
		    $id = $rs->fields['M'] + 1;
            $dbh->Execute(sprintf("INSERT INTO $page_tbl"
                                . " (id,pagename,hits)"
                                . " VALUES (%d,%s,0)",
                                $id, $dbh->qstr($pagename)));
        }
        $this->unlock();
        return $id;
    }

    function get_latest_version($pagename) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        $rs = $dbh->Execute(sprintf("SELECT latestversion"
                                      . " FROM $page_tbl, $recent_tbl"
                                      . " WHERE $page_tbl.id=$recent_tbl.id"
                                      . "  AND pagename=%s",
                                      $dbh->qstr($pagename)));
		return (int)$rs->fields['latestversion'];
    }

    function get_previous_version($pagename, $version) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
		//Use SELECTLIMIT for maximum portability
        $rs = $dbh->SelectLimit(sprintf("SELECT version"
                                      . " FROM $version_tbl, $page_tbl"
                                      . " WHERE $version_tbl.id=$page_tbl.id"
                                      . "  AND pagename=%s"
                                      . "  AND version < %d"
                                      . " ORDER BY version DESC"
                                      ,$dbh->qstr($pagename),
                                      $version),
									  1);
		return (int)$rs->fields['version'];
    }
    
    /**
     * Get version data.
     *
     * @param $version int Which version to get.
     *
     * @return hash The version data, or false if specified version does not
     *              exist.
     */
    function get_versiondata($pagename, $version, $want_content = false) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
                
        assert(!empty($pagename));
        assert($version > 0);
        
        //trigger_error("GET_REVISION $pagename $version $want_content", E_USER_NOTICE);
        // FIXME: optimization: sometimes don't get page data?

        if ($want_content) {
            $fields = "*";
        }
        else {
            $fields = ("$page_tbl.*,"
                       . "mtime,minor_edit,versiondata,"
                       . "content<>'' AS have_content");
        }
// removed ref to FETCH_MODE in next line
        $result = $dbh->GetRow(sprintf("SELECT $fields"
                                       . " FROM $page_tbl, $version_tbl"
                                       . " WHERE $page_tbl.id=$version_tbl.id"
                                       . "  AND pagename=%s"
                                       . "  AND version=%d",
                                       $dbh->qstr($pagename), $version));

        return $this->_extract_version_data($result);
    }

    function _extract_version_data(&$query_result) {
        if (!$query_result)
            return false;

        extract($query_result);
        $data = empty($versiondata) ? array() : unserialize($versiondata);

        $data['mtime'] = $mtime;
        $data['is_minor_edit'] = !empty($minor_edit);
        
        if (isset($content))
            $data['%content'] = $content;
        elseif ($have_content)
            $data['%content'] = true;
        else
            $data['%content'] = '';

        // FIXME: this is ugly.
        if (isset($pagename)) {
            // Query also includes page data.
            // We might as well send that back too...
            $data['%pagedata'] = $this->_extract_page_data($query_result);
        }

        return $data;
    }


    /**
     * Create a new revision of a page.
     */
    function set_versiondata($pagename, $version, $data) {
        $dbh = &$this->_dbh;
        $version_tbl = $this->_table_names['version_tbl'];
        
        $minor_edit = (int) !empty($data['is_minor_edit']);
        unset($data['is_minor_edit']);
        
        $mtime = (int)$data['mtime'];
        unset($data['mtime']);
        assert(!empty($mtime));

        @$content = (string) $data['%content'];
        unset($data['%content']);

        unset($data['%pagedata']);
        
        $this->lock();
        $id = $this->_get_pageid($pagename, true);

        // FIXME: optimize: mysql can do this with one REPLACE INTO (I think).
        $dbh->Execute(sprintf("DELETE FROM $version_tbl"
                            . " WHERE id=%d AND version=%d",
                            $id, $version));

        $dbh->Execute(sprintf("INSERT INTO $version_tbl"
                            . " (id,version,mtime,minor_edit,content,versiondata)"
                            . " VALUES(%d,%d,%d,%d,%s,%s)",
                            $id, $version, $mtime, $minor_edit,
                            $dbh->qstr($content),
                            $dbh->qstr(serialize($data))));

        $this->_update_recent_table($id);
        $this->_update_nonempty_table($id);
        
        $this->unlock();
    }
    
    /**
     * Delete an old revision of a page.
     */
    function delete_versiondata($pagename, $version) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock();
        if ( ($id = $this->_get_pageid($pagename)) ) {
            $dbh->Execute("DELETE FROM $version_tbl"
                        . " WHERE id=$id AND version=$version");
            $this->_update_recent_table($id);
            // This shouldn't be needed (as long as the latestversion
            // never gets deleted.)  But, let's be safe.
            $this->_update_nonempty_table($id);
        }
        $this->unlock();
    }

    /**
     * Delete page from the database.
     */
    function delete_page($pagename) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        
        $this->lock();
        if ( ($id = $this->_get_pageid($pagename, 'id')) ) {
            $dbh->Execute("DELETE FROM $version_tbl  WHERE id=$id");
            $dbh->Execute("DELETE FROM $recent_tbl   WHERE id=$id");
            $dbh->Execute("DELETE FROM $nonempty_tbl WHERE id=$id");
            $dbh->Execute("DELETE FROM $link_tbl     WHERE linkfrom=$id");
			$rs = $dbh->Execute("SELECT COUNT(*) AS C FROM $link_tbl WHERE linkto=$id");
            $nlinks = $rs->fields['C'];
            if ($nlinks) {
                // We're still in the link table (dangling link) so we can't delete this
                // altogether.
                $dbh->Execute("UPDATE $page_tbl SET hits=0, pagedata='' WHERE id=$id");
            }
            else {
                $dbh->Execute("DELETE FROM $page_tbl WHERE id=$id");
            }
            $this->_update_recent_table();
            $this->_update_nonempty_table();
        }
        $this->unlock();
    }
            

    // The only thing we might be interested in updating which we can
    // do fast in the flags (minor_edit).   I think the default
    // update_versiondata will work fine...
    //function update_versiondata($pagename, $version, $data) {
    //}

    function set_links($pagename, $links) {
        // Update link table.
        // FIXME: optimize: mysql can do this all in one big INSERT.

        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $this->lock();
        $pageid = $this->_get_pageid($pagename, true);

        $dbh->Execute("DELETE FROM $link_tbl WHERE linkfrom=$pageid");

	if ($links) {
            foreach($links as $link) {
                if (isset($linkseen[$link]))
                    continue;
                $linkseen[$link] = true;
                $linkid = $this->_get_pageid($link, true);
                $dbh->Execute("INSERT INTO $link_tbl (linkfrom, linkto)"
                            . " VALUES ($pageid, $linkid)");
            }
	}
        $this->unlock();
    }
    
    /**
     * Find pages which link to or are linked from a page.
     */
    function get_links($pagename, $reversed = true) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        if ($reversed)
            list($have,$want) = array('linkee', 'linker');
        else
            list($have,$want) = array('linker', 'linkee');

        $qpagename = $dbh->qstr($pagename);
// removed ref to FETCH_MODE in next line        
        $result = $dbh->Execute("SELECT $want.*"
                              . " FROM $link_tbl, $page_tbl AS linker, $page_tbl AS linkee"
                              . " WHERE linkfrom=linker.id AND linkto=linkee.id"
                              . "  AND $have.pagename=$qpagename"
                              //. " GROUP BY $want.id"
                              . " ORDER BY $want.pagename");
        
        return new WikiDB_backend_ADODB_iter($this, $result);
    }

    function get_all_pages($include_deleted) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        if ($include_deleted) {
            $result = $dbh->Execute("SELECT * FROM $page_tbl ORDER BY pagename");
        }
        else {
            $result = $dbh->Execute("SELECT $page_tbl.*"
                                  . " FROM $nonempty_tbl, $page_tbl"
                                  . " WHERE $nonempty_tbl.id=$page_tbl.id"
                                  . " ORDER BY pagename");
        }

        return new WikiDB_backend_ADODB_iter($this, $result);
    }
        
    /**
     * Title search.
     */
    function text_search($search = '', $fullsearch = false) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        
        $table = "$nonempty_tbl, $page_tbl";
        $join_clause = "$nonempty_tbl.id=$page_tbl.id";
        $fields = "$page_tbl.*";
        $callback = new WikiMethodCb($this, '_sql_match_clause');
        
        if ($fullsearch) {
            $table .= ", $recent_tbl";
            $join_clause .= " AND $page_tbl.id=$recent_tbl.id";

            $table .= ", $version_tbl";
            $join_clause .= " AND $page_tbl.id=$version_tbl.id AND latestversion=version";

            $fields .= ",$version_tbl.*";
            $callback = new WikiMethodCb($this, '_fullsearch_sql_match_clause');
        }
        
        $search_clause = $search->makeSqlClause($callback);
        
        $result = $dbh->Execute("SELECT $fields FROM $table"
                              . " WHERE $join_clause"
                              . "  AND ($search_clause)"
                              . " ORDER BY pagename");
        
        return new WikiDB_backend_ADODB_iter($this, $result);
    }

    function _sql_match_clause($word) {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);  //not sure if we need this.  ADODB may do it for us
        // (we need it for at least % and _ --- they're the wildcard characters
        //  for the LIKE operator, and we need to quote them if we're searching
        //  for literal '%'s or '_'s.  --- I'm not sure about \, but it seems to
        //  work as is.
        $word = $this->_dbh->qstr("%$word%");
        return "LOWER(pagename) LIKE $word";
    }

    function _fullsearch_sql_match_clause($word) {
        $word = preg_replace('/(?=[%_\\\\])/', "\\", $word);  //not sure if we need this
        // (see above)
        $word = $this->_dbh->qstr("%$word%");
        return "LOWER(pagename) LIKE $word OR content LIKE $word";
    }

    /**
     * Find highest or lowest hit counts.
     */
    function most_popular($limit) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        $order = "DESC";
		if ($limit < 0){ 
		    $order = "ASC"; 
			$limit = -$limit;
			}
		$limit = $limit ? $limit : -1;
        $result = $dbh->SelectLimit("SELECT $page_tbl.*"
                              . " FROM $nonempty_tbl, $page_tbl"
                              . " WHERE $nonempty_tbl.id=$page_tbl.id"
                              . " ORDER BY hits $order"
                              , $limit);

        return new WikiDB_backend_ADODB_iter($this, $result);
    }

    /**
     * Find recent changes.
     */
    function most_recent($params) {
        $limit = 0;
        $since = 0;
        $include_minor_revisions = false;
        $exclude_major_revisions = false;
        $include_all_revisions = false;
		extract($params);

        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $pick = array();
		if ($since)
		    $pick[] = "mtime >= $since";
		
        
        if ($include_all_revisions) {
            // Include all revisions of each page.
            $table = "$page_tbl, $version_tbl";
            $join_clause = "$page_tbl.id=$version_tbl.id";

            if ($exclude_major_revisions) {
		// Include only minor revisions
                $pick[] = "minor_edit <> 0";
            }
            elseif (!$include_minor_revisions) {
		// Include only major revisions
                $pick[] = "minor_edit = 0";
            }
        }
        else {
            $table = "$page_tbl, $recent_tbl";
            $join_clause = "$page_tbl.id=$recent_tbl.id";
            $table .= ", $version_tbl";
            $join_clause .= " AND $version_tbl.id=$page_tbl.id";
                
            if ($exclude_major_revisions) {
                // Include only most recent minor revision
                $pick[] = 'version=latestminor';
            }
            elseif (!$include_minor_revisions) {
                // Include only most recent major revision
                $pick[] = 'version=latestmajor';
            }
            else {
                // Include only the latest revision (whether major or minor).
                $pick[] ='version=latestversion';
            }
        }
        $order = "DESC";
		if($limit < 0){
    		$order = "ASC";
	    	$limit = -$limit;
		}
        $limit = $limit ? $limit : -1;
        $where_clause = $join_clause;
        if ($pick)
            $where_clause .= " AND " . join(" AND ", $pick);

        // FIXME: use SQL_BUFFER_RESULT for mysql?
		//Use SELECTLIMIT for portability
        $result = $dbh->SelectLimit("SELECT $page_tbl.*,$version_tbl.*"
                              . " FROM $table"
                              . " WHERE $where_clause"
                              . " ORDER BY mtime $order",
                              $limit);

        return new WikiDB_backend_ADODB_iter($this, $result);
    }

    function _update_recent_table($pageid = false) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);
        extract($this->_expressions);

        $pageid = (int)$pageid;

        $this->lock();

        $dbh->Execute("DELETE FROM $recent_tbl"
                    . ( $pageid ? " WHERE id=$pageid" : ""));
        
        $dbh->Execute( "INSERT INTO $recent_tbl"
                     . " (id, latestversion, latestmajor, latestminor)"
                     . " SELECT id, $maxversion, $maxmajor, $maxminor"
                     . " FROM $version_tbl"
                     . ( $pageid ? " WHERE id=$pageid" : "")
                     . " GROUP BY id" );
        $this->unlock();
    }

    function _update_nonempty_table($pageid = false) {
        $dbh = &$this->_dbh;
        extract($this->_table_names);

        $pageid = (int)$pageid;

        $this->lock();

        $dbh->Execute("DELETE FROM $nonempty_tbl"
                    . ( $pageid ? " WHERE id=$pageid" : ""));

        $dbh->Execute("INSERT INTO $nonempty_tbl (id)"
                    . " SELECT $recent_tbl.id"
                    . " FROM $recent_tbl, $version_tbl"
                    . " WHERE $recent_tbl.id=$version_tbl.id"
                    . "       AND version=latestversion"
                    . "  AND content<>''"
                    . ( $pageid ? " AND $recent_tbl.id=$pageid" : ""));

        $this->unlock();
    }


    /**
     * Grab a write lock on the tables in the SQL database.
     *
     * Calls can be nested.  The tables won't be unlocked until
     * _unlock_database() is called as many times as _lock_database().
     *
     * @access protected
     */
    function lock($write_lock = true) {
        if ($this->_lock_count++ == 0)
            $this->_lock_tables($write_lock);
    }

    /**
     * Actually lock the required tables.
     */
    function _lock_tables($write_lock) {
        trigger_error("virtual", E_USER_ERROR);
    }
    
    /**
     * Release a write lock on the tables in the SQL database.
     *
     * @access protected
     *
     * @param $force boolean Unlock even if not every call to lock() has been matched
     * by a call to unlock().
     *
     * @see _lock_database
     */
    function unlock($force = false) {
        if ($this->_lock_count == 0)
            return;
        if (--$this->_lock_count <= 0 || $force) {
            $this->_unlock_tables();
            $this->_lock_count = 0;
        }
    }

    /**
     * Actually unlock the required tables.
     */
    function _unlock_tables($write_lock) {
        trigger_error("virtual", E_USER_ERROR);
    }
    
    /**
     * Callback for PEAR (DB) errors.
     *
     * @access protected
     *
     * @param A PEAR_error object.
     */
/*  function _pear_error_callback($error) {
        if ($this->_is_false_error($error))
            return;
        
        $this->_dbh->setErrorHandling(PEAR_ERROR_PRINT);	// prevent recursive loops.
        $this->close();
        trigger_error($this->_pear_error_message($error), E_USER_ERROR);
    }
*/
    /**
     * Detect false errors messages from PEAR DB.
     *
     * The version of PEAR DB which ships with PHP 4.0.6 has a bug in that
     * it doesn't recognize "LOCK" and "UNLOCK" as SQL commands which don't
     * return any data.  (So when a "LOCK" command doesn't return any data,
     * DB reports it as an error, when in fact, it's not.)
     *
     * @access private
     * @return bool True iff error is not really an error.
     */
/*    function _is_false_error($error) {
        if ($error->getCode() != DB_ERROR)
            return false;

        $query = $this->_dbh->last_query;

        if (! preg_match('/^\s*"?(INSERT|UPDATE|DELETE|REPLACE|CREATE'
                         . '|DROP|ALTER|GRANT|REVOKE|LOCK|UNLOCK)\s/', $query)) {
            // Last query was not of the sort which doesn't return any data.
            //" <--kludge for brain-dead syntax coloring
            return false;
        }
        
        if (! in_array('ismanip', get_class_methods('DB'))) {
            // Pear shipped with PHP 4.0.4pl1 (and before, presumably)
            // does not have the DB::isManip method.
            return true;
        }
        
        if (DB::isManip($query)) {
            // If Pear thinks it's an isManip then it wouldn't have thrown
            // the error we're testing for....
            return false;
        }

        return true;
    }
*/
/*    function _pear_error_message($error) {
        $class = get_class($this);
        $message = "$class: fatal database error\n"
             . "\t" . $error->getMessage() . "\n"
             . "\t(" . $error->getDebugInfo() . ")\n";

        // Prevent password from being exposed during a connection error
        $safe_dsn = preg_replace('| ( :// .*? ) : .* (?=@) |xs',
                                 '\\1:XXXXXXXX', $this->_dsn);
        return str_replace($this->_dsn, $safe_dsn, $message);
    }
*/
    /**
     * Filter PHP errors notices from PEAR DB code.
     *
     * The PEAR DB code which ships with PHP 4.0.6 produces spurious
     * errors and notices.  This is an error callback (for use with
     * ErrorManager which will filter out those spurious messages.)
     * @see _is_false_error, ErrorManager
     * @access private
     */
/*    function _pear_notice_filter($err) {
        return ( $err->isNotice()
                 && preg_match('|DB[/\\\\]common.php$|', $err->errfile)
                 && $err->errline == 126
                 && preg_match('/Undefined offset: +0\b/', $err->errstr) );
    }
*/
};

class WikiDB_backend_ADODB_iter
extends WikiDB_backend_iterator
{
    function WikiDB_backend_ADODB_iter(&$backend, &$query_result) {
// ADODB equivalent of this?  May not matter, since we should never get here
/*        if (DB::isError($query_result)) {
            // This shouldn't happen, I thought.
            $backend->_pear_error_callback($query_result);
        }
*/        
        $this->_backend = &$backend;
        $this->_result = $query_result;
    }
    
    function next() {
        $result = &$this->_result;
        if (!$result || $result->EOF) {
            $this->free();
            return false;
        }

//      $record = $this->_result->fetchRow(DB_FETCHMODE_ASSOC);
        $record = $result->fields;
        $result->MoveNext();
        
        $backend = &$this->_backend;

        $pagedata = $backend->_extract_page_data($record);
        $rec = array('pagename' => $record['pagename'],
                     'pagedata' => $pagedata);

        if (!empty($record['version'])) {
            $rec['versiondata'] = $backend->_extract_version_data($record);
            $rec['version'] = $record['version'];
        }
        
        return $rec;
    }

    function free () {
        if ($this->_result) {
//          $this->_result->free();
            $this->_result->Close();
            $this->_result = false;
        }
    }
}

// Following function taken from adodb-pear.inc.php.  Eventually, change index.php to provide the relevant information
// directly?
    /**
     * Parse a data source name
     *
     * @param $dsn string Data Source Name to be parsed
     *
     * @return array an associative array with the following keys:
     *
     *  phptype: Database backend used in PHP (mysql, odbc etc.)
     *  dbsyntax: Database used with regards to SQL syntax etc.
     *  protocol: Communication protocol to use (tcp, unix etc.)
     *  hostspec: Host specification (hostname[:port])
     *  database: Database to use on the DBMS server
     *  username: User name for login
     *  password: Password for login
     *
     * The format of the supplied DSN is in its fullest form:
     *
     *  phptype(dbsyntax)://username:password@protocol+hostspec/database
     *
     * Most variations are allowed:
     *
     *  phptype://username:password@protocol+hostspec:110//usr/db_file.db
     *  phptype://username:password@hostspec/database_name
     *  phptype://username:password@hostspec
     *  phptype://username@hostspec
     *  phptype://hostspec/database
     *  phptype://hostspec
     *  phptype(dbsyntax)
     *  phptype
     *
     * @author Tomas V.V.Cox <cox@idecnet.com>
     */
    function parseDSN($dsn)
    {
        if (is_array($dsn)) {
            return $dsn;
        }

        $parsed = array(
            'phptype'  => false,
            'dbsyntax' => false,
            'protocol' => false,
            'hostspec' => false,
            'database' => false,
            'username' => false,
            'password' => false
        );

        // Find phptype and dbsyntax
        if (($pos = strpos($dsn, '://')) !== false) {
            $str = substr($dsn, 0, $pos);
            $dsn = substr($dsn, $pos + 3);
        } else {
            $str = $dsn;
            $dsn = NULL;
        }

        // Get phptype and dbsyntax
        // $str => phptype(dbsyntax)
        if (preg_match('|^(.+?)\((.*?)\)$|', $str, $arr)) {
            $parsed['phptype'] = $arr[1];
            $parsed['dbsyntax'] = (empty($arr[2])) ? $arr[1] : $arr[2];
        } else {
            $parsed['phptype'] = $str;
            $parsed['dbsyntax'] = $str;
        }

        if (empty($dsn)) {
            return $parsed;
        }

        // Get (if found): username and password
        // $dsn => username:password@protocol+hostspec/database
        if (($at = strpos($dsn,'@')) !== false) {
            $str = substr($dsn, 0, $at);
            $dsn = substr($dsn, $at + 1);
            if (($pos = strpos($str, ':')) !== false) {
                $parsed['username'] = urldecode(substr($str, 0, $pos));
                $parsed['password'] = urldecode(substr($str, $pos + 1));
            } else {
                $parsed['username'] = urldecode($str);
            }
        }

        // Find protocol and hostspec
        // $dsn => protocol+hostspec/database
        if (($pos=strpos($dsn, '/')) !== false) {
            $str = substr($dsn, 0, $pos);
            $dsn = substr($dsn, $pos + 1);
        } else {
            $str = $dsn;
            $dsn = NULL;
        }

        // Get protocol + hostspec
        // $str => protocol+hostspec
        if (($pos=strpos($str, '+')) !== false) {
            $parsed['protocol'] = substr($str, 0, $pos);
            $parsed['hostspec'] = urldecode(substr($str, $pos + 1));
        } else {
            $parsed['hostspec'] = urldecode($str);
        }

        // Get dabase if any
        // $dsn => database
        if (!empty($dsn)) {
            $parsed['database'] = $dsn;
        }

        return $parsed;
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
