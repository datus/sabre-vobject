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

    protected $unfoldings = array();

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
        $this->pos = 0;
        $this->options = $options;

        //$this->buffer = $this->unfold($this->buffer);
        // perform unfolding, but remember all unfolding positions in order to
        // be able to reconstruct original (folded) line for quoted printable
        $this->buffer = str_replace("\n\t", "\n ", $this->buffer);
        $pos = 0;
        do {
            $pos = strpos($this->buffer, "\n ", $pos);
            if ($pos === false) {
                break;
            }

            $this->unfoldings []= $pos;
            $this->buffer = substr($this->buffer, 0, $pos) . substr($this->buffer, $pos + 2);
        } while (true);


        $this->bufferLineLogical();
    }

    /**
     * read next unfolded, logical line into line buffer
     */
    protected function bufferLineLogical() {

        $pos = strpos($this->buffer, "\n", $this->pos);
        if ($pos === false) {
            $this->line = '';
        } else {
            $this->line = substr($this->buffer, $this->pos, ($pos - $this->pos));
            $this->linePos = 0;
            $this->pos = $pos + 1;
        }
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
