<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Utils\BigNumber;

class BigNumberTest extends TestCase
{
    private function check($expect, $actual)
    {
        if ($expect !== $actual) {
            echo "Expect: $expect, actual: $actual";
        }
        $this->assertTrue($expect === $actual);
    }

    /**
     * test big number
     * @group bigNumber
     *
     * @return void
     */
    public function testCreateNumber1()
    {
        $this->check('1', (string) new BigNumber(1));
        $this->check('1', (string) new BigNumber('1'));
        $this->assertTrue('0' === (string) new BigNumber(0));
        $this->assertTrue('0' === (string) new BigNumber('0'));
        $this->assertTrue('-1' === (string) new BigNumber(-1));
        $this->assertTrue('-1' === (string) new BigNumber('-1'));
        $this->assertTrue('0.0001' === (string) new BigNumber(1e-4));
        $this->assertTrue('1000' === (string) new BigNumber(1e3));
        $this->assertTrue('123.456' === (string) new BigNumber('123.456'));
        $this->assertTrue('123.456' === (string) new BigNumber(123.456));
        $this->assertTrue('0.00000024' === (string) new BigNumber('2.4e-7'));
        $this->assertTrue('0.00000024' === (string) new BigNumber(2.4e-7));
    }

    /**
     * test big number
     * @group bigNumber
     * @group roundBigNumber
     *
     * @return void
     */
    public function testRound()
    {
        $number = new BigNumber('1.00011122233');
        $this->assertTrue('1.0001112223' === (string) $number);

        $number = new BigNumber('1.00011122235');
        $this->assertTrue('1.0001112224' === (string) $number);

        $number = new BigNumber('1.00011122233', BigNumber::ROUND_MODE_CEIL);
        $this->check('1.0001112224', (string) $number);

        $number = new BigNumber('3114500', BigNumber::ROUND_MODE_CEIL);
        $this->check('3114500', (string) $number);

        $number = new BigNumber('1.00011122237', BigNumber::ROUND_MODE_CEIL);
        $this->check('1.0001112224', (string) $number);

        $number = new BigNumber('1.00011122233', BigNumber::ROUND_MODE_FLOOR);
        $this->check('1.0001112223', (string) $number);

        $number = new BigNumber('1.00011122236', BigNumber::ROUND_MODE_FLOOR);
        $this->check('1.0001112223', (string) $number);
    }

    /**
     * test big number
     * @group bigNumber
     *
     * @return void
     */
    public function testAdd1()
    {
        $number = new BigNumber('1');
        $number = $number->add('2');
        $this->assertTrue('3' === (string) $number);
    }

    /**
     * test big number
     * @group bigNumber
     *
     * @return void
     */
    public function testAdd2()
    {
        $number = new BigNumber('1.23');
        $number = $number->add('2.34');
        $this->assertTrue('3.57' === (string) $number);
    }

    /**
     * test big number
     * @group bigNumber
     *
     * @return void
     */
    public function testAdd3()
    {
        $number = new BigNumber('1.23346');
        $number = $number->add('2.34654');
        $this->assertTrue('3.58' === (string) $number);
    }

    /**
     * test big number
     * @group bigNumber
     *
     * @return void
     */
    public function testSub1()
    {
        $number = new BigNumber('1.23346');
        $number = $number->sub('2.34654');
        $this->assertEquals('-1.11308', (string) $number);
    }

    /**
     * test big number
     * @group bigNumber
     *
     * @return void
     */
    public function testMul1()
    {
        $number = new BigNumber('1.23346');
        $number = $number->mul('2.34654');
        $this->assertTrue('2.8943632284' === (string) $number);
        $this->assertTrue('0.01' === BigNumber::new(-1)->mul('-0.01')->toString());
    }

    /**
     * test big number
     * @group bigNumber
     *
     * @return void
     */
    public function testDiv1()
    {
        $number = new BigNumber('1.23346');
        $number = $number->div('2.34654');
        $this->assertEquals('0.5256505323', (string) $number);
    }

    /**
     * test big number
     * @group bigNumber
     *
     * @return void
     */
    public function testCompare()
    {
        $number = new BigNumber('1.23346');
        $this->assertEquals(1, $number->comp('1.03346'));
        $this->assertEquals(-1, $number->comp('1.33346'));
        $this->assertEquals(0, $number->comp('1.23346'));
    }

    /**
     * test big number
     * @group bigNumber1
     *
     * @return void
     */
    public function testToString()
    {
        $value = BigNumber::new(-1)->mul('-0.01')->toString();
        $this->assertEquals('string', gettype($value));
        $this->assertEquals('0.01', $value);
    }
}
