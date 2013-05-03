<?php

namespace Sabre\VObject;

use Sabre\VObject\ParseException;

/**
 * abstract base class for VCALENDAR/VCARD parser
 *
 * This class provides an interface (API) to reading vobject files and return
 * a full element tree.
 *
 * @copyright Copyright (C) 2007-2013 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
abstract class Parser {

    /**
     * If this option is passed to the reader, it will be less strict about the
     * validity of the lines.
     *
     * Currently using this option just means, that it will accept underscores
     * in property names.
     */
    const OPTION_FORGIVING = 1;

    /**
     * If this option is turned on, any lines we cannot parse will be ignored
     * by the reader.
     *
     * @TODO: Consider which methods should return null if an invalid line was
     *  encountered!
     */
    const OPTION_IGNORE_INVALID_LINES = 2;

    /**
     * See the OPTIONS constants.
     *
     * @var int
     */
    protected $options = 0;

    protected $line = '';
    protected $linePos = 0;

    /**
     * reads either a whole Component (BEGIN:{NAME} to END:{NAME}) or a single Property
     *
     * @return Component|Property
     * @throws ParseException
     * @todo consider whether this should really be public
     */
    public function readComponentOrProperty() {

        $property = $this->readProperty();

        if ($property->name === 'BEGIN') {
            return $this->readIntoComponent(Component::create($property->value));
        }
        return $property;
    }

    /**
     * reads a whole Componenet (BEGIN:{NAME} to END:{NAME})
     *
     * @return Component
     * @throws ParseException
     */
    public function readComponent() {

        $obj = $this->readComponentOrProperty();

        if ($obj instanceof Property) {
            throw $this->createException('Expected component begin "BEGIN:{NAME}", but god "' . $obj->serialize() . '"');
        }
        return $obj;
    }

    /**
     * reads a single Property alongs with its name, Parameters and value
     *
     * @return Property
     * @throws ParseException
     */
    public function readProperty() {

        // Properties
        //$result = preg_match('/(?P<name>[A-Z0-9-]+)(?:;(?P<parameters>^(?<!:):))(.*)$/',$line,$matches);

        if ($this->options & self::OPTION_FORGIVING) {
            $token = 'A-Z0-9\-\._';
        } else {
            $token = 'A-Z0-9\-\.';
        }

        if (!$this->tokens($token, $propertyName)) {
            throw $this->createException('Expected property name');
        }

        $isQuotedPrintable = false;
        $propertyParams = array();
        while ($this->literal(';')) {
            $parameter = $this->readParameter();
            $propertyParams []= $parameter;

            if ($parameter->name === 'ENCODING' && strtoupper($parameter->value) === 'QUOTED-PRINTABLE') {
                $isQuotedPrintable = true;
            }
        }

        // match colon + remainder of line + perform unfolding to concat next lines
        if (!$this->literal(':')) {
            throw $this->createException('Expected colon and property value');
        }

        if ($isQuotedPrintable) {
            $this->remainderRaw($propertyValue);
            // quoted-printable soft line break at line end => try to read next lines
            while (substr($propertyValue, -1) === '=') {
                // TODO: use single match instead of looping (performance)
                if ($this->bufferMatch('/(.*)(?:\n)?/A', $match)) {
                    $propertyValue .= "\n" . $match[1];
                } else {
                    throw $this->createException('');
                }
            }

            $propertyValue = preg_replace('/=\n[ \t]?/', '', $propertyValue);
            $propertyValue = $this->unfold($propertyValue);
        } else {
            $this->remainder($propertyValue);

            // unescape backslash-escaped values
            $propertyValue = preg_replace_callback('#(\\\\(\\\\|N|n))#',function($matches) {
                if ($matches[2]==='n' || $matches[2]==='N') {
                    return "\n";
                } else {
                    return $matches[2];
                }
            }, $propertyValue);
        }

        // TODO: whole logic line should now have been consumed, pre-process next line
        $this->bufferLineLogical();

        $property = Property::create($propertyName, $propertyValue);
        foreach ($propertyParams as $param) {
            $property->add($param);
        }
        return $property;
    }

    /**
     * Reads a single property parameter from buffer (and advance buffer behind this parameter)
     *
     * @return Parameter
     * @throws ParseException
     */
    public function readParameter() {

        $token = 'A-Z0-9\-';

        if (!$this->tokens($token, $paramName)) {
            throw $this->createException('Invalid parameter name');
        }
        $paramValue = null;

        // optionally match equal sign
        if ($this->literal('=')) {
            // parameter value is enclosed in quotes
            if ($this->literal('"')) {
                // TODO: escaped quotes?
                if (!$this->until('"', $paramValue)) {
                    throw $this->createException('Missing parameter quote end delimiter');
                }
            } else {
                $paramValue = '';

                // match any number of characters as parameter value (until the first colon and semicolon)
                while ($this->tokens('^\:\;', $part)) {
                    $paramValue .= $part;

                    // the last character was a backslash, so add trailing colon or semicolon and continue reading
                    // TODO: consider: name=value\\:
                    if (substr($part, -1) === '\\') {
                        $this->char($next);
                        $paramValue .= $next;
                        // continue
                    } else {
                        break;
                    }
                }
            }

            $paramValue = preg_replace_callback('#(\\\\(\\\\|N|n|;|,))#',function($matches) {
                if ($matches[2]==='n' || $matches[2]==='N') {
                    return "\n";
                } else {
                    return $matches[2];
                }
            }, $paramValue);
        }
        return new Parameter($paramName, $paramValue);
    }

    // non-public helper methods:

    /**
     * reads any number of sub-Components and Properties into the given Component
     *
     * @param Component $component
     * @return Component
     * @throws ParseException
     */
    protected function readIntoComponent($component) {

        do {
            try{
                $parsed = $this->readComponentOrProperty();
            }
            catch(ParseException $error) {
                if ($this->options & self::OPTION_IGNORE_INVALID_LINES) {
                    continue;
                }
                throw $error;
            }

            // Checking component name of the 'END:' line.
            if ($parsed instanceof Property && $parsed->name === 'END') {
                if ($parsed->value !== $component->name) {
                    throw $this->createException('Expected "END:' . $component->name . '", but got "END:' . $parsed->value . '"');
                }
                break;
            }

            $component->add($parsed);

            if ($this->line === '')
                throw new ParseException('Invalid VObject. Document ended prematurely. Expected: "END:' . $component->name.'"');

        } while(true);

        return $component;
    }

    /**
     * read next unfolded, logical line into line buffer
     */
    protected function bufferLineLogical() {

        if (!$this->bufferMatch('/(.*(?:\n[ \t].+)*)\n/A', $match)) {
            $this->line = '';
            return;
        }
        $this->line = $this->unfold($match[1]);
        $this->linePos = 0;
    }

    protected function remainder(&$line) {

        if (isset($this->line[$this->linePos])) {
            $line = substr($this->line, $this->linePos);
            $this->linePos += strlen($line);
            return true;
        }
        return false;
    }

    protected function remainderRaw(&$line) {

        throw $this->createException('TO BE DONE');
    }

    abstract protected function bufferMatch($regex, &$match);

    /**
     * normalize all line breaks (CRLF and mac CR) as unix LF only
     *
     * @param string $data
     * @return string
     */
    protected function normalizeNewlines($data) {

        // TODO: skip empty lines?
        return rtrim(str_replace(array("\r\n", "\r"),"\n", $data), "\n") . "\n";
    }

    /**
     * read a single character from the buffer (and advance behind char)
     *
     * @param string $char
     * @return boolean
     */
    protected function char(&$char) {

//         if ($this->match('/./A', $out)) {
//             $char = $out[0];
//             return true;
//         }
//         return false;

        if (isset($this->line[$this->linePos])) {
            $char = $this->line[$this->linePos];
            $this->linePos += 1;
            return true;
        }

        return false;
    }

    /**
     * create ParseException along with given $error
     *
     * @param string $str
     * @return ParseException
     */
    protected function createException($error) {

        $lineNr = $this->getLineNr();
        $line = $this->line;

        // this line failed, so make sure to load next one into buffer
        $this->bufferLineLogical();

        // include marker at our current position in this line
        $line = substr($line, 0, $this->linePos) . '↦' . substr($line, $this->linePos);

        return new ParseException('Invalid VObject: ' . $error . ': Line ' . $lineNr . ' did not follow the icalendar/vcard format:' . var_export($line, true));
    }

    /**
     * get line number for given buffer position
     *
     * @return int
     */
    abstract protected function getLineNr();

    /**
     * try to match given regex on current buffer position (and advance behind match)
     *
     * @param string $regex
     * @param array  $ret
     * @return boolean
     * @uses preg_match()
     */
    protected function match($regex, &$ret) {

        if (preg_match($regex, $this->line, $ret, null, $this->linePos)) {
            $this->linePos += strlen($ret[0]);

            return true;
        }
        return false;
    }

    /**
     * match any number of the given tokens (and advance behind tokens)
     *
     * @param string $token
     * @param string $out
     * @return boolean
     */
    protected function tokens($token, &$out) {

        if ($this->match('/([' . $token . ']+)/Ai', $match)) {
            $out = $match[1];
            return true;
        }
        return false;
    }

    /**
     * match given literal string in buffer (and advance behind literal)
     *
     * @param string $expect
     * @return boolean
     */
    protected function literal($expect) {

//         return $this->match('/' . preg_quote($expect) . '/A', $ignore);

        if (isset($this->line[$this->linePos]) && $this->line[$this->linePos] === $expect) {
            // literal character is the first character in buffer
            $this->linePos += 1;
            return true;
        }
        return false;


    }

    /**
     * read from buffer until $end is found ($end will not be returned and advance behind end)
     *
     * @param string $end
     * @param string $out
     * @return boolean
     */
    protected function until($end, &$out) {

//         if ($this->match('/(.*)' . preg_quote($end) . '/A', $match)) {
//             $out = $match[1];
//             return true;
//         }
//         return false;

        $pos = strpos($this->line, $end, $this->linePos);
        if ($pos === false) {
            return false;
        }

        $out = substr($this->line, $this->linePos, ($pos - $this->linePos));
        $this->linePos = $pos + 1;

        return true;
    }

    protected function unfold($str) {

        return str_replace(array("\n ", "\n\t"), '', $str);
    }
}
