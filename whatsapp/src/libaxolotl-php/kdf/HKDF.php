<?php
    require_once __DIR__ ."/HKDFv2.php";
    require_once __DIR__ ."/HKDFv3.php";
    class HKDF{
            const HASH_OUTPUT_SIZE  = 32;
            public static function createFor($version){
                if($version == 2)
                    return new HKDFv2();
                if($version == 3)
                    return new HKDFv3();
                throw new Exception("Unknown version $version");
            }
            public function deriveSecrets($inputKey, $info, $outputLength, $salt = null){
                $salt = ($salt != null?$salt:str_repeat(chr(0),self::HASH_OUTPUT_SIZE));
                $prk = $this->extract($salt,$inputKey);
                return $this->expand($prk,$info,$outputLength);
            }
            public function extract($salt,$inputKey){
                $mac = hash_init("sha256",HASH_HMAC,$salt);
                hash_update($mac, $inputKey);
                return hash_final($mac,true);
            }
            public function expand($prk,$info,$outputSize){
                $iterations = (int)ceil(floatval($outputSize)/floatval(self::HASH_OUTPUT_SIZE));
                $remainingBytes =  $outputSize;
                $mixin = "";
                $result = "";
                for($i = $this->getIterationStartOffset(); $i<$iterations+$this->getIterationStartOffset();$i++)
                {
                    $mac  = hash_init("sha256",HASH_HMAC,$prk);
                    hash_update($mac, $mixin);
                    if($info != null){
                        hash_update($mac,$info);
                    }
                    $updateChr = chr($i % 256);
                    hash_update($mac, $updateChr);
                    $stepResult = hash_final($mac,true);
                    $stepSize = min($remainingBytes,strlen($stepResult));
                    $result .= substr($stepResult,0,$stepSize);
                    $mixin = $stepResult;
                    $remainingBytes -= $stepSize;
                }
                return $result;
            }

    }


    