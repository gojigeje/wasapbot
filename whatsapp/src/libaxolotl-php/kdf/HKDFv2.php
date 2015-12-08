<?php
require_once __DIR__ ."/HKDF.php";
class HKDFv2 extends HKDF {
    protected function getIterationStartOffset ()
    {
        return 0;
    }
}