<?php

require_once __DIR__.'/../IdentityKey.php';
require_once __DIR__.'/../IdentityKeyPair.php';
require_once __DIR__.'/../ecc/ECKeyPair.php';
require_once __DIR__.'/../ecc/ECPublicKey.php';
class SymmetricAxolotlParameters
{
    protected $ourBaseKey;    // ECKeyPair
    protected $ourRatchetKey;    // ECKeyPair
    protected $ourIdentityKey;    // IdentityKeyPair
    protected $theirBaseKey;    // ECPublicKey
    protected $theirRatchetKey;    // ECPublicKey
    protected $theirIdentityKey;    // IdentityKey

    public function SymmetricAxolotlParameters($ourBaseKey, $ourRatchetKey, $ourIdentityKey, $theirBaseKey, $theirRatchetKey, $theirIdentityKey) // [ECKeyPair ourBaseKey, ECKeyPair ourRatchetKey, IdentityKeyPair ourIdentityKey, ECPublicKey theirBaseKey, ECPublicKey theirRatchetKey, IdentityKey theirIdentityKey]
    {
        $this->ourBaseKey = $ourBaseKey;
        $this->ourRatchetKey = $ourRatchetKey;
        $this->ourIdentityKey = $ourIdentityKey;
        $this->theirBaseKey = $theirBaseKey;
        $this->theirRatchetKey = $theirRatchetKey;
        $this->theirIdentityKey = $theirIdentityKey;

        if (($ourBaseKey == null) || ($ourRatchetKey == null)
            || ($ourIdentityKey == null) || ($theirBaseKey == null)
            || ($theirRatchetKey == null) || ($theirIdentityKey == null)) {
            throw new Exception('Null values!');
        }
    }

    public function getOurBaseKey()
    {
        return $this->ourBaseKey;
    }

    public function getOurRatchetKey()
    {
        return $this->ourRatchetKey;
    }

    public function getOurIdentityKey()
    {
        return $this->ourIdentityKey;
    }

    public function getTheirBaseKey()
    {
        return $this->theirBaseKey;
    }

    public function getTheirRatchetKey()
    {
        return $this->theirRatchetKey;
    }

    public function getTheirIdentityKey()
    {
        return $this->theirIdentityKey;
    }

    public static function newBuilder()
    {
        return new SymmetricBuilder();
    }
}
class SymmetricBuilder
{
    protected $ourBaseKey;    // ECKeyPair
        protected $ourRatchetKey;    // ECKeyPair
        protected $ourIdentityKey;    // IdentityKeyPair
        protected $theirBaseKey;    // ECPublicKey
        protected $theirRatchetKey;    // ECPublicKey
        protected $theirIdentityKey;    // IdentityKey

        public function SymmetricBuilder()
        {
            $this->ourIdentityKey = null;
            $this->ourBaseKey = null;
            $this->ourRatchetKey = null;
            $this->theirRatchetKey = null;
            $this->theirIdentityKey = null;
            $this->theirBaseKey = null;
        }

    public function setOurIdentityKey($ourIdentityKey)
    {
        $this->ourIdentityKey = $ourIdentityKey;

        return $this;
    }

    public function setOurBaseKey($ourBaseKey)
    {
        $this->ourBaseKey = $ourBaseKey;

        return $this;
    }

    public function setOurRatchetKey($ourRatchetKey)
    {
        $this->ourRatchetKey = $ourRatchetKey;

        return $this;
    }

    public function setTheirRatchetKey($theirRatchetKey)
    {
        $this->theirRatchetKey = $theirRatchetKey;

        return $this;
    }

    public function setTheirIdentityKey($theirIdentityKey)
    {
        $this->theirIdentityKey = $theirIdentityKey;

        return $this;
    }

    public function setTheirBaseKey($theirBaseKey)
    {
        $this->theirBaseKey = $theirBaseKey;

        return $this;
    }

    public function create()
    {
        return new SymmetricAxolotlParameters($this->ourBaseKey, $this->ourRatchetKey, $this->ourIdentityKey,
                                        $this->theirBaseKey, $this->theirRatchetKey, $this->theirIdentityKey);
    }
}
