<?php
/**
 * Plugin ifauth: Displays content at given time. (After next cache update)
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Otto Vainio <oiv-ifauth@valjakko.net>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_ifauth extends DokuWiki_Syntax_Plugin {

    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'container';
    }

    function accepts($mode){
        return true;
    }

    /**
     * Paragraph Type
     *
     * Defines how this syntax is handled regarding paragraphs. This is important
     * for correct XHTML nesting. Should return one of the following:
     *
     * 'normal' - The plugin can be used inside paragraphs
     * 'block'  - Open paragraphs need to be closed before plugin output
     * 'stack'  - Special case. Plugin wraps other paragraphs.
     *
     * @see Doku_Handler_Block
     */
    function getPType() {
        return 'stack';
    }

    function getAllowedTypes() {
        return array(
            'container',
            'formatting',
            'substition',
            'protected',
            'disabled',
            'paragraphs',
            'baseonly',
        );
    }

    function getSort(){
        return 196;
    }
    function connectTo($mode) {
        $this->Lexer->addEntryPattern('<ifauth.*?>(?=.*?\x3C/ifauth\x3E)',$mode,'plugin_ifauth');
    }
    function postConnect() {
        $this->Lexer->addExitPattern('</ifauth>','plugin_ifauth');
    }


    /**
     * Handle the match
     */
    function handle($match, $state, $pos, &$handler){
        switch ($state) {
            case DOKU_LEXER_ENTER :

                // remove <ifauth and >
                $auth  = trim(substr($match, 8, -1));
                // explode wanted auths
                $this->aauth = explode(",",$auth);

                // FIXME remember aauth here

                $ReWriter = new Doku_Handler_Nest($handler->CallWriter,'plugin_ifauth');
                $handler->CallWriter = & $ReWriter;

                // don't add any plugin instruction:
                return false;

            case DOKU_LEXER_UNMATCHED :
                // unmatched data is cdata
                $handler->_addCall('cdata', array($match), $pos);
                // don't add any plugin instruction:
                return false;

            case DOKU_LEXER_EXIT :
                // get all calls we intercepted
                $calls = $handler->CallWriter->calls;

                // switch back to the old call writer
                $ReWriter = & $handler->CallWriter;
                $handler->CallWriter = & $ReWriter->CallWriter;

                // return a plugin instruction
                return array($state, $calls, $this->aauth);
        }
        return false;
    }

    /**
     * Create output
     */
    function render($mode, &$renderer, $data) {
        global $INFO;

        // we can't cache here
        $renderer->nocache();

        list($state, $calls, $aauth) = $data;
        if($state != DOKU_LEXER_EXIT) return true;

        // Store current user info. Add '@' to the group names
        $grps=array();
        if (is_array($INFO['userinfo'])) {
            foreach($INFO['userinfo']['grps'] as $val) {
                $grps[] = "@".$val;
            }
        }
        $grps[]=$_SERVER['REMOTE_USER'];

        $rend = false;
        // Loop through each wanted user / group
        foreach($aauth as $val) {
            $not = false;

            // Check negation
            if (substr($val,0,1)=="!") {
                $not = true;
                $val = substr($val,1);
            }
            // FIXME More complicated rules may be wanted. Currently any rule that matches for render overrides others.


            // If current user/group found in wanted groups/userid, then render.
            if (!$not && in_array($val,$grps)) {
                $rend = true;
            }

            // If user set as not wanted (!) or not found from current user/group then render.
            if ($not && !in_array($val,$grps)) {
                $rend = true;
            }
        }
        // if user is authenticated, render the instructions
        if ($rend) {
            foreach($calls as $i){
                if(method_exists($renderer,$i[0])){
                    call_user_func_array(array($renderer,$i[0]),$i[1]);
                }
            }
        }
        return true;
    }
}
