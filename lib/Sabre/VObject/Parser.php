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

    protected function normalizeNewlines($data) {

        // TODO: skip empty lines?
        return str_replace(array("\r\n", "\r"),"\n", $data);
    }

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
     * reads any number of sub-Components and Properties into the given Component
     *
     * @param Component $component
     * @return Component
     * @throws ParseException
     */
    protected function readIntoComponent($component) {

        do {
            $pos = $this->tell();

            try{
                $parsed = $this->readComponentOrProperty();
            }
            catch(ParseException $error) {
                if ($this->options & self::OPTION_IGNORE_INVALID_LINES) {
                    $this->seek($pos);

                    $this->readLine();
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

            if ($this->eof())
                throw new ParseException('Invalid VObject. Document ended prematurely. Expected: "END:' . $component->name.'"');

        } while(true);

        return $component;
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

        if (!$this->tokens($token, $match)) {
            throw $this->createException('Expected property name');
        }
        $propertyName = strtoupper($match);

        $isQuotedPrintable = false;
        $propertyParams = array();
        while ($this->literal(';')) {
            $parameter = $this->readParameter();
            $propertyParams []= $parameter;

            if ($parameter->name === 'ENCODING' && strtoupper($parameter->value) === 'QUOTED-PRINTABLE') {
                $isQuotedPrintable = true;
            }
        }

        if (!$this->literal(':')) {
            throw $this->createException('Missing colon after property parameters');
        }

        $propertyValue = $this->readLine();

        if ($isQuotedPrintable) {
            // peek at next lines if this is a quoted-printable encoding
            while (substr($propertyValue, -1) === '=') {
                $line = $this->readLine();

                if (true) {
                    $line = ltrim($line);
                }

                // next line is unparsable => append to current line
                $propertyValue = substr($propertyValue, 0, -1) . $line;
            }
        } else {
            // peek at following lines to check for line-folding
            while (true) {
                $pos = $this->tell();
                try {
                    $line = $this->readLine();
                }
                catch (\Exception $e) {
                    break;
                }

                if ($line[0]===" " || $line[0]==="\t") {
                    $propertyValue .= substr($line, 1);
                } else {
                    // reset position
                    $this->seek($pos);
                    break;
                }
            }

            // unescape backslash-escaped values
            $propertyValue = preg_replace_callback('#(\\\\(\\\\|N|n))#',function($matches) {
                if ($matches[2]==='n' || $matches[2]==='N') {
                    return "\n";
                } else {
                    return $matches[2];
                }
            }, $propertyValue);
        }

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

        if ($this->literal('=')) {
            if ($this->literal('"')) {
                // TODO: escaped quotes?
                if (!$this->until('"', $paramValue)) {
                    throw $this->createException('Missing parameter quote end delimiter');
                }
            } else {
                $paramValue = '';
                $pos = $this->tell();
                $escaped = false;

                // read any number of characters
                while ($this->char($char)) {
                    if ($escaped) {
                        $escaped = false;
                    } else {
                        // those must not be included in parameter value => seek to previous valid position and stop
                        if ($char === ';' || $char === ':') {
                            $this->seek($pos);
                            break;
                        } else if ($char === '\\') {
                            // the following char is escaped
                            $escaped = true;
                        }
                    }

                    $paramValue .= $char;
                    $pos = $this->tell();
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

    /**
     * match any number of the given tokens (and advance behind tokens)
     *
     * @param string $token
     * @param string $out
     * @return boolean
     */
    protected function tokens($token, &$out) {

        if ($this->match('/^([' . $token . ']+)/i', $match)) {
            $out = $match[1];
            return true;
        }
        return false;
    }

    /**
     * read a single character from the buffer (and advance behind char)
     *
     * @param string $char
     * @return boolean
     */
    abstract protected function char(&$char);

    /**
     * create ParseException along with given $error
     *
     * @param string $str
     * @return ParseException
     */
    abstract protected function createException($error);

    /**
     * read remainder of the current line from the buffer (line break will not be included and advance behind line break)
     *
     * @return string
     * @throws \Exception if the buffer is already drained (end-of-file)
     */
    abstract protected function readLine();

    /**
     * get current position in buffer
     *
     * @return int
     */
    abstract protected function tell();

    /**
     * set buffer position
     *
     * @param int $pos
     * @throws \Exception
     */
    abstract protected function seek($pos);

    /**
     * check if buffer is drained (end-of-file)
     *
     * @return boolean
     */
    abstract protected function eof();

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
    abstract protected function match($regex, &$ret);

    /**
     * match given literal string in buffer (and advance behind literal)
     *
     * @param string $expect
     * @return boolean
     */
    protected function literal($expect) {

        return $this->match('/^'.preg_quote($expect).'/', $ignore);

//         $l = strlen($expect);
//         if (substr($this->buffer, $this->pos, $l) === (string)$expect) {
//             $this->pos += $l;
//             return true;
//         }
//         return false;
    }

    /**
     * read from buffer until $end is found ($end will not be returned and advance behind end)
     *
     * @param string $end
     * @param string $out
     * @return boolean
     */
    protected function until($end, &$out) {

        if ($this->match('/^(.*?)' . preg_quote($end) . '/', $match)) {
            $out = $match[1];
            return true;
        }
        return false;

//         $pos = strpos($this->buffer, $end, $this->pos);
//         if ($pos === false) {
//             return false;
//         }

//         $out = substr($this->buffer, $this->pos, ($pos - $this->pos));
//         $this->pos = $pos + strlen($end);

//         return true;
    }
}
