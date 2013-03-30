<?php

namespace Sabre\VObject\Property;
use Sabre\VObject\Component;

class DateTimeTest extends \PHPUnit_Framework_TestCase {

    function testSetDateTime() {

        $tz = new \DateTimeZone('Europe/Amsterdam');
        $dt = new \DateTime('1985-07-04 01:30:00', $tz);
        $dt->setTimeZone($tz);

        $elem = new DateTime('DTSTART');
        $elem->setDateTime($dt);

        $this->assertEquals('19850704T013000', $elem->getValue());
        $this->assertEquals('Europe/Amsterdam', (string)$elem['TZID']);

    }

    function testSetDateTimeFloating() {

        $tz = new \DateTimeZone('Europe/Amsterdam');
        $dt = new \DateTime('1985-07-04 01:30:00', $tz);
        $dt->setTimeZone($tz);

        $elem = new DateTime('DTSTART');
        $elem->setDateTime($dt, true);

        $this->assertEquals('19850704T013000', $elem->getValue());
        $this->assertNull($elem['TZID']);

    }

    function testSetDateTimeUTC() {

        $tz = new \DateTimeZone('GMT');
        $dt = new \DateTime('1985-07-04 01:30:00', $tz);
        $dt->setTimeZone($tz);

        $elem = new DateTime('DTSTART');
        $elem->setDateTime($dt);

        $this->assertEquals('19850704T013000Z', $elem->getValue());
        $this->assertNull($elem['TZID']);

    }

    function testSetDateTimeLOCALTZ() {

        $tz = new \DateTimeZone('Europe/Amsterdam');
        $dt = new \DateTime('1985-07-04 01:30:00', $tz);
        $dt->setTimeZone($tz);

        $elem = new DateTime('DTSTART');
        $elem->setDateTime($dt);

        $this->assertEquals('19850704T013000', $elem->getValue());
        $this->assertEquals('Europe/Amsterdam', (string)$elem['TZID']);

    }

    function testGetDateTimeCached() {

        $tz = new \DateTimeZone('Europe/Amsterdam');
        $dt = new \DateTime('1985-07-04 01:30:00', $tz);
        $dt->setTimeZone($tz);

        $elem = new DateTime('DTSTART');
        $elem->setDateTime($dt);

        $this->assertEquals($elem->getDateTime(), $dt);

    }

    function testGetDateTimeDateNULL() {

        $elem = new DateTime('DTSTART');
        $dt = $elem->getDateTime();

        $this->assertNull($dt);

    }

    function testGetDateTimeDateDATE() {

        $elem = new DateTime('DTSTART','19850704');
        $dt = $elem->getDateTime();

        $this->assertInstanceOf('DateTime', $dt);
        $this->assertEquals('1985-07-04 00:00:00', $dt->format('Y-m-d H:i:s'));

    }


    function testGetDateTimeDateLOCAL() {

        $elem = new DateTime('DTSTART','19850704T013000');
        $dt = $elem->getDateTime();

        $this->assertInstanceOf('DateTime', $dt);
        $this->assertEquals('1985-07-04 01:30:00', $dt->format('Y-m-d H:i:s'));

    }

    function testGetDateTimeDateUTC() {

        $elem = new DateTime('DTSTART','19850704T013000Z');
        $dt = $elem->getDateTime();

        $this->assertInstanceOf('DateTime', $dt);
        $this->assertEquals('1985-07-04 01:30:00', $dt->format('Y-m-d H:i:s'));
        $this->assertEquals('UTC', $dt->getTimeZone()->getName());

    }

    function testGetDateTimeDateLOCALTZ() {

        $elem = new DateTime('DTSTART','19850704T013000');
        $elem['TZID'] = 'Europe/Amsterdam';

        $dt = $elem->getDateTime();

        $this->assertInstanceOf('DateTime', $dt);
        $this->assertEquals('1985-07-04 01:30:00', $dt->format('Y-m-d H:i:s'));
        $this->assertEquals('Europe/Amsterdam', $dt->getTimeZone()->getName());

    }

    /**
     * @expectedException InvalidArgumentException
     */
    function testGetDateTimeDateInvalid() {

        $elem = new DateTime('DTSTART','bla');
        $dt = $elem->getDateTime();

    }

    function testGetDateTimeWeirdTZ() {

        $elem = new DateTime('DTSTART','19850704T013000');
        $elem['TZID'] = '/freeassociation.sourceforge.net/Tzfile/Europe/Amsterdam';


        $event = new Component('VEVENT');
        $event->add($elem);

        $timezone = new Component('VTIMEZONE');
        $timezone->TZID = '/freeassociation.sourceforge.net/Tzfile/Europe/Amsterdam';
        $timezone->{'X-LIC-LOCATION'} = 'Europe/Amsterdam';

        $calendar = new Component('VCALENDAR');
        $calendar->add($event);
        $calendar->add($timezone);

        $dt = $elem->getDateTime();

        $this->assertInstanceOf('DateTime', $dt);
        $this->assertEquals('1985-07-04 01:30:00', $dt->format('Y-m-d H:i:s'));
        $this->assertEquals('Europe/Amsterdam', $dt->getTimeZone()->getName());

    }

    function testGetDateTimeBadTimeZone() {

        $default = date_default_timezone_get();
        date_default_timezone_set('Canada/Eastern');

        $elem = new DateTime('DTSTART','19850704T013000');
        $elem['TZID'] = 'Moon';


        $event = new Component('VEVENT');
        $event->add($elem);

        $timezone = new Component('VTIMEZONE');
        $timezone->TZID = 'Moon';
        $timezone->{'X-LIC-LOCATION'} = 'Moon';

        $calendar = new Component('VCALENDAR');
        $calendar->add($event);
        $calendar->add($timezone);

        $dt = $elem->getDateTime();

        $this->assertInstanceOf('DateTime', $dt);
        $this->assertEquals('1985-07-04 01:30:00', $dt->format('Y-m-d H:i:s'));
        $this->assertEquals('Canada/Eastern', $dt->getTimeZone()->getName());
        date_default_timezone_set($default);

    }
}
