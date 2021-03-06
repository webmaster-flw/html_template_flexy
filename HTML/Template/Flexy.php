<?php
//
// +----------------------------------------------------------------------+
// | PHP Version 5                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author:  Alan Knowles <alan@akbkhome.com>
// | Original Author: Wolfram Kriesing <wolfram@kriesing.de>             |
// +----------------------------------------------------------------------+
//

require_once 'PEAR.php';
require_once 'HTML/Template/Flexy/Assign.php';
require_once 'HTML/Template/Flexy/Compiler.php';
require_once 'HTML/Template/Flexy/Element.php';
require_once 'HTML/Template/Flexy/Plugin.php';

/**
*   @package    HTML_Template_Flexy
*/
// prevent disaster when used with xdebug! 
@ini_set('xdebug.max_nesting_level', 1000);

/*
* Global variable - used to store active options when compiling a template.
*/
$GLOBALS['_HTML_TEMPLATE_FLEXY'] = array(); 

// ERRORS:

define('HTML_TEMPLATE_FLEXY_ERROR_SYNTAX',-1);  // syntax error in template.
define('HTML_TEMPLATE_FLEXY_ERROR_INVALIDARGS',-2);  // bad arguments to methods.
define('HTML_TEMPLATE_FLEXY_ERROR_FILE',-2);  // file access problem

define('HTML_TEMPLATE_FLEXY_ERROR_RETURN',1);  // RETURN ERRORS
define('HTML_TEMPLATE_FLEXY_ERROR_DIE',8);  // FATAL DEATH
/**
* A Flexible Template engine - based on simpletemplate  
*
* @abstract Long Description
*  Have a look at the package description for details.
*
* usage: 
* $template = new HTML_Template_Flexy($options);
* $template->compiler('/name/of/template.html');
* $data =new StdClass
* $data->text = 'xxxx';
* $template->outputObject($data,$elements)
*
* Notes:
* $options can be blank if so, it is read from 
* PEAR5::getStaticProperty('HTML_Template_Flexy', 'options');
*
* the first argument to outputObject is an object (which could even be an 
* associateve array cast to an object) - I normally send it the controller class.
* the seconde argument '$elements' is an array of HTML_Template_Flexy_Elements
* eg. array('name'=> new HTML_Template_Flexy_Element('',array('value'=>'fred blogs'));
* 
*
*
*
* @version    $Id$
*/



class HTML_Template_Flexy  
{

    /* 
    *   @var    array   $options    the options for initializing the template class
    */
    var $options = array(   
        'compileDir'    =>  '',         // where do you want to write to.. (defaults to session.save_path)
        'templateDir'   =>  '',         // where are your templates
        
        // where the template comes from. ------------------------------------------
        'multiSource'   => false,       // Allow same template to exist in multiple places
                                        // So you can have user themes....
        'templateDirOrder' => '',       // set to 'reverse' to assume that first template
        
         
        'debug'         => false,       // prints a few messages
        
        
        // compiling conditions ------------------------------------------
        'compiler'      => 'Flexy',  // which compiler to use. (Flexy,Regex, Raw,Xipe)
        'forceCompile'  =>  false,      // only suggested for debugging
        'dontCompile'	=> false,		// never compile - use this if you're manually compiling your templates

        // regex Compiler       ------------------------------------------
        'filters'       => array(),     // used by regex compiler.
        
        // standard Compiler    ------------------------------------------
        'nonHTML'       => false,       // dont parse HTML tags (eg. email templates)
        'allowPHP'      => false,       // allow PHP in template (use true=allow, 'delete' = remove it.)
        
        'flexyIgnore'   => 0,           // turn on/off the tag to element code
        'numberFormat'  => ",2,'.',','",  // default number format  {xxx:n} format = eg. 1,200.00 
        
        'url_rewrite'   => '',          // url rewriting ability:
                                        // eg. "images/:test1/images/,js/:test1/js"
                                        // changes href="images/xxx" to href="test1/images/xxx"
                                        // and src="js/xxx.js" to src="test1/js/xxx.js"
                                        
        'compileToString' => false,     // should the compiler return a string 
                                        // rather than writing to a file.
        'privates'      => false,       // allow access to _variables (eg. suido privates
        'globals'       => false,       // allow access to _GET/_POST/_REQUEST/GLOBALS/_COOKIES/_SESSION

        'globalfunctions' => false,     // allow GLOBALS.date(#d/m/Y#) to have access to all PHP's methods
                                        // warning dont use unless you trust the template authors
                                        // exec() becomes exposed.
        'useElementLabels' => true,     // WARNING DO NOT ENABLE THIS UNLESS YOU TRUST THE TEMPLATE AUTHORS
                                        // look for label elements referring to input elements
                                        // you can then set (replace) the label of the corresponding input
                                        // element by $element->label="my new label text";
        // get text/transalation suppport ------------------------------------------
        //  (flexy compiler only)
        'disableTranslate' => false,    // if true, skips the translation functionality completely
        'locale'        => 'en',        // works with gettext or File_Gettext
        'textdomain'    => '',          // for gettext emulation with File_Gettext
                                        // eg. 'messages' (or you can use the template name.
        'textdomainDir' => '',          // eg. /var/www/site.com/locale
                                        // so the french po file is:
                                        // /var/www/site.com/local/fr/LC_MESSAGE/{textdomain}.po
        
        'Translation2'  => false,       // to make Translation2 a provider.
                                        // rather than gettext.
                                        // set to:
                                        //  'Translation2' => array(
                                        //         'driver' => 'dataobjectsimple',
                                        //         'CommonPageID' => 'page.id'    
                                        //         'options' => array()
                                        //  );
                                        //
                                        // Note: CommonPageID : to use a single page id for all templates
                                        //
                                        // or the slower way.. 
                                        //   = as it requires loading the code..
                                        //
                                        //  'Translation2' => new Translation2('dataobjectsimple','')
                                        
      
        'charset'       => 'UTF-8',     // charset used with htmlspecialchars to render data.
                                        // experimental
        
        // output options           ------------------------------------------
        'strict'        => false,       // All elements in the template must be defined - 
                                        // makes php E_NOTICE warnings appear when outputing template.
                                        
        'fatalError'    => HTML_TEMPLATE_FLEXY_ERROR_DIE,       // default behavior is to die on errors in template.
        
        'plugins'       => array(),     // load classes to be made available via the plugin method
                                        // eg. = array('Savant') - loads the Savant methods.
                                        // = array('MyClass_Plugins' => 'MyClass/Plugins.php')
                                        //    Class, and where to include it from..
    ); 
    /**
    * The compiled template filename (Full path)
    *
    * @var string
    * @access public
    */
    var $compiledTemplate;
    /**
    * The source template filename (Full path)
    *
    * @var string
    * @access public
    */
    
    
    var $currentTemplate;
    
    /**
    * The getTextStrings Filename
    *
    * @var string
    * @access public
    */
    var $getTextStringsFile;
    /**
    * The serialized elements array file.
    *
    * @var string
    * @access public
    */
    var $elementsFile;
    
     
    /**
    * Array of HTML_elements which is displayed on the template
    * 
    * Technically it's private (eg. only the template uses it..)
    * 
    *
    * @var array of  HTML_Template_Flexy_Elements
    * @access private
    */
    var $elements = array();
    /**
    *   Constructor 
    *
    *   Initializes the Template engine, for each instance, accepts options or
    *   reads from PEAR5::getStaticProperty('HTML_Template_Flexy', 'options');
    *
    *   @access public
    *   @param    array    $options (Optional)
    */
    public function __construct(array $options = array())
    {
        $baseoptions = PEAR::getStaticProperty('HTML_Template_Flexy', 'options');
        if (!is_array($baseoptions)) {
            $baseoptions = array();
        }
        $this->options = array_merge($this->options, $baseoptions, $options);

        $filters = $this->options['filters'];
        if (is_string($filters)) {
            $this->options['filters']= explode(',', $filters);
        }

        $templateDir = $this->options['templateDir'];
        if (is_string($templateDir)) {
            $this->options['templateDir'] = explode(PATH_SEPARATOR, $templateDir);
        }
    }

    /**
     * given a file, return the possible templates that will becompiled.
     *
     *  @param  string $file  the template to look for.
     *  @return string|PEAR_Error $directory 
     */
   
    function resolvePath ( $file )
    {
        $dirs = array_unique($this->options['templateDir']);
        if ($this->options['templateDirOrder'] == 'reverse') {
            $dirs = array_reverse($dirs);
        }
        $ret = false;
        foreach ($dirs as $tmplDir) {
            if (@!file_exists($tmplDir . DIRECTORY_SEPARATOR .$file)) {
                continue;
            }
            
            if (!$this->options['multiSource'] && ($ret !== false)) {
                return self::raiseError(
                    "You have more than one template Named {$file} in your paths, found in both"
                    . "<BR>{$this->currentTemplate }<BR>{$tmplDir}" . DIRECTORY_SEPARATOR . $file,
                    HTML_TEMPLATE_FLEXY_ERROR_INVALIDARGS,
                    HTML_TEMPLATE_FLEXY_ERROR_DIE,
                    $this->options
                );
            }
            
            $ret = $tmplDir;
            
        }
        return $ret;
        
    }
 
 
    /**
    *   compile the template
    *
    *   @access     public
    *   @version    01/12/03
    *   @author     Wolfram Kriesing <wolfram@kriesing.de>
    *   @param      string  $file   relative to the 'templateDir' which you set when calling the constructor
    *   @return     boolean true on success. (or string, if compileToString) PEAR_Error on failure..
    */
    function compile( $file )
    {
        if (!$file) {
            return self::raiseError(
                'HTML_Template_Flexy::compile no file selected',
                HTML_TEMPLATE_FLEXY_ERROR_INVALIDARGS,
                HTML_TEMPLATE_FLEXY_ERROR_DIE,
                $this->options
            );
        }
        
        if (!@$this->options['locale']) {
            $this->options['locale']='en';
        }
        
        
        //Remove the slash if there is one in front, just to be safe.
        $file = ltrim($file,DIRECTORY_SEPARATOR);
        
        
        if (strpos($file,'#')) {
            list($file,$this->options['output.block']) = explode('#', $file);
        }
        
        $parts = array();
        $tmplDirUsed = false;
        
        // PART A mulitlanguage support: ( part B is gettext support in the engine..) 
        //    - user created language version of template.
        //    - compile('abcdef.html') will check for compile('abcdef.en.html') 
        //       (eg. when locale=en)
        
        $this->currentTemplate  = false;
        
        if (preg_match('/(.*)(\.[a-z]+)$/i',$file,$parts)) {
            $newfile = $parts[1].'.'.$this->options['locale'] .$parts[2];
            $match = $this->resolvePath($newfile);
            if ($match instanceof PEAR_Error) {
                return $match;
            }
            if (false !== $match ) {
                $this->currentTemplate = $match . DIRECTORY_SEPARATOR .$newfile;
                $tmplDirUsed = $match;
            }
            
          
        }
        
        // look in all the posible locations for the template directory..
        if ($this->currentTemplate  === false) {
            
            
            $match = $this->resolvePath($file);
            
             if ($match instanceof PEAR_Error) {
                return $match;
            }
            if (false !== $match ) {
                $this->currentTemplate = $match . DIRECTORY_SEPARATOR .$file;
                $tmplDirUsed = $match;
            }
              
        }
        if ($this->currentTemplate === false)  {
            // check if the compile dir has been created
            return self::raiseError(
                "Could not find Template {$file} in any of the directories<br>"
                . implode("<BR>", $this->options['templateDir']),
                HTML_TEMPLATE_FLEXY_ERROR_INVALIDARGS,
                HTML_TEMPLATE_FLEXY_ERROR_DIE,
                $this->options
            );
        }
        
        
        // Savant compatible compiler 
        
        if ( is_string( $this->options['compiler'] ) && ($this->options['compiler'] == 'Raw')) {
            $this->compiledTemplate = $this->currentTemplate;
            $this->debug("Using Raw Compiler");
            return true;
        }
        
        
        
        
        // now for the compile target 
        
        //If you are working with mulitple source folders and $options['multiSource'] is set
        //the template folder will be:
        // compiled_tempaltes/{templatedir_basename}_{md5_of_dir}/
        
        
        $compileSuffix = ((count($this->options['templateDir']) > 1) && $this->options['multiSource']) ? 
            DIRECTORY_SEPARATOR  .basename($tmplDirUsed) . '_' .md5($tmplDirUsed) : '';
        
        
        $compileDest = isset($this->options['compileDir']) ? $this->options['compileDir'] : '';
        
        $isTmp = false;
        // Use a default compile directory if one has not been set. 
        if (!$compileDest) {
            // Use session.save_path + 'compiled_templates_' + md5(of sourcedir)
            $compileDest = ini_get('session.save_path') .  DIRECTORY_SEPARATOR . 'flexy_compiled_templates';
            if (!file_exists($compileDest)) {
                require_once 'System.php';
                System::mkdir(array('-p',$compileDest));
            }
            $isTmp = true;
        
        }
        
         
        
        // we generally just keep the directory structure as the application uses it,
        // so we dont get into conflict with names
        // if we have multi sources we do md5 the basedir..
        
       
        $base = $compileDest . $compileSuffix . DIRECTORY_SEPARATOR .$file;
        $fullFile = $this->compiledTemplate    = $base .'.'.$this->options['locale'].'.php';
        $this->getTextStringsFile  = $base .'.gettext.serial';
        $this->elementsFile        = $base .'.elements.serial';
        if (isset($this->options['output.block'])) {
            $this->compiledTemplate    .= '#'.$this->options['output.block'];
        }
        
        if (!empty($this->options['dontCompile'])) {
            return true;
        }
        
        $recompile = false;
        
        $isuptodate = file_exists($this->compiledTemplate)   ?
            (filemtime($this->currentTemplate) == filemtime( $this->compiledTemplate)) : 0;
            
        if( !empty($this->options['forceCompile']) || !$isuptodate ) {
            $recompile = true;
        } else {
            $this->debug("File looks like it is uptodate.");
            return true;
        }
        
        
        
        
        if( !@is_dir($compileDest) || !is_writeable($compileDest)) {
            require_once 'System.php';
            System::mkdir(array('-p',$compileDest));
        }
        if( !@is_dir($compileDest) || !is_writeable($compileDest)) {
            return self::raiseError(
                "can not write to 'compileDir', which is <b>'$compileDest'</b><br>"
                . "Please give write and enter-rights to it",
                HTML_TEMPLATE_FLEXY_ERROR_FILE,
                HTML_TEMPLATE_FLEXY_ERROR_DIE,
                $this->options
            );
        }
        
        if (!file_exists(dirname($this->compiledTemplate))) {
            require_once 'System.php';
            System::mkdir(array('-p','-m', 0770, dirname($this->compiledTemplate)));
        }
         
        // Compile the template in $file. 
        $compiler = HTML_Template_Flexy_Compiler::factory($this->options);
        $ret = $compiler->compile($this);
        if ($ret instanceof PEAR_Error) {
            return self::raiseError(
                'HTML_Template_Flexy fatal error:' . $ret->message,
                $ret->code,
                HTML_TEMPLATE_FLEXY_ERROR_DIE,
                $this->options
            );
        }
        return $ret;
        
        //return $this->$method();
        
    }

     /**
    *  compiles all templates
    *  Used for offline batch compilation (eg. if your server doesn't have write access to the filesystem).
    *
    *   @access     public
    *   @author     Alan Knowles <alan@akbkhome.com>
    *
    */
    function compileAll($dir = '',$regex='/.html$/')
    {
        $c = new HTML_Template_Flexy_Compiler;
        $c->compileAll($this,$dir,$regex);
    } 
    
    /**
    *   Outputs an object as $t 
    *
    *   for example the using simpletags the object's variable $t->test
    *   would map to {test}
    *
    *   @version    01/12/14
    *   @access     public
    *   @author     Alan Knowles
    *   @param    object   to output  
    *   @param    array  HTML_Template_Flexy_Elements (or any object that implements toHtml())
    *   @return     none
    */
    public function outputObject($t, $elements = array())
    {
        if (!is_array($elements)) {
            return self::raiseError(
                'second Argument to HTML_Template_Flexy::outputObject() was an '
                . gettype($elements) . ', not an array',
                HTML_TEMPLATE_FLEXY_ERROR_INVALIDARGS,
                HTML_TEMPLATE_FLEXY_ERROR_DIE,
                $this->options
            );
        }
        if (@$this->options['debug']) {
            echo "output $this->compiledTemplate<BR>";
        }
  
        // this may disappear later it's a Backwards Compatibility fudge to try 
        // and deal with the first stupid design decision to not use a second argument
        // to the method.
       
        if (count($this->elements) && !count($elements)) {
            $elements = $this->elements;
        }
        // end depreciated code
        
        
        $this->elements = $this->getElements();
        
        // Overlay values from $elements to $this->elements (which is created from the template)
        // Remove keys with no corresponding value.
        foreach($elements as $k=>$v) {
            // Remove key-value pair from $this->elements if hasn't a value in $elements.
            if (!$v) {
                unset($this->elements[$k]);
            }
            // Add key-value pair to $this->$elements if it's not there already.
            if (!isset($this->elements[$k])) {
                $this->elements[$k] = $v;
                continue;
            }
            // Call the clever element merger - that understands form values and 
            // how to display them...
            $this->elements[$k] = $this->elements[$k]->merge($v);
        }
        //echo '<PRE>'; print_r(array($elements,$this->elements));
      
        
        // we use PHP's error handler to hide errors in the template.
        // use $options['strict'] - if you want to force declaration of
        // all variables in the template
        
        
        $_error_reporting = false;
        if (!$this->options['strict']) {
            $_error_reporting = error_reporting(E_ALL & ~(E_NOTICE | E_STRICT | E_DEPRECATED));
        }
        if (!is_readable($this->compiledTemplate)) {
            return self::raiseError(
                "Could not open the template: <b>'{$this->compiledTemplate}'</b><BR>"
                . "Please check the file permissions on the directory and file ",
                HTML_TEMPLATE_FLEXY_ERROR_FILE,
                HTML_TEMPLATE_FLEXY_ERROR_DIE,
                $this->options
            );
        }
        
        // are we using the assign api!
        
        if (isset($this->assign)) {
            if (!$t) {
                $t = (object) $this->assign->variables;
            }
            extract($this->assign->variables);
            foreach(array_keys($this->assign->references) as $_k) {
                $$_k = &$this->assign->references[$_k];
            }
        }
        // used by Flexy Elements etc..
        $GLOBALS['_HTML_TEMPLATE_FLEXY']['options']  = $this->options;
        
        include($this->compiledTemplate);
        
        // Return the error handler to its previous state. 
        
        if ($_error_reporting !== false) {
            error_reporting($_error_reporting);
        }
    }
    /**
    *   Outputs an object as $t, buffers the result and returns it.
    *
    *   See outputObject($t) for more details.
    *
    *   @version    01/12/14
    *   @access     public
    *   @author     Alan Knowles
    *   @param      object object to output as $t
    *   @return     string - result
    */
    public function bufferedOutputObject($t, $elements = array())
    {
        ob_start();
        $this->outputObject($t,$elements);
        $data = ob_get_contents();
        ob_end_clean();
        return $data;
    }
    /**
    * static version which does new, compile and output all in one go.
    *
    *   See outputObject($t) for more details.
    *
    *   @version    01/12/14
    *   @access     public
    *   @author     Alan Knowles
    *   @param      object object to output as $t
    *   @param      filename of template
    *   @return     string - result
    */
    public static function staticQuickTemplate($file, $t)
    {
        $template = new HTML_Template_Flexy;
        $template->compile($file);
        $template->outputObject($t);
    }

    /**
     * if debugging is on, print the debug info to the screen
     *
     * @author  Alan Knowles <alan@akbkhome.com>
     * @param   string  $string     output to display
     * @param   boolean $debug
     * @return  void
     */
    public static function staticDebug($string, $debug = null) 
    {
        if ($debug === null) {
            $debug = !empty($GLOBALS['_HTML_TEMPLATE_FLEXY']['debug']);
        }
        if ($debug) {
            echo "<PRE><B>FLEXY DEBUG:</B> $string</PRE>";
        }
    }

    /**
     * if debugging is on, print the debug info to the screen
     *
     * @author  Alan Knowles <alan@akbkhome.com>
     * @param   string  $string     output to display
     * @return  void
     * @see     HTML_Template_Flexy::staticDebug()
     */
    public function debug($string) 
    {
        if ($this->options['debug']) {
            self::staticDebug($string, true);
        }
    }

    /**
     * @depreciated
     * See element->merge
     *
     * @access   public
     */
    public function mergeElement($original, $new)
    {
        // no original - return new
        if (!$original) {
            return clone $new;
        }
        return $original->merge($new);
    }  

    /**
    * Get an array of elements from the template
    *
    * All <form> elements (eg. <input><textarea) etc.) and anything marked as 
    * dynamic  (eg. flexy:dynamic="yes") are converted in to elements
    * (simliar to XML_Tree_Node) 
    * you can use this to build the default $elements array that is used by
    * outputObject() - or just create them and they will be overlayed when you
    * run outputObject()
    *
    *
    * @return   array   of HTML_Template_Flexy_Element sDescription
    * @access   public
    */
    
    function getElements() {
    
        if ($this->elementsFile && file_exists($this->elementsFile)) {
            return unserialize(file_get_contents($this->elementsFile));
        }
        return array();
    }
    
    
    /**
    * Lazy loading of PEAR, and the error handler..
    * This should load HTML_Template_Flexy_Error really..
    * 
    * @param   string message
    * @param   int      error type.
    * @param   int      an equivalant to pear error return|die etc.
    * @param   array    options
    *
    * @return   object      pear error.
    * @access   public
    */
    public static function raiseError(
        $message,
        $type = null,
        $fatal = HTML_TEMPLATE_FLEXY_ERROR_RETURN,
        array $options = null
    ) {
        self::staticDebug(
            "<B>HTML_Template_Flexy::raiseError</B>$message",
            ($options) ? $options['debug'] : null
        );

        if ($fatal === HTML_TEMPLATE_FLEXY_ERROR_DIE) {
            // rewrite DIE!
            if ($options) {
                return PEAR::raiseError($message, $type, $options['fatalError']);
            } elseif (isset($GLOBALS['_HTML_TEMPLATE_FLEXY']['fatalError'])) {
                return PEAR::raiseError($message, $type, $GLOBALS['_HTML_TEMPLATE_FLEXY']['fatalError']);
            }
        }

        return PEAR::raiseError($message, $type, $fatal);
    }


    /**
    * 
    * Assign API - 
    * 
    * read the docs on HTML_Template_Flexy_Assign::assign()
    *
    * @param   varargs ....
    * 
    *
    * @return   mixed    PEAR_Error or true?
    * @access   public
    * @see  HTML_Template_Flexy_Assign::assign()
    * @status alpha
    */
  
    function setData() {
        // load assigner..
        if (!isset($this->assign)) {
            $this->assign = new HTML_Template_Flexy_Assign;
        }
        return $this->assign->assign(func_get_args());
    }
    /**
    * 
    * Assign API - by Reference
    * 
    * read the docs on HTML_Template_Flexy_Assign::assign()
    *
    * @param  key  string
    * @param  value mixed
    * 
    * @return   mixed    PEAR_Error or true?
    * @access   public
    * @see  HTML_Template_Flexy_Assign::assign()
    * @status alpha
    */
        
    function setDataByRef($k,&$v) {
        // load assigner..
        if (!isset($this->assign)) {
            $this->assign = new HTML_Template_Flexy_Assign;
        }
        $this->assign->assignRef($k,$v);
    } 
    /**
    * 
    * Plugin (used by templates as $this->plugin(...) or {this.plugin(#...#,#....#)}
    * 
    * read the docs on HTML_Template_Flexy_Plugin()
    *
    * @param  varargs ....
    * 
    * @return   mixed    PEAR_Error or true?
    * @access   public
    * @see  HTML_Template_Flexy_Plugin
    * @status alpha
    */
    function plugin() {
        // load pluginManager.
        if (!isset($this->plugin)) {
            $this->plugin = new HTML_Template_Flexy_Plugin;
            $this->plugin->flexy = $this;
        }
        return $this->plugin->call(func_get_args());
    } 
    /**
    * 
    * output / display ? - outputs an object, without copy by references..
    * 
    * @param  optional mixed object to output
    * 
    * @return   mixed    PEAR_Error or true?
    * @access   public
    * @see  HTML_Template_Flexy::ouptutObject
    * @status alpha
    */
    function output($object = false) 
    {
        return $this->outputObject($object);
    }
    
    /**
    * 
    * render the template with data..
    * 
    * @param  optional mixed object to output
    * 
    * @return   mixed    PEAR_Error or true?
    * @access   public
    * @see  HTML_Template_Flexy::ouptutObject
    * @status alpha
    */
    function toString($object = false) 
    {
        return $this->bufferedOutputObject($object);
    }
    
}
