<?php

namespace Sabre\VObject\Parser;

use Sabre\VObject;
use Sabre\VObject\ParseException;

/**
 * VCALENDAR/VCARD string parser
 *
 * This class reads vobject definitions from a given input string, and returns
 * a full element tree.
 *
 * @copyright Copyright (C) 2007-2013 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class String extends VObject\Parser {

    /**
     * input string buffer we're operating on
     *
     * @var string
     */
    protected $buffer;

    /**
     * current position/offset into the buffer
     *
     * @var int
     */
    protected $pos;

    /**
     * total length of input buffer
     *
     * @var int
     */
    protected $length;

    /**
     * Instanciate a StringParser
     *
     * This Parser receives an input string consisting of several lines.
     * A string offset (`$pos`) is used to traverse.
     *
     * @param string $buffer
     * @param int $options See the OPTIONS constants.
     */
    public function __construct($buffer, $options = 0) {

        $this->buffer = $this->normalizeNewlines($buffer);
        $this->length = strlen($this->buffer);
        $this->pos = 0;
        $this->options = $options;

        $this->bufferLineLogical();
    }

    /**
     * create ParseException along with given $error
     *
     * @param string $str
     * @return ParseException
     */
    protected function createException($error) {

        $lineNr = $this->getLineNr();

        if ($this->buffer === '') {
            $line = '';
        } else {
            $line = $this->line;

            // include marker at our current position in this line
            $line = substr($line, 0, $this->linePos) . '↦' . substr($line, $this->linePos);
        }

        return new ParseException('Invalid VObject: ' . $error . ': Line ' . $lineNr . ' did not follow the icalendar/vcard format:' . var_export($line, true));
    }

    /**
     * read remainder of the current line from the buffer (line break will not be included and advance behind line break)
     *
     * @return string
     * @throws \Exception if the buffer is already drained (end-of-file)
     */
    protected function readLine() {

        if ($this->eof()) {
            throw new \Exception('Buffer drained');
        }
        $pos = strpos($this->buffer, "\n", $this->pos);

        if ($pos === false) {
            $ret = substr($this->buffer, $this->pos);
            $this->pos = $this->length;

            // throw new \Exception('No line ending found');
        } else {
            $ret = (string)substr($this->buffer, $this->pos, ($pos - $this->pos));

            $this->pos = $pos + 1;
        }

        return $ret;
    }

    /**
     * get current position in buffer
     *
     * @return int
     */
    protected function tell() {

        return $this->pos;
    }

    /**
     * set buffer position
     *
     * @param int $pos
     * @throws \Exception
     */
    protected function seek($pos) {

        if ($pos < 0 || $pos > $this->length) {
            throw new \Exception('Invalid offset given');
        }
        $this->pos = $pos;
    }

    /**
     * check if buffer is drained (end-of-file)
     *
     * @return boolean
     */
    protected function eof() {

        return ($this->pos >= $this->length);
    }

    /**
     * get line number for given buffer position
     *
     * @return int
     */
    protected function getLineNr() {

        if ($this->pos === 0) {
            return 1;
        }
        return substr_count($this->buffer, "\n", 0, $this->pos) + 1;
    }

    protected function bufferMatch($regex, &$ret) {

        if (preg_match($regex, $this->buffer, $ret, null, $this->pos)) {
            $this->pos += strlen($ret[0]);

            return true;
        }
        return false;
    }
}
