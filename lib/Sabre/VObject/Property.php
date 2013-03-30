<?php

namespace Sabre\VObject;

/**
 * VObject Property
 *
 * A property in VObject is usually in the form PARAMNAME:paramValue.
 * An example is : SUMMARY:Weekly meeting
 *
 * Properties can also have parameters:
 * SUMMARY;LANG=en:Weekly meeting.
 *
 * Parameters can be accessed using the ArrayAccess interface.
 *
 * @copyright Copyright (C) 2007-2013 Rooftop Solutions. All rights reserved.
 * @author Evert Pot (http://www.rooftopsolutions.nl/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
abstract class Property extends Node {

    /**
     * Propertyname
     *
     * @var string
     */
    public $name;

    /**
     * Group name
     *
     * This may be something like 'HOME' for vcards.
     *
     * @var string
     */
    public $group;

    /**
     * Property parameters
     *
     * @var array
     */
    public $parameters = array();

    /**
     * Default delimiter for encoding properties with multiple values.
     *
     * @var string
     */
    static $delimiter = ';';

    /**
     * Default value types per property.
     */
    static $defaultValueType = array(

        // iCalendar
        "COMPLETED"     => "DATE-TIME",
        "CREATED"       => "DATE-TIME",
        "DTEND"         => "AutoDate",
        "DTSTAMP"       => "DATE-TIME",
        "DTSTART"       => "AutoDate",
        "DUE"           => "DATE-TIME",
        "DURATION"      => "DURATION",
        "EXDATE"        => "DATE-TIME",
        "LAST-MODIFIED" => "DATE-TIME",
        "FREEBUSY"      => "PERIOD",
        "RECURRENCE-ID" => "DATE-TIME",
        "TRIGGER"       => "AutoDate",
    );

    /**
     * Value type -> class mapping
     *
     * @var array
     */
    static $valueTypeMap = array(
        "TEXT"      => "Text",
        "DATE"      => "Date",
        "DATE-TIME" => "DateTime",
        // Not yet implemented
        // BINARY
        // BOOLEAN
        // CAL-ADDRESS
        // DURATION
        // FLOAT
        // INTEGER
        // PERIOD
        // RECUR
        // TIME
        // URI
        // UTC-OFFSET
    );

    /**
     * Creates the new property by name, but in addition will also see if
     * there's a class mapped to the property name.
     *
     * Parameters can be specified with the optional third argument. Parameters
     * must be a key->value map of the parameter name, and value. If the value
     * is specified as an array, it is assumed that multiple parameters with
     * the same name should be added.
     *
     * @param string $name
     * @param string $value
     * @param array $parameters
     * @return Property
     */
    static public function create($name, $value = null, array $parameters = array()) {

        $name = strtoupper($name);
        $shortName = $name;
        $group = null;
        if (strpos($shortName,'.')!==false) {
            list($group, $shortName) = explode('.', $shortName);
        }

        $class = self::getClassForProperty($shortName, $value, $parameters);
        return new $class($name, $value, $parameters);

    }

    /**
     * Creates a new property, based on a raw value as it may be embedded
     * within a vCard or iCalendar object.
     *
     * @param string $name
     * @param string $value
     * @param array $parameters
     * @return Property
     */
    static public function createFromRaw($name, $value, array $parameters = array()) {

        $name = strtoupper($name);
        $shortName = $name;
        $group = null;
        if (strpos($shortName,'.')!==false) {
            list($group, $shortName) = explode('.', $shortName);
        }

        $class = self::getClassForProperty($shortName, $value, $parameters);
        return $class::deserialize($name, $value, $parameters);

    }

    /**
     * Returns the correct class for a property.
     *
     * @param mixed $name Name of the property (e.g.: ADR, without a group).
     * @param mixed $value Property value
     * @param array $parameters
     * @return string
     */
    static public function getClassForProperty($name, $value, array $parameters) {

        $valueParam = null;

        // First, lets see if there's a VALUE="" parameter.
        foreach($parameters as $parameter) {
            if ($parameter->name === "VALUE") {
                $valueParam = strtoupper($parameter->getValue());
                break;
            }
        }

        // Check the default value types for the property name.
        if (!$valueParam) {
            if (isset(self::$defaultValueType[$name])) {
                $valueParam = self::$defaultValueType[$name];
            }
        }

        /**
         * This one is special. Rather than relying on the user to specify the
         * correct thing, we automatically detect these values
         */
        if ($valueParam === 'AutoDate') {
            if ($value[0] === 'P' || $value[0] ==='-' && $value[1] === 'P') {
                // its a DURATION.
                $valueParam = 'DURATION';
            } elseif (strlen($value)===8) {
                // It's a DATE
                $valueParam = 'DATE';
            } else {
                // It must be a DATE-TIME
                $valueParam = 'DATE-TIME';
            }
            $parameters[] = new Parameter('VALUE', $valueParam);

        }

        $class = null;
        if ($valueParam && isset(self::$valueTypeMap[$valueParam])) {
            $class = self::$valueTypeMap[$valueParam];
        } else {
            // Return text by default.
            $class = 'Text';
        }

        return 'Sabre\\VObject\\Property\\' . $class;

    }

    /**
     * Creates a new property object
     *
     * Parameters can be specified with the optional third argument. Parameters
     * must be a key->value map of the parameter name, and value. If the value
     * is specified as an array, it is assumed that multiple parameters with
     * the same name should be added.
     *
     * @param string $name
     * @param string $value
     * @param array $parameters
     */
    public function __construct($name, $value = null, array $parameters = array()) {

        $name = strtoupper($name);
        $group = null;
        if (strpos($name,'.')!==false) {
            list($group, $name) = explode('.', $name);
        }
        $this->name = $name;
        $this->group = $group;
        $this->setValue($value);

        foreach($parameters as $paramName => $paramValues) {

            if (!is_array($paramValues)) {
                $paramValues = array($paramValues);
            }

            foreach($paramValues as $paramValue) {
                if ($paramValue instanceof Parameter) {
                    $this->add($paramValue);
                } else {
                    $this->add($paramName, $paramValue);
                }
            }

        }

    }

    /**
     * Updates the internal value.
     *
     * The value is assumed to be unescaped. To set a compound value (multiple
     * components) pass the value as an array.
     *
     * @param string|array $value
     * @return void
     */
    abstract public function setValue($value);

    /**
     * Returns the current value.
     *
     * If the property has multiple values, this method will return an array.
     *
     * @return string|array
     */
    abstract public function getValue();

    /**
     * Returns the current value as an array, regardless if there were 1 or
     * more values in this property.
     *
     * @return array
     */
    public function getValues() {

        $val = $this->getValue();
        return is_array($val)?$val:array($val);

    }

    /**
     * Turns the object back into a serialized blob.
     *
     * @return string
     */
    public function serialize() {

        $str = $this->name;
        if ($this->group) $str = $this->group . '.' . $this->name;

        foreach($this->parameters as $param) {

            $str.=';' . $param->serialize();

        }

        $str.=':' . $this->serializeValue();

        $out = '';
        while(strlen($str)>0) {
            if (strlen($str)>75) {
                $out.= mb_strcut($str,0,75,'utf-8') . "\r\n";
                $str = ' ' . mb_strcut($str,75,strlen($str),'utf-8');
            } else {
                $out.=$str . "\r\n";
                $str='';
                break;
            }
        }

        return $out;
    }

    /**
     * Serializes the value for use in an iCalendar of vCard blob.
     *
     * @return string
     */
    public function serializeValue() {

        $value = $this->getValue();
        if (!is_array($value)) {
            $value = array($value);
        }

        $escapeMap = array(
            '\\' => '\\\\',
            ';'  => '\;',
            ','  => '\,',
            "\n" => '\n',
        );

        foreach($value as $k=>$subValue) {

            $value[$k] = strtr( $subValue, $escapeMap );

        }

        return implode(static::$delimiter, $value);

    }

    /**
     * Deserializes a string and return a Property object.
     *
     * @param string $str
     * @param string $value
     * @param array $parameters
     * @return Property
     */
    static public function deserialize($name, $value, array $parameters) {

        // Tiny parser. Hope it's not too slow
        $output = array();

        $lastValue = '';

        for($pos = 0; $pos < strlen($value); $pos++) {

            if ($value[$pos]==='\\') {
               // We encountered an escape character, so we need to grab
                // the next character to find out it's meaning.
                switch($value[$pos+1]) {
                    case 'n' :
                    case 'N' :
                        $lastValue.="\n";
                        break;
                    // This captures a \; \, \\ and any other char.
                    default :
                        $lastValue.=$value[$pos+1];
                        break;
                }

                // Increment the position 1 extra.
                $pos++;

            } elseif ($value[$pos]===static::$delimiter) {

                // Multi-value separator
                $output[] = $lastValue;
                $lastValue='';

            } else {

                // Everything else
                $lastValue.=$value[$pos];

            }

        }

        $output[] = $lastValue;

        return new static($name, $output, $parameters);

    }

    /**
     * Adds a new componenten or element
     *
     * You can call this method with the following syntaxes:
     *
     * add(Parameter $element)
     * add(string $name, $value)
     *
     * The first version adds an Parameter
     * The second adds a property as a string.
     *
     * @param mixed $item
     * @param mixed $itemValue
     * @return void
     */
    public function add($item, $itemValue = null) {

        if ($item instanceof Parameter) {
            if (!is_null($itemValue)) {
                throw new \InvalidArgumentException('The second argument must not be specified, when passing a VObject');
            }
            $item->parent = $this;
            $this->parameters[] = $item;
        } elseif(is_scalar($item)) {

            $parameter = new Parameter($item,$itemValue);
            $parameter->parent = $this;
            $this->parameters[] = $parameter;

        } else {

            throw new \InvalidArgumentException('The first argument must either be a Node or a scalar');

        }

    }

    /* ArrayAccess interface {{{ */

    /**
     * Checks if an array element exists
     *
     * @param mixed $name
     * @return bool
     */
    public function offsetExists($name) {

        if (is_int($name)) return parent::offsetExists($name);

        $name = strtoupper($name);

        foreach($this->parameters as $parameter) {
            if ($parameter->name == $name) return true;
        }
        return false;

    }

    /**
     * Returns a parameter, or parameter list.
     *
     * @param string $name
     * @return Node
     */
    public function offsetGet($name) {

        if (is_int($name)) return parent::offsetGet($name);
        $name = strtoupper($name);

        $result = array();
        foreach($this->parameters as $parameter) {
            if ($parameter->name == $name)
                $result[] = $parameter;
        }

        if (count($result)===0) {
            return null;
        } elseif (count($result)===1) {
            return $result[0];
        } else {
            $result[0]->setIterator(new ElementList($result));
            return $result[0];
        }

    }

    /**
     * Creates a new parameter
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function offsetSet($name, $value) {

        if (is_int($name)) parent::offsetSet($name, $value);

        if (is_scalar($value)) {
            if (!is_string($name))
                throw new \InvalidArgumentException('A parameter name must be specified. This means you cannot use the $array[]="string" to add parameters.');

            $this->offsetUnset($name);
            $parameter = new Parameter($name, $value);
            $parameter->parent = $this;
            $this->parameters[] = $parameter;

        } elseif ($value instanceof Parameter) {
            if (!is_null($name))
                throw new \InvalidArgumentException('Don\'t specify a parameter name if you\'re passing a \\Sabre\\VObject\\Parameter. Add using $array[]=$parameterObject.');

            $value->parent = $this;
            $this->parameters[] = $value;
        } else {
            throw new \InvalidArgumentException('You can only add parameters to the property object');
        }

    }

    /**
     * Removes one or more parameters with the specified name
     *
     * @param string $name
     * @return void
     */
    public function offsetUnset($name) {

        if (is_int($name)) parent::offsetUnset($name);
        $name = strtoupper($name);

        foreach($this->parameters as $key=>$parameter) {
            if ($parameter->name == $name) {
                $parameter->parent = null;
                unset($this->parameters[$key]);
            }

        }

    }

    /* }}} */

    /**
     * Called when this object is being cast to a string
     *
     * @return string
     */
    public function __toString() {

        $val = $this->getValue();
        // in order to make a sane representation of arrays, we simply add the
        // delimiters again.
        return is_array($val) ? implode(static::$delimiter, $val) : (string)$val;

    }

    /**
     * This method is automatically called when the object is cloned.
     * Specifically, this will ensure all child elements are also cloned.
     *
     * @return void
     */
    public function __clone() {

        foreach($this->parameters as $key=>$child) {
            $this->parameters[$key] = clone $child;
            $this->parameters[$key]->parent = $this;
        }

    }

    /**
     * Validates the node for correctness.
     *
     * The following options are supported:
     *   - Node::REPAIR - If something is broken, and automatic repair may
     *                    be attempted.
     *
     * An array is returned with warnings.
     *
     * Every item in the array has the following properties:
     *    * level - (number between 1 and 3 with severity information)
     *    * message - (human readable message)
     *    * node - (reference to the offending node)
     *
     * @param int $options
     * @return array
     */
    public function validate($options = 0) {

        $warnings = array();

        // Checking if our value is UTF-8
        if (!StringUtil::isUTF8((string)$this)) {
            $warnings[] = array(
                'level' => 1,
                'message' => 'Property is not valid UTF-8!',
                'node' => $this,
            );
            if ($options & self::REPAIR) {
                $this->value = StringUtil::convertToUTF8($this->value);
            }
        }

        // Checking if the propertyname does not contain any invalid bytes.
        if (!preg_match('/^([A-Z0-9-]+)$/', $this->name)) {
            $warnings[] = array(
                'level' => 1,
                'message' => 'The propertyname: ' . $this->name . ' contains invalid characters. Only A-Z, 0-9 and - are allowed',
                'node' => $this,
            );
            if ($options & self::REPAIR) {
                // Uppercasing and converting underscores to dashes.
                $this->name = strtoupper(
                    str_replace('_', '-', $this->name)
                );
                // Removing every other invalid character
                $this->name = preg_replace('/([^A-Z0-9-])/u', '', $this->name);

            }

        }

        // Validating inner parameters
        foreach($this->parameters as $param) {
            $warnings = array_merge($warnings, $param->validate($options));
        }

        return $warnings;

    }

}
