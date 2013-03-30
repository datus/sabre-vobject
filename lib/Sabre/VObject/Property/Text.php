<?php

namespace Sabre\VObject\Property;

use
  Sabre\VObject\Property;

/**
 * This object represents TEXT values.
 *
 * @copyright Copyright (C) 2007-2013 Rooftop Solutions. All rights reserved.
 * @author Evert Pot (http://www.rooftopsolutions.nl/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class Text extends Property {

    /**
     * Current value
     *
     * @var string|array
     */
    protected $value;

    /**
     * Updates the internal value.
     *
     * In case the property has multiple values, you can supply this as an
     * array.
     *
     * @param string|array $value
     * @return void
     */
    public function setValue($value) {

        $this->value = $value;

    }

    /**
     * Returns the current value.
     *
     * If the property has multiple values, this method will return an array.
     *
     * @return string|array
     */
    public function getValue() {

        if (is_array($this->value) && count($this->value) === 1) {
            return $this->value[0];
        }
        return $this->value;

    }

}
