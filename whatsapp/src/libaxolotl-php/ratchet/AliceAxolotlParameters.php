<?php
require_once(__DIR__."/../IdentityKey.php");
require_once(__DIR__."/../IdentityKeyPair.php");
require_once(__DIR__."/../ecc/ECKeyPair.php");
require_once(__DIR__."/../ecc/ECPublicKey.php");
class AliceAxolotlParameters {
    protected $ourIdentityKey;    // IdentityKeyPair
    protected $ourBaseKey;    // ECKeyPair
    protected $theirIdentityKey;    // IdentityKey
    protected $theirSignedPreKey;    // ECPublicKey
    protected $theirOneTimePreKey;    // Optional<ECPublicKey>
    protected $theirRatchetKey;    // ECPublicKey

    public function AliceAxolotlParameters ($ourIdentityKey, $ourBaseKey, $theirIdentityKey, $theirSignedPreKey, $theirRatchetKey, $theirOneTimePreKey) // [IdentityKeyPair ourIdentityKey, ECKeyPair ourBaseKey, IdentityKey theirIdentityKey, ECPublicKey theirSignedPreKey, ECPublicKey theirRatchetKey, Optional<ECPublicKey> theirOneTimePreKey]
    {
        $this->ourIdentityKey = $ourIdentityKey;
        $this->ourBaseKey = $ourBaseKey;
        $this->theirIdentityKey = $theirIdentityKey;
        $this->theirSignedPreKey = $theirSignedPreKey;
        $this->theirRatchetKey = $theirRatchetKey;
        $this->theirOneTimePreKey = $theirOneTimePreKey;
        if (($ourIdentityKey == null) || ($ourBaseKey == null) 
            || ($theirIdentityKey == null) || ($theirSignedPreKey == null) || ($theirRatchetKey == null))
        {
            throw new Exception("Null values!");
        }
    }
    public function getOurIdentityKey ()
    {
        return $this->ourIdentityKey;
    }
    public function getOurBaseKey ()
    {
        return $this->ourBaseKey;
    }
    public function getTheirIdentityKey ()
    {
        return $this->theirIdentityKey;
    }
    public function getTheirSignedPreKey ()
    {
        return $this->theirSignedPreKey;
    }
    public function getTheirOneTimePreKey ()
    {
        return $this->theirOneTimePreKey;
    }
    public static function newBuilder ()
    {
        return new AliceBuilder();
    }
    public function getTheirRatchetKey ()
    {
        return $this->theirRatchetKey;
    }
}
class AliceBuilder{
    protected $ourIdentityKey;
    protected $ourBaseKey;
    protected $theirIdentityKey;
    protected $theirSignedPreKey;
    protected $theirRatchetKey;
    protected $theirOneTimePreKey;
    public function AliceBuilder(){
        $this->ourIdentityKey = null;
        $this->ourBaseKey = null;
        $this->theirIdentityKey = null;
        $this->theirSignedPreKey = null;
        $this->theirRatchetKey = null;
        $this->theirOneTimePreKey = null;
    }
    public function setOurIdentityKey($ourIdentityKey){
        $this->ourIdentityKey = $ourIdentityKey;
        return $this;
    }
    public function setOurBaseKey($ourBaseKey){
        $this->ourBaseKey = $ourBaseKey;
        return $this;
    }

    public function setTheirRatchetKey($theirRatchetKey){
        $this->theirRatchetKey = $theirRatchetKey;
        return $this;
    }
    public function setTheirIdentityKey($theirIdentityKey){
        $this->theirIdentityKey = $theirIdentityKey;
        return $this;
    }
    public function setTheirSignedPreKey($theirSignedPreKey){
        $this->theirSignedPreKey = $theirSignedPreKey;
        return $this;
    }

    public function setTheirOneTimePreKey($theirOneTimePreKey){
        $this->theirOneTimePreKey = $theirOneTimePreKey;
        return $this;
    }

    public function create(){
        return new AliceAxolotlParameters($this->ourIdentityKey, $this->ourBaseKey, $this->theirIdentityKey,
                                      $this->theirSignedPreKey, $this->theirRatchetKey, $this->theirOneTimePreKey);
    }
}