<?php rcs_id('$Id: Request.php,v 1.13 2002/02/08 08:01:32 lakka Exp $');

// FIXME: write log entry.

class Request {
        
    function Request() {
        $this->_fix_magic_quotes_gpc();
        $this->_fix_multipart_form_data();
        
        switch($this->get('REQUEST_METHOD')) {
        case 'GET':
        case 'HEAD':
            $this->args = &$GLOBALS['HTTP_GET_VARS'];
            break;
        case 'POST':
            $this->args = &$GLOBALS['HTTP_POST_VARS'];
            break;
        default:
            $this->args = array();
            break;
        }
        
        $this->session = new Request_SessionVars;
        $this->cookies = new Request_CookieVars;
        
        if (ACCESS_LOG)
            $this->_log_entry = & new Request_AccessLogEntry($this,
                                                             ACCESS_LOG);
        
        $TheRequest = $this;
    }

    function get($key) {
        $vars = &$GLOBALS['HTTP_SERVER_VARS'];

        if (isset($vars[$key]))
            return $vars[$key];

        switch ($key) {
        case 'REMOTE_HOST':
            $addr = $vars['REMOTE_ADDR'];
            if (defined('ENABLE_REVERSE_DNS') && ENABLE_REVERSE_DNS)
                return $vars[$key] = gethostbyaddr($addr);
            else
                return $addr;
        default:
            return false;
        }
    }

    function getArg($key) {
        if (isset($this->args[$key]))
            return $this->args[$key];
        return false;
    }

    function getArgs () {
        return $this->args;
    }
    
    function setArg($key, $val) {
        if ($val === false)
            unset($this->args[$key]);
        else
            $this->args[$key] = $val;
    }
    

    function getURLtoSelf($args = false) {
        $get_args = $this->args;
        if ($args)
            $get_args = array_merge($get_args, $args);

        $pagename = $get_args['pagename'];
        unset ($get_args['pagename']);
        if ($get_args['action'] == 'browse')
            unset($get_args['action']);

        return WikiURL($pagename, $get_args);
    }
    

    function isPost () {
        return $this->get("REQUEST_METHOD") == "POST";
    }
    
    function redirect($url) {
        header("Location: $url");
        if (isset($this->_log_entry))
            $this->_log_entry->setStatus(302);
    }

    function setStatus($status) {
        if (preg_match('|^HTTP/.*?\s(\d+)|i', $status, $m)) {
            header($status);
            $status = $m[1];
        }
        else {
            $status = (integer) $status;
            $reasons = array('200' => 'OK',
                             '302' => 'Found',
                             '400' => 'Bad Request',
                             '401' => 'Unauthorized',
                             '403' => 'Forbidden',
                             '404' => 'Not Found');
            header(sprintf("HTTP/1.0 %d %s", $status, $reason[$status]));
        }

        if (isset($this->_log_entry))
            $this->_log_entry->setStatus($status);
    }

    function compress_output() {
        if (function_exists('ob_gzhandler')) {
            ob_start('ob_gzhandler');
            $this->_is_compressing_output = true;
        }
    }

    function finish() {
        if (!empty($this->_is_compressing_output))
            ob_end_flush();
    }
    

    function getSessionVar($key) {
        return $this->session->get($key);
    }
    function setSessionVar($key, $val) {
        return $this->session->set($key, $val);
    }
    function deleteSessionVar($key) {
        return $this->session->delete($key);
    }

    function getCookieVar($key) {
        return $this->cookies->get($key);
    }
    function setCookieVar($key, $val, $lifetime_in_days = false) {
        return $this->cookies->set($key, $val, $lifetime_in_days);
    }
    function deleteCookieVar($key) {
        return $this->cookies->delete($key);
    }
    
    function getUploadedFile($key) {
        return Request_UploadedFile::getUploadedFile($key);
    }
    

    function _fix_magic_quotes_gpc() {
        $needs_fix = array('HTTP_POST_VARS',
                           'HTTP_GET_VARS',
                           'HTTP_COOKIE_VARS',
                           'HTTP_SERVER_VARS',
                           'HTTP_POST_FILES');
        
        // Fix magic quotes.
        if (get_magic_quotes_gpc()) {
            foreach ($needs_fix as $vars)
                $this->_stripslashes($GLOBALS[$vars]);
        }
    }


    function _stripslashes(&$var) {
        if (is_array($var)) {
            foreach ($var as $key => $val)
                $this->_stripslashes($var[$key]);
        }
        elseif (is_string($var))
            $var = stripslashes($var);
    }
    
    function _fix_multipart_form_data () {
        if (preg_match('|^multipart/form-data|', $this->get('CONTENT_TYPE')))
            $this->_strip_leading_nl($GLOBALS['HTTP_POST_VARS']);
    }
    
    function _strip_leading_nl(&$var) {
        if (is_array($var)) {
            foreach ($var as $key => $val)
                $this->_strip_leading_nl($var[$key]);
        }
        elseif (is_string($var))
            $var = preg_replace('|^\r?\n?|', '', $var);
    }
}

class Request_SessionVars {
    function Request_SessionVars() {
        // Prevent cacheing problems with IE 5
        session_cache_limiter('none');
                                        
        session_start();
    }
    
    function get($key) {
        $vars = &$GLOBALS['HTTP_SESSION_VARS'];
        if (isset($vars[$key]))
            return $vars[$key];
        return false;
    }
    
    function set($key, $val) {
        $vars = &$GLOBALS['HTTP_SESSION_VARS'];
        if (ini_get('register_globals')) {
            // This is funky but necessary, at least in some PHP's
            $GLOBALS[$key] = $val;
        }
        $vars[$key] = $val;
        session_register($key);
    }
    
    function delete($key) {
        $vars = &$GLOBALS['HTTP_SESSION_VARS'];
        if (ini_get('register_globals'))
            unset($GLOBALS[$key]);
        unset($vars[$key]);
        session_unregister($key);
    }
}

class Request_CookieVars {
    
    function get($key) {
        $vars = &$GLOBALS['HTTP_COOKIE_VARS'];
        if (isset($vars[$key])) {
            @$val = unserialize($vars[$key]);
            if (!empty($val))
                return $val;
        }
        return false;
    }
        
    function set($key, $val, $persist_days = false) {
        $vars = &$GLOBALS['HTTP_COOKIE_VARS'];
        
        if (is_numeric($persist_days)) {
            $expires = time() + (24 * 3600) * $persist_days;
        }
        else {
            $expires = 0;
        }
        
        $packedval = serialize($val);
        $vars[$key] = $packedval;
        setcookie($key, $packedval, $expires, '/');
    }
    
    function delete($key) {
        $vars = &$GLOBALS['HTTP_COOKIE_VARS'];
        setcookie($key);
        unset($vars[$key]);
    }
}

class Request_UploadedFile {
    function getUploadedFile($postname) {
        global $HTTP_POST_FILES;
        
        if (!isset($HTTP_POST_FILES[$postname]))
            return false;
        
        $fileinfo = &$HTTP_POST_FILES[$postname];
        if (!is_uploaded_file($fileinfo['tmp_name']))
            return false;       // possible malicious attack.

        return new Request_UploadedFile($fileinfo);
    }
    
    function Request_UploadedFile($fileinfo) {
        $this->_info = $fileinfo;
    }

    function getSize() {
        return $this->_info['size'];
    }

    function getName() {
        return $this->_info['name'];
    }

    function getType() {
        return $this->_info['type'];
    }

    function open() {
        if ( ($fd = fopen($this->_info['tmp_name'], "rb")) ) {
            if ($this->getSize() < filesize($this->_info['tmp_name'])) {
                // FIXME: Some PHP's (or is it some browsers?) put
                //    HTTP/MIME headers in the file body, some don't.
                //
                // At least, I think that's the case.  I know I used
                // to need this code, now I don't.
                //
                // This code is more-or-less untested currently.
                //
                // Dump HTTP headers.
                while ( ($header = fgets($fd, 4096)) ) {
                    if (trim($header) == '') {
                        break;
                    }
                    else if (!preg_match('/^content-(length|type):/i', $header)) {
                        rewind($fd);
                        break;
                    }
                }
            }
        }
        return $fd;
    }

    function getContents() {
        $fd = $this->open();
        $data = fread($fd, $this->getSize());
        fclose($fd);
        return $data;
    }
}

/**
 * Create NCSA "combined" log entry for current request.
 */
class Request_AccessLogEntry
{
    /**
     * Constructor.
     *
     * The log entry will be automatically appended to the log file
     * when the current request terminates.
     *
     * If you want to modify a Request_AccessLogEntry before it gets
     * written (e.g. via the setStatus and setSize methods) you should
     * use an '&' on the constructor, so that you're working with the
     * original (rather than a copy) object.
     *
     * <pre>
     *    $log_entry = & new Request_AccessLogEntry($req, "/tmp/wiki_access_log");
     *    $log_entry->setStatus(401);
     * </pre>
     *
     *
     * @param $request object  Request object for current request.
     * @param $logfile string  Log file name.
     */
    function Request_AccessLogEntry (&$request, $logfile) {
        $this->logfile = $logfile;
        
        $this->host  = $request->get('REMOTE_HOST');
        $this->ident = $request->get('REMOTE_IDENT');
        if (!$this->ident)
            $this->ident = '-';
        $this->user = '-';        // FIXME: get logged-in user name
        $this->time = time();
        $this->request = join(' ', array($request->get('REQUEST_METHOD'),
                                         $request->get('REQUEST_URI'),
                                         $request->get('SERVER_PROTOCOL')));
        $this->status = 200;
        $this->size = 0;
        $this->referer = (string) $request->get('HTTP_REFERER');
        $this->user_agent = (string) $request->get('HTTP_USER_AGENT');

        global $Request_AccessLogEntry_entries;
        if (!isset($Request_AccessLogEntry_entries)) {
            register_shutdown_function("Request_AccessLogEntry_shutdown_function");
        }
        $Request_AccessLogEntry_entries[] = &$this;
    }

    /**
     * Set result status code.
     *
     * @param $status integer  HTTP status code.
     */
    function setStatus ($status) {
        $this->status = $status;
    }
    
    /**
     * Set response size.
     *
     * @param $size integer
     */
    function setSize ($size) {
        $this->size = $size;
    }
    
    /**
     * Get time zone offset.
     *
     * This is a static member function.
     *
     * @param $time integer Unix timestamp (defaults to current time).
     * @return string Zone offset, e.g. "-0800" for PST.
     */
    function _zone_offset ($time = false) {
        if (!$time)
            $time = time();
        $offset = date("Z", $time);
        if ($offset < 0) {
            $negoffset = "-";
            $offset = -$offset;
        }
        $offhours = floor($offset / 3600);
        $offmins  = $offset / 60 - $offhours * 60;
        return sprintf("%s%02d%02d", $negoffset, $offhours, $offmins);
    }

    /**
     * Format time in NCSA format.
     *
     * This is a static member function.
     *
     * @param $time integer Unix timestamp (defaults to current time).
     * @return string Formatted date & time.
     */
    function _ncsa_time($time = false) {
        if (!$time)
            $time = time();

        return date("d/M/Y:H:i:s", $time) .
            " " . $this->_zone_offset();
    }

    /**
     * Write entry to log file.
     */
    function write() {
        $entry = sprintf('%s %s %s [%s] "%s" %d %d "%s" "%s"',
                         $this->host, $this->ident, $this->user,
                         $this->_ncsa_time($this->time),
                         $this->request, $this->status, $this->size,
                         $this->referer, $this->user_agent);

        //Error log doesn't provide locking.
        //error_log("$entry\n", 3, $this->logfile);

        // Alternate method 
        if (($fp = fopen($this->logfile, "a"))) {
            flock($fp, LOCK_EX);
            fputs($fp, "$entry\n");
            fclose($fp);
        }
    }
}

/**
 * Shutdown callback.
 *
 * @access private
 * @see Request_AccessLogEntry
 */
function Request_AccessLogEntry_shutdown_function ()
{
    global $Request_AccessLogEntry_entries;
    
    foreach ($Request_AccessLogEntry_entries as $entry) {
        $entry->write();
    }
    unset($Request_AccessLogEntry_entries);
}

// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:   
?>
