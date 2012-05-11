<?php
/**
 * Extends a template by another one.
 *
 * @example
 * {% extends "base" %}
 *
 * @package Liquid
 * @copyright Copyright (c) 2011-2012 Harald Hanek
 * @license http://harrydeluxe.mit-license.org
 */

class LiquidTagExtends extends LiquidTag
{
    /**
     * @var string The name of the template
     */
    private $_templateName;

    /**
     * @var LiquidDocument The LiquidDocument that represents the included template
     */
    private $_document;

    protected $_hash;


    /**
     * Constructor
     *
     * @param string $markup
     * @param array $tokens
     * @param LiquidFileSystem $file_system
     * @return IncludeLiquidTag
     */
    public function __construct($markup, &$tokens, &$file_system)
    {
        $regex = new LiquidRegexp('/("[^"]+"|\'[^\']+\')?/');

        if ($regex->match($markup))
        {
            $this->_templateName = substr($regex->matches[1], 1, strlen($regex->matches[1]) - 2);
        }
        else
        {
            throw new LiquidException("Error in tag 'extends' - Valid syntax: extends '[template name]'");
        }

        parent::__construct($markup, $tokens, $file_system);
    }


    private function _findBlocks($tokens)
    {
        $blockstart_regexp = new LiquidRegexp('/^' . LIQUID_TAG_START . '\s*block (\w+)\s*(.*)?' . LIQUID_TAG_END . '$/');
        $blockend_regexp = new LiquidRegexp('/^' . LIQUID_TAG_START . '\s*endblock\s*?' . LIQUID_TAG_END . '$/');

        $b = array();
        $name = null;

        foreach($tokens as $token)
        {
            if($blockstart_regexp->match($token))
            {
                $name = $blockstart_regexp->matches[1];
                $b[$name] = array();
            }
            else if($blockend_regexp->match($token))
            {
                $name = null;
            }
            else
            {
                if(isset($name))
                {
                    array_push($b[$name], $token);
                }
            }
        }

        return $b;
    }


    /**
     * Parses the tokens
     *
     * @param array $tokens
     */
    public function parse(&$tokens)
    {
        if (!isset($this->file_system))
        {
            throw new LiquidException("No file system");
        }



        // read the source of the template and create a new sub document
        $source = $this->file_system->read_template_file($this->_templateName);

        // tokens in this new document
        $maintokens = LiquidTemplate::tokenize($source);

        $childtokens = $this->_findBlocks($tokens);

        $blockstart_regexp = new LiquidRegexp('/^' . LIQUID_TAG_START . '\s*block (\w+)\s*(.*)?' . LIQUID_TAG_END . '$/');
        $blockend_regexp = new LiquidRegexp('/^' . LIQUID_TAG_START . '\s*endblock\s*?' . LIQUID_TAG_END . '$/');

        $b = array();
        $name = null;

        $rest = array();
        $aufzeichnen = false;

        for($i = 0; $i < count($maintokens); $i++)
        {
            if($blockstart_regexp->match($maintokens[$i]))
            {
                $name = $blockstart_regexp->matches[1];

                if(isset($childtokens[$name]))
                {
                    $aufzeichnen = true;
                    array_push($rest, $maintokens[$i]);
                    foreach($childtokens[$name] as $item)
                        array_push($rest, $item);
                }

            }
            if(!$aufzeichnen)
                array_push($rest, $maintokens[$i]);

            if($blockend_regexp->match($maintokens[$i]) && $aufzeichnen === true)
            {
                $aufzeichnen = false;
                array_push($rest, $maintokens[$i]);
            }
        }

        $this->_hash = md5($source);

        $cache = LiquidTemplate::getCache();

        if (isset($cache))
        {
            if (($this->_document = $cache->read($this->_hash)) != false && $this->_document->checkIncludes() != true)
            {
            }
            else
            {
                $this->_document = new LiquidDocument($rest, $this->file_system);
                $cache->write($this->_hash, $this->_document);
            }
        }
        else
        {
            $this->_document = new LiquidDocument($rest, $this->file_system);
        }
    }


    /**
     * check for cached includes
     *
     * @return string
     */
    public function checkIncludes()
    {
        $cache = LiquidTemplate::getCache();

        if ($this->_document->checkIncludes() == true)
            return true;

        $source = $this->file_system->read_template_file($this->_templateName);

        if ($cache->exists(md5($source)) && $this->_hash == md5($source))
            return false;

        return true;
    }


    /**
     * Renders the node
     *
     * @param LiquidContext $context
     */
    public function render(&$context)
    {
        $context->push();
        $result = $this->_document->render($context);
        $context->pop();
        return $result;
    }
}