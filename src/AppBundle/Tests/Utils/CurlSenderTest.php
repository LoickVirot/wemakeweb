<?php
/**
 * Created by PhpStorm.
 * User: loick
 * Date: 01/11/17
 * Time: 17:52
 */

namespace AppBundle\Tests\Utils;

use AppBundle\Utils\CurlSender;
use PHPUnit_Framework_TestCase;

class CurlSenderTest extends PHPUnit_Framework_TestCase
{
    public function testCurlGet()
    {
        $curl = new CurlSender("https://api.github.com/repos/LoickVirot/wemakeweb");
        $response = json_decode($curl->send());

        $this->assertEquals('102090706', $response->id);
    }
}
