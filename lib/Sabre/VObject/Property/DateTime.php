<?php

namespace Sabre\VObject\Property;

use
    Sabre\VObject\Property,
    Sabre\VObject\TimeZoneUtil;

/**
 * This object represents DATE-TIME values, as defined in:
 *
 * http://tools.ietf.org/html/rfc5545#section-3.3.5
 *
 * @copyright Copyright (C) 2007-2013 Rooftop Solutions. All rights reserved.
 * @author Evert Pot (http://www.rooftopsolutions.nl/)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class DateTime extends Text {

    /**
     * Sets the DateTime value(s).
     *
     * This may be either specified as a single DateTime object, or as an array
     * of datetime objects.
     *
     * If $isFloating is set to true, timezone information will be stripped,
     * and the date-time will be encoded as a 'floating time'.
     *
     * If the timezone is GMT or UTC, this will be automatically encoded as
     * such.
     *
     * Because multiple-value date-times can only be listed under 1 timezone,
     * only the first timezone definition will be used.
     *
     * @param \DateTime|\DateTime[] $value
     * @param bool $isFloating
     * @return void
     */
    public function setDateTime($value, $isFloating = false) {

        $value = is_array($value)?$value:array($value);
        $strValue = array();

        if ($isFloating) {

            foreach($value as $dt) {
                $strValue[] = $dt->format('Ymd\\THis');
            }

        } else {

            $tz = $value[0]->getTimeZone();
            $tzName = $tz->getName();

            if ($tzName==='UTC' || $tzName==='GMT') {
                foreach($value as $dt) {
                    $dt->setTimeZone($tz);
                    $strValue[] = $dt->format('Ymd\\THis\\Z');
                }
            } else {
                foreach($value as $dt) {
                    $dt->setTimeZone($tz);
                    $strValue[] = $dt->format('Ymd\\THis');
                    $this->offsetSet('TZID', $tzName);
                }
            }

        }

        $this->value = $strValue;

    }


    /**
     * Returns the current value as a Date-Time object.
     *
     * In case there were multiple values, this will be returned as an
     * array.
     *
     * The first reference-argument will be filled with true, if the values
     * were specified as 'floating time'.
     *
     * @param bool $isFloating
     * @return array|DateTime
     */
    public function getDateTime(&$isFloating = null) {

        $date = '(?P<year>[1-2][0-9]{3})(?P<month>[0-1][0-9])(?P<date>[0-3][0-9])';
        $time = '(?P<hour>[0-2][0-9])(?P<minute>[0-5][0-9])(?P<second>[0-5][0-9])';
        $regex = "/^$date(T$time(?P<isutc>Z)?)$/";

        // Finding the timezone.
        $tzid = $this['TZID'];
        $tz = null;

        if ($tzid) {
            // To look up the timezone, we must first find the VCALENDAR component.
            $root = $this;
            while($root->parent) {
                $root = $root->parent;
            }
            if ($root->name === 'VCALENDAR') {
                $tz = TimeZoneUtil::getTimeZone((string)$tzid, $root);
            } else {
                $tz = TimeZoneUtil::getTimeZone((string)$tzid);
            }
        }

        $value = $this->getValues();

        $output = array();

        foreach($value as $val) {

            if (!preg_match($regex, $val, $matches)) {
                throw new \InvalidArgumentException($val . ' is not a valid DateTime string');
            }

            $dateStr =
                $matches['year'] .'-' .
                $matches['month'] . '-' .
                $matches['date'] . ' ' .
                $matches['hour'] . ':' .
                $matches['minute'] . ':' .
                $matches['second'];

            if (isset($matches['isutc'])) {
                $dt = new \DateTime($dateStr, new \DateTimeZone('UTC'));
                $dt->setTimeZone(new \DateTimeZone('UTC'));
            } elseif ($tz) {
                $dt = new \DateTime($dateStr, $tz);
                $dt->setTimeZone($tz);
            } else {
                $dt = new \DateTime($dateStr);
                $isFloating = true;
            }

            $output[] = $dt;

        }

        if (count($output)===1) {
            return $output[0];
        } else {
            return $output;
        }

    }

}
