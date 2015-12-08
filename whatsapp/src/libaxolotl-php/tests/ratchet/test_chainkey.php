<?php

require_once __DIR__."/../../ratchet/ChainKey.php";
require_once __DIR__."/../../kdf/HKDF.php";

class ChainKeyTest extends PHPUnit_Framework_TestCase{
    public function test_chainKeyDerivationV2()
    {
        $seed         = "\x8a\xb7\x2d\x6f\x4c\xc5\xac\x0d\x38\x7e\xaf\x46\x33\x78\xdd\xb2\x8e\xdd\x07\x38\x5b\x1c\xb0\x12\x50\xc7\x15\x98\x2e\x7a\xd4\x8f";

        $messageKey   = "\x02\xa9\xaa\x6c\x7d\xbd\x64\xf9\xd3\xaa\x92\xf9\x2a\x27\x7b\xf5\x46\x09\xda\xdf\x0b\x00\x82\x8a\xcf\xc6\x1e\x3c\x72\x4b\x84\xa7";

        $macKey       = "\xbf\xbe\x5e\xfb\x60\x30\x30\x52\x67\x42\xe3\xee\x89\xc7\x02\x4e\x88\x4e\x44\x0f\x1f\xf3\x76\xbb\x23\x17\xb2\xd6\x4d\xeb\x7c\x83";

        $nextChainKey = "\x28\xe8\xf8\xfe\xe5\x4b\x80\x1e\xef\x7c\x5c\xfb\x2f\x17\xf3\x2c\x7b\x33\x44\x85\xbb\xb7\x0f\xac\x6e\xc1\x03\x42\xa2\x46\xd1\x5d";

        $chainKey =  new ChainKey(HKDF::createFor(2), $seed, 0);
        $this->assertEquals($chainKey->getKey(), $seed);
        $this->assertEquals($chainKey->getMessageKeys()->getCipherKey(), $messageKey);
        $this->assertEquals($chainKey->getMessageKeys()->getMacKey(), $macKey);
        $this->assertEquals($chainKey->getNextChainKey()->getKey(), $nextChainKey);
        $this->assertEquals($chainKey->getIndex(), 0);
        $this->assertEquals($chainKey->getMessageKeys()->getCounter(), 0);
        $this->assertEquals($chainKey->getNextChainKey()->getIndex(), 1);
        $this->assertEquals($chainKey->getNextChainKey()->getMessageKeys()->getCounter(), 1);
      }
}
