<?php //-*-php-*-
rcs_id('$Id: WikiPlugin.php,v 1.20 2002/02/15 05:34:49 carstenklapp Exp $');

class WikiPlugin
{
    function getDefaultArguments() {
        return array();
    }


    // FIXME: args?
    function run ($argstr, $request) {
        trigger_error("WikiPlugin::run: pure virtual function",
                      E_USER_ERROR);
    }

    /**
     * Get name of plugin.
     *
     * This is used (by default) by getDefaultLinkArguments and
     * getDefaultFormArguments to compute the default link/form
     * targets.
     *
     * If you want to gettextify the name (probably a good idea),
     * override this method in your plugin class, like:
     * <pre>
     *   function getName() { return _("MyPlugin"); }
     * </pre>
     *
     * @return string plugin name/target.
     */
    function getName() {
        return preg_replace('/^.*_/', '',  get_class($this));
    }

    function getDescription() {
        return $this->getName();
    }


    function getArgs($argstr, $request, $defaults = false) {
        if ($defaults === false)
            $defaults = $this->getDefaultArguments();

        list ($argstr_args, $argstr_defaults) = $this->parseArgStr($argstr);

        foreach ($defaults as $arg => $default_val) {
            if (isset($argstr_args[$arg]))
                $args[$arg] = $argstr_args[$arg];
            elseif ( ($argval = $request->getArg($arg)) !== false )
                $args[$arg] = $argval;
            elseif (isset($argstr_defaults[$arg]))
                $args[$arg] = (string) $argstr_defaults[$arg];
            else
                $args[$arg] = $default_val;

            $args[$arg] = $this->expandArg($args[$arg], $request);

            unset($argstr_args[$arg]);
            unset($argstr_defaults[$arg]);
        }

        foreach (array_merge($argstr_args, $argstr_defaults) as $arg => $val) {
            trigger_error(sprintf(_("argument '%s' not declared by plugin"),
                                  $arg), E_USER_NOTICE);
        }

        return $args;
    }

    function expandArg($argval, $request) {
        return preg_replace('/\[(\w[\w\d]*)\]/e', '$request->getArg("$1")',
                            $argval);
    }


    function parseArgStr($argstr) {
        $arg_p = '\w+';
        $op_p = '(?:\|\|)?=';
        $word_p = '\S+';
        $opt_ws = '\s*';
        $qq_p = '" ( (?:[^"\\\\]|\\\\.)* ) "';
        //"<--kludge for brain-dead syntax coloring
        $q_p  = "' ( (?:[^'\\\\]|\\\\.)* ) '";
        $gt_p = "_\\( $opt_ws $qq_p $opt_ws \\)";
        $argspec_p = "($arg_p) $opt_ws ($op_p) $opt_ws (?: $qq_p|$q_p|$gt_p|($word_p))";

        $args = array();
        $defaults = array();

        while (preg_match("/^$opt_ws $argspec_p $opt_ws/x", $argstr, $m)) {
            @ list(,$arg,$op,$qq_val,$q_val,$gt_val,$word_val) = $m;
            $argstr = substr($argstr, strlen($m[0]));

            // Remove quotes from string values.
            if ($qq_val)
                $val = stripslashes($qq_val);
            elseif ($q_val)
                $val = stripslashes($q_val);
            elseif ($gt_val)
                $val = _(stripslashes($gt_val));
            else
                $val = $word_val;

            if ($op == '=') {
                $args[$arg] = $val;
            }
            else {
                // NOTE: This does work for multiple args. Use the
                // separator character defined in your webserver
                // configuration, usually & or &amp; (See
                // http://www.htmlhelp.com/faq/cgifaq.4.html)
                // e.g. <plugin RecentChanges days||=1 show_all||=0 show_minor||=0>
                // url: RecentChanges?days=1&show_all=1&show_minor=0
                assert($op == '||=');
                $defaults[$arg] = $val;
            }
        }

        if ($argstr) {
            trigger_error(sprintf(_("trailing cruft in plugin args: '%s'"),
                                    $argstr), E_USER_NOTICE);
        }

        return array($args, $defaults);
    }


    function getDefaultLinkArguments() {
        return array('targetpage' => $this->getName(),
                     'linktext' => $this->getName(),
                     'description' => $this->getDescription(),
                     'class' => 'wikiaction');
    }
    
    function makeLink($argstr, $request) {
        $defaults = $this->getDefaultArguments();
        $link_defaults = $this->getDefaultLinkArguments();
        $defaults = array_merge($defaults, $link_defaults);
    
        $args = $this->getArgs($argstr, $request, $defaults);
        $plugin = $this->getName();
    
        $query_args = array();
        foreach ($args as $arg => $val) {
            if (isset($link_defaults[$arg]))
                continue;
            if ($val != $defaults[$arg])
                $query_args[$arg] = $val;
        }
    
        $link = Button($query_args, $args['linktext'], $args['targetpage']);
        if (!empty($args['description']))
            $link->addTooltip($args['description']);
    
        return $link;
    }
    
    function getDefaultFormArguments() {
        return array('targetpage' => $this->getName(),
                    'buttontext' => $this->getName(),
                    'class' => 'wikiaction',
                    'method' => 'get',
                    'textinput' => 's',
                    'description' => $this->getDescription(),
                    'formsize' => 30);
    }
    
    function makeForm($argstr, $request) {
        $form_defaults = $this->getDefaultFormArguments();
        $defaults = array_merge($this->getDefaultArguments(),
                                $form_defaults);
    
        $args = $this->getArgs($argstr, $request, $defaults);
        $plugin = $this->getName();
        $textinput = $args['textinput'];
        assert(!empty($textinput) && isset($args['textinput']));
    
        $form = HTML::form(array('action' => WikiURL($args['targetpage']),
                                'method' => $args['method'],
                                'class' => $args['class'],
                                'accept-charset' => CHARSET));
        $contents = HTML::div();
        $contents->setAttr('class', $args['class']);
    
        foreach ($args as $arg => $val) {
            if (isset($form_defaults[$arg]))
                continue;
            if ($arg != $textinput && $val == $defaults[$arg])
                continue;
    
            $i = HTML::input(array('name' => $arg, 'value' => $val));
    
            if ($arg == $textinput) {
                //if ($inputs[$arg] == 'file')
                //    $attr['type'] = 'file';
                //else
                $i->setAttr('type', 'text');
                $i->setAttr('size', $args['formsize']);
                if ($args['description'])
                    $i->addTooltip($args['description']);
            }
            else {
                $i->setAttr('type', 'hidden');
            }
            $contents->pushContent($i);
    
            // FIXME: hackage
            if ($i->getAttr('type') == 'file') {
                $form->setAttr('enctype', 'multipart/form-data');
                $form->setAttr('method', 'post');
                $contents->pushContent(HTML::input(array('name' => 'MAX_FILE_SIZE',
                                                         'value' => MAX_UPLOAD_SIZE,
                                                         'type' => 'hidden')));
            }
        }
    
        if (!empty($args['buttontext']))
            $contents->pushContent(HTML::input(array('type' => 'submit',
                                                     'class' => 'button',
                                                     'value' => $args['buttontext'])));
    
        $form->pushContent($contents);
        return $form;
    }
    
    function error ($message) {
        return HTML::div(array('class' => 'errors'),
                        HTML::strong(fmt("Plugin %s failed.", $this->getName())), ' ',
                        $message);
    }
}

class WikiPluginLoader {
    var $_errors;

    function expandPI($pi, $request) {
        if (!preg_match('/^\s*<\?(plugin(?:-form|-link)?)\s+(\w+)\s*(.*?)\s*\?>\s*$/s', $pi, $m))
            return $this->_error(sprintf("Bad %s", 'PI'));

        list(, $pi_name, $plugin_name, $plugin_args) = $m;
        $plugin = $this->getPlugin($plugin_name);
        if (!is_object($plugin)) {
            return new HtmlElement($pi_name == 'plugin-link' ? 'span' : 'p',
                                   array('class' => 'plugin-error'),
                                   $this->getErrorDetail());
        }
        switch ($pi_name) {
            case 'plugin':
                // FIXME: change API for run() (no $dbi needed).
                $dbi = $request->getDbh();
                return $plugin->run($dbi, $plugin_args, $request);
            case 'plugin-link':
                return $plugin->makeLink($plugin_args, $request);
            case 'plugin-form':
                return $plugin->makeForm($plugin_args, $request);
        }
    }

    function getPlugin($plugin_name) {
        global $ErrorManager;

        // Note that there seems to be no way to trap parse errors
        // from this include.  (At least not via set_error_handler().)
        $plugin_source = "lib/plugin/$plugin_name.php";

        $ErrorManager->pushErrorHandler(new WikiMethodCb($this, '_plugin_error_filter'));
        $include_failed = !include_once("lib/plugin/$plugin_name.php");
        $ErrorManager->popErrorHandler();

        $plugin_class = "WikiPlugin_$plugin_name";
        if (!class_exists($plugin_class)) {
            if ($include_failed)
                return $this->_error(sprintf(_("Include of '%s' failed"),
                                             $plugin_source));
            return $this->_error(sprintf("%s: no such class", $plugin_class));
        }

        $plugin = new $plugin_class;
        if (!is_subclass_of($plugin, "WikiPlugin"))
            return $this->_error(sprintf("%s: not a subclass of WikiPlugin",
                                         $plugin_class));

        return $plugin;
    }

    function _plugin_error_filter ($err) {
        if (preg_match("/Failed opening '.*' for inclusion/", $err->errstr))
            return true;        // Ignore this error --- it's expected.
        return false;
    }

    function getErrorDetail() {
        return $this->_errors;
    }

    function _error($message) {
        $this->_errors = $message;
        return false;
    }


};

// (c-file-style: "gnu")
// Local Variables:
// mode: php
// tab-width: 8
// c-basic-offset: 4
// c-hanging-comment-ender-p: nil
// indent-tabs-mode: nil
// End:
?>
