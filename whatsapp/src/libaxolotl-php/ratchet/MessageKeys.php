<?php

class MessageKeys
{
    protected $cipherKey;    // SecretKeySpec
    protected $macKey;    // SecretKeySpec
    protected $iv;    // IvParameterSpec
    protected $counter;    // int

    public function MessageKeys($cipherKey, $macKey, $iv, $counter) // [SecretKeySpec cipherKey, SecretKeySpec macKey, IvParameterSpec iv, int counter]
    {
        $this->cipherKey = $cipherKey;
        $this->macKey = $macKey;
        $this->iv = $iv;
        $this->counter = $counter;
    }

    public function getCipherKey()
    {
        return $this->cipherKey;
    }

    public function getMacKey()
    {
        return $this->macKey;
    }

    public function getIv()
    {
        return $this->iv;
    }

    public function getCounter()
    {
        return $this->counter;
    }
}
