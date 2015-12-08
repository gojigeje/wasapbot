<?php
require_once __DIR__."/../../util/ByteUtil.php";

class ByteUtilTest extends PHPUnit_Framework_TestCase{
    public function testSplit(){
        $okm = pack("H*",'02a9aa6c7dbd64f9d3aa92f92a277bf54609dadf0b00828acfc61e3c724b84a7bfbe5efb603030526742e3ee89c7024e884e'.
                 '440f1ff376bb2317b2d64deb7c8322f4c5015d9d895849411ba1d793a827');

        $data = "";
        for($i=0;$i<80;$i++) $data.=chr($i);
        $a_data = "";
        for($i=0;$i<32;$i++) $a_data .= chr($i);
        $b_data = "";
        for($i=32;$i<64;$i++) $b_data .= chr($i);
        $c_data = "";
        for($i=64;$i<80;$i++) $c_data .= chr($i);

        $result = ByteUtil::split($data, 32, 32, 16);
        $this->assertEquals($result[0], $a_data);
        $this->assertEquals($result[1], $b_data);
        $this->assertEquals($result[2], $c_data);
    }
}
?>