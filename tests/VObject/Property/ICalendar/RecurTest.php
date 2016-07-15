<?php

namespace Sabre\VObject\Property\ICalendar;

use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Node;
use Sabre\VObject\Reader;

class RecurTest extends \PHPUnit_Framework_TestCase {

    use \Sabre\VObject\PHPUnitAssertions;

    function testParts() {

        $vcal = new VCalendar();
        $recur = $vcal->add('RRULE', 'FREQ=Daily');

        $this->assertInstanceOf('Sabre\VObject\Property\ICalendar\Recur', $recur);

        $this->assertEquals(['FREQ' => 'DAILY'], $recur->getParts());
        $recur->setParts(['freq'    => 'MONTHLY']);

        $this->assertEquals(['FREQ' => 'MONTHLY'], $recur->getParts());

    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testSetValueBadVal() {

        $vcal = new VCalendar();
        $recur = $vcal->add('RRULE', 'FREQ=Daily');
        $recur->setValue(new \Exception());

    }

    function testSetValueWithCount() {
        $vcal = new VCalendar();
        $recur = $vcal->add('RRULE', 'FREQ=Daily');
        $recur->setValue(['COUNT' => 3]);
        $this->assertEquals($recur->getParts()['COUNT'], 3);
    }

    function testGetJSONWithCount() {
        $input = 'BEGIN:VCALENDAR
BEGIN:VEVENT
UID:908d53c0-e1a3-4883-b69f-530954d6bd62
TRANSP:OPAQUE
DTSTART;TZID=Europe/Berlin:20160301T150000
DTEND;TZID=Europe/Berlin:20160301T170000
SUMMARY:test
RRULE:FREQ=DAILY;COUNT=3
ORGANIZER;CN=robert pipo:mailto:robert@example.org
END:VEVENT
END:VCALENDAR
';

        $vcal = Reader::read($input);
        $rrule = $vcal->VEVENT->RRULE;
        $count = $rrule->getJsonValue()[0]['count'];
        $this->assertTrue(is_int($count));
        $this->assertEquals(3, $count);
    }

    function testSetSubParts() {

        $vcal = new VCalendar();
        $recur = $vcal->add('RRULE', ['FREQ' => 'DAILY', 'BYDAY' => 'mo,tu', 'BYMONTH' => [0, 1]]);

        $this->assertEquals([
            'FREQ'    => 'DAILY',
            'BYDAY'   => ['MO', 'TU'],
            'BYMONTH' => [0, 1],
        ], $recur->getParts());

    }

    function testGetJSONWithUntil() {
        $input = 'BEGIN:VCALENDAR
BEGIN:VEVENT
UID:908d53c0-e1a3-4883-b69f-530954d6bd62
TRANSP:OPAQUE
DTSTART;TZID=Europe/Berlin:20160301T150000
DTEND;TZID=Europe/Berlin:20160301T170000
SUMMARY:test
RRULE:FREQ=DAILY;UNTIL=20160305T230000Z
ORGANIZER;CN=robert pipo:mailto:robert@example.org
END:VEVENT
END:VCALENDAR
';

        $vcal = Reader::read($input);
        $rrule = $vcal->VEVENT->RRULE;
        $untilJsonString = $rrule->getJsonValue()[0]['until'];
        $this->assertEquals('2016-03-05T23:00:00Z', $untilJsonString);
    }


    function testValidateStripEmpties() {

        $input = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:foobar
BEGIN:VEVENT
UID:908d53c0-e1a3-4883-b69f-530954d6bd62
TRANSP:OPAQUE
DTSTART;TZID=Europe/Berlin:20160301T150000
DTEND;TZID=Europe/Berlin:20160301T170000
SUMMARY:test
RRULE:FREQ=DAILY;BYMONTH=;UNTIL=20160305T230000Z
ORGANIZER;CN=robert pipo:mailto:robert@example.org
DTSTAMP:20160312T183800Z
END:VEVENT
END:VCALENDAR
';

        $vcal = Reader::read($input);
        $this->assertEquals(
            1,
            count($vcal->validate())
        );
        $this->assertEquals(
            1,
            count($vcal->validate($vcal::REPAIR))
        );

        $expected = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:foobar
BEGIN:VEVENT
UID:908d53c0-e1a3-4883-b69f-530954d6bd62
TRANSP:OPAQUE
DTSTART;TZID=Europe/Berlin:20160301T150000
DTEND;TZID=Europe/Berlin:20160301T170000
SUMMARY:test
RRULE:FREQ=DAILY;UNTIL=20160305T230000Z
ORGANIZER;CN=robert pipo:mailto:robert@example.org
DTSTAMP:20160312T183800Z
END:VEVENT
END:VCALENDAR
';

        $this->assertVObjectEqualsVObject(
            $expected,
            $vcal
        );

    }

    function testValidateStripNoFreq() {

        $input = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:foobar
BEGIN:VEVENT
UID:908d53c0-e1a3-4883-b69f-530954d6bd62
TRANSP:OPAQUE
DTSTART;TZID=Europe/Berlin:20160301T150000
DTEND;TZID=Europe/Berlin:20160301T170000
SUMMARY:test
RRULE:UNTIL=20160305T230000Z
ORGANIZER;CN=robert pipo:mailto:robert@example.org
DTSTAMP:20160312T183800Z
END:VEVENT
END:VCALENDAR
';

        $vcal = Reader::read($input);
        $this->assertEquals(
            1,
            count($vcal->validate())
        );
        $this->assertEquals(
            1,
            count($vcal->validate($vcal::REPAIR))
        );

        $expected = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:foobar
BEGIN:VEVENT
UID:908d53c0-e1a3-4883-b69f-530954d6bd62
TRANSP:OPAQUE
DTSTART;TZID=Europe/Berlin:20160301T150000
DTEND;TZID=Europe/Berlin:20160301T170000
SUMMARY:test
ORGANIZER;CN=robert pipo:mailto:robert@example.org
DTSTAMP:20160312T183800Z
END:VEVENT
END:VCALENDAR
';

        $this->assertVObjectEqualsVObject(
            $expected,
            $vcal
        );

    }

    function testValidateInvalidByMonthRruleWithRepair() {

        $calendar = new VCalendar();
        $property = $calendar->createProperty('RRULE', 'FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=0');
        $result = $property->validate(Node::REPAIR);

        $this->assertCount(1, $result);
        $this->assertEquals('BYMONTH in RRULE must have value(s) between 1 and 12!', $result[0]['message']);
        $this->assertEquals(1, $result[0]['level']);
        // BYMONTH will be repaired from 0 to 1
        $this->assertEquals('FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=1', $property->getValue());

    }

    function testValidateInvalidByMonthRruleWithoutRepair() {

        $calendar = new VCalendar();
        $property = $calendar->createProperty('RRULE', 'FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=0');
        $result = $property->validate();

        $this->assertCount(1, $result);
        $this->assertEquals('BYMONTH in RRULE must have value(s) between 1 and 12!', $result[0]['message']);
        $this->assertEquals(3, $result[0]['level']);
        $this->assertEquals('FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=0', $property->getValue());

    }

    function testValidateInvalidByMonthRruleWithRepair2() {

        $calendar = new VCalendar();
        $property = $calendar->createProperty('RRULE', 'FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=bla');
        $result = $property->validate(Node::REPAIR);

        $this->assertCount(1, $result);
        $this->assertEquals('BYMONTH in RRULE must have value(s) between 1 and 12!', $result[0]['message']);
        $this->assertEquals(1, $result[0]['level']);
        // repair means remove BYMONTH
        $this->assertEquals('FREQ=YEARLY;COUNT=6;BYMONTHDAY=24', $property->getValue());

    }

    function testValidateInvalidByMonthRruleWithoutRepair2() {

        $calendar = new VCalendar();
        $property = $calendar->createProperty('RRULE', 'FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=bla');
        $result = $property->validate();

        $this->assertCount(1, $result);
        $this->assertEquals('BYMONTH in RRULE must have value(s) between 1 and 12!', $result[0]['message']);
        $this->assertEquals(3, $result[0]['level']);
        // Without repair the invalid BYMONTH is still there, but the value is changed to uppercase
        $this->assertEquals('FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=BLA', $property->getValue());

    }

    function testValidateInvalidByMonthRruleValue14WithRepair() {

        $calendar = new VCalendar();
        $property = $calendar->createProperty('RRULE', 'FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=14');
        $result = $property->validate(Node::REPAIR);

        $this->assertCount(1, $result);
        $this->assertEquals('BYMONTH in RRULE must have value(s) between 1 and 12!', $result[0]['message']);
        $this->assertEquals(1, $result[0]['level']);
        // BYMONTH will be repaired from 14 to 12
        $this->assertEquals('FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=12', $property->getValue());

    }

    function testValidateInvalidByMonthRruleMultipleWithRepair() {

        $calendar = new VCalendar();
        $property = $calendar->createProperty('RRULE', 'FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=0,1,2,3,4,14');
        $result = $property->validate(Node::REPAIR);

        $this->assertCount(2, $result);
        $this->assertEquals('BYMONTH in RRULE must have value(s) between 1 and 12!', $result[0]['message']);
        $this->assertEquals(1, $result[0]['level']);
        $this->assertEquals('BYMONTH in RRULE must have value(s) between 1 and 12!', $result[1]['message']);
        $this->assertEquals(1, $result[1]['level']);
        // repair 14->12, 0->1, and remove duplicates
        $this->assertEquals('FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=1,2,3,4,12', $property->getValue());

    }

    function testValidateOneOfManyInvalidByMonthRruleWithRepair() {

        $calendar = new VCalendar();
        $property = $calendar->createProperty('RRULE', 'FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=bla,3,foo');
        $result = $property->validate(Node::REPAIR);

        $this->assertCount(2, $result);
        $this->assertEquals('BYMONTH in RRULE must have value(s) between 1 and 12!', $result[0]['message']);
        $this->assertEquals(1, $result[0]['level']);
        $this->assertEquals('BYMONTH in RRULE must have value(s) between 1 and 12!', $result[1]['message']);
        $this->assertEquals(1, $result[1]['level']);
        $this->assertEquals('FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=3', $property->getValue());

    }

    function testValidateValidByMonthRrule() {

        $calendar = new VCalendar();
        $property = $calendar->createProperty('RRULE', 'FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=2,3');
        $result = $property->validate(Node::REPAIR);

        // There should be 0 warnings and the value should be unchanged
        $this->assertEmpty($result);
        $this->assertEquals('FREQ=YEARLY;COUNT=6;BYMONTHDAY=24;BYMONTH=2,3', $property->getValue());

    }

}
