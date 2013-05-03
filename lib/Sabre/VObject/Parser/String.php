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
