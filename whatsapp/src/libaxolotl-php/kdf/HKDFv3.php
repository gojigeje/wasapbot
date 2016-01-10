<?php

require_once __DIR__.'/HKDF.php';
class HKDFv3 extends HKDF
{
    protected function getIterationStartOffset()
    {
        return 1;
    }
}
