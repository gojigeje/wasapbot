<?php
/**
 * Auto generated from LocalStorageProtocol.proto at 2015-09-10 23:19:03
 *
 * textsecure package
 */

/**
 * ChainKey message embedded in Chain/SessionStructure message
 */
class Textsecure_SessionStructure_Chain_ChainKey extends \ProtobufMessage
{
    /* Field index constants */
    const INDEX = 1;
    const KEY = 2;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::INDEX => array(
            'name' => 'index',
            'required' => false,
            'type' => 5,
        ),
        self::KEY => array(
            'name' => 'key',
            'required' => false,
            'type' => 7,
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::INDEX] = null;
        $this->values[self::KEY] = null;
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'index' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setIndex($value)
    {
        return $this->set(self::INDEX, $value);
    }

    /**
     * Returns value of 'index' property
     *
     * @return int
     */
    public function getIndex()
    {
        return $this->get(self::INDEX);
    }

    /**
     * Sets value of 'key' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setKey($value)
    {
        return $this->set(self::KEY, $value);
    }

    /**
     * Returns value of 'key' property
     *
     * @return string
     */
    public function getKey()
    {
        return $this->get(self::KEY);
    }
}

/**
 * MessageKey message embedded in Chain/SessionStructure message
 */
class Textsecure_SessionStructure_Chain_MessageKey extends \ProtobufMessage
{
    /* Field index constants */
    const INDEX = 1;
    const CIPHERKEY = 2;
    const MACKEY = 3;
    const IV = 4;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::INDEX => array(
            'name' => 'index',
            'required' => false,
            'type' => 5,
        ),
        self::CIPHERKEY => array(
            'name' => 'cipherKey',
            'required' => false,
            'type' => 7,
        ),
        self::MACKEY => array(
            'name' => 'macKey',
            'required' => false,
            'type' => 7,
        ),
        self::IV => array(
            'name' => 'iv',
            'required' => false,
            'type' => 7,
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::INDEX] = null;
        $this->values[self::CIPHERKEY] = null;
        $this->values[self::MACKEY] = null;
        $this->values[self::IV] = null;
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'index' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setIndex($value)
    {
        return $this->set(self::INDEX, $value);
    }

    /**
     * Returns value of 'index' property
     *
     * @return int
     */
    public function getIndex()
    {
        return $this->get(self::INDEX);
    }

    /**
     * Sets value of 'cipherKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setCipherKey($value)
    {
        return $this->set(self::CIPHERKEY, $value);
    }

    /**
     * Returns value of 'cipherKey' property
     *
     * @return string
     */
    public function getCipherKey()
    {
        return $this->get(self::CIPHERKEY);
    }

    /**
     * Sets value of 'macKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setMacKey($value)
    {
        return $this->set(self::MACKEY, $value);
    }

    /**
     * Returns value of 'macKey' property
     *
     * @return string
     */
    public function getMacKey()
    {
        return $this->get(self::MACKEY);
    }

    /**
     * Sets value of 'iv' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setIv($value)
    {
        return $this->set(self::IV, $value);
    }

    /**
     * Returns value of 'iv' property
     *
     * @return string
     */
    public function getIv()
    {
        return $this->get(self::IV);
    }
}

/**
 * Chain message embedded in SessionStructure message
 */
class Textsecure_SessionStructure_Chain extends \ProtobufMessage
{
    /* Field index constants */
    const SENDERRATCHETKEY = 1;
    const SENDERRATCHETKEYPRIVATE = 2;
    const CHAINKEY = 3;
    const MESSAGEKEYS = 4;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::SENDERRATCHETKEY => array(
            'name' => 'senderRatchetKey',
            'required' => false,
            'type' => 7,
        ),
        self::SENDERRATCHETKEYPRIVATE => array(
            'name' => 'senderRatchetKeyPrivate',
            'required' => false,
            'type' => 7,
        ),
        self::CHAINKEY => array(
            'name' => 'chainKey',
            'required' => false,
            'type' => 'Textsecure_SessionStructure_Chain_ChainKey'
        ),
        self::MESSAGEKEYS => array(
            'name' => 'messageKeys',
            'repeated' => true,
            'type' => 'Textsecure_SessionStructure_Chain_MessageKey'
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::SENDERRATCHETKEY] = null;
        $this->values[self::SENDERRATCHETKEYPRIVATE] = null;
        $this->values[self::CHAINKEY] = null;
        $this->values[self::MESSAGEKEYS] = array();
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'senderRatchetKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setSenderRatchetKey($value)
    {
        return $this->set(self::SENDERRATCHETKEY, $value);
    }

    /**
     * Returns value of 'senderRatchetKey' property
     *
     * @return string
     */
    public function getSenderRatchetKey()
    {
        return $this->get(self::SENDERRATCHETKEY);
    }

    /**
     * Sets value of 'senderRatchetKeyPrivate' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setSenderRatchetKeyPrivate($value)
    {
        return $this->set(self::SENDERRATCHETKEYPRIVATE, $value);
    }

    /**
     * Returns value of 'senderRatchetKeyPrivate' property
     *
     * @return string
     */
    public function getSenderRatchetKeyPrivate()
    {
        return $this->get(self::SENDERRATCHETKEYPRIVATE);
    }

    /**
     * Sets value of 'chainKey' property
     *
     * @param Textsecure_SessionStructure_Chain_ChainKey $value Property value
     *
     * @return null
     */
    public function setChainKey(Textsecure_SessionStructure_Chain_ChainKey $value)
    {
        return $this->set(self::CHAINKEY, $value);
    }

    /**
     * Returns value of 'chainKey' property
     *
     * @return Textsecure_SessionStructure_Chain_ChainKey
     */
    public function getChainKey()
    {
        return $this->get(self::CHAINKEY);
    }

    /**
     * Appends value to 'messageKeys' list
     *
     * @param Textsecure_SessionStructure_Chain_MessageKey $value Value to append
     *
     * @return null
     */
    public function appendMessageKeys(Textsecure_SessionStructure_Chain_MessageKey $value)
    {
        return $this->append(self::MESSAGEKEYS, $value);
    }

    /**
     * Clears 'messageKeys' list
     *
     * @return null
     */
    public function clearMessageKeys()
    {
        return $this->clear(self::MESSAGEKEYS);
    }

    /**
     * Returns 'messageKeys' list
     *
     * @return Textsecure_SessionStructure_Chain_MessageKey[]
     */
    public function getMessageKeys()
    {
        return $this->get(self::MESSAGEKEYS);
    }

    /**
     * Returns 'messageKeys' iterator
     *
     * @return ArrayIterator
     */
    public function getMessageKeysIterator()
    {
        return new \ArrayIterator($this->get(self::MESSAGEKEYS));
    }

    /**
     * Returns element from 'messageKeys' list at given offset
     *
     * @param int $offset Position in list
     *
     * @return Textsecure_SessionStructure_Chain_MessageKey
     */
    public function getMessageKeysAt($offset)
    {
        return $this->get(self::MESSAGEKEYS, $offset);
    }

    /**
     * Returns count of 'messageKeys' list
     *
     * @return int
     */
    public function getMessageKeysCount()
    {
        return $this->count(self::MESSAGEKEYS);
    }
}

/**
 * PendingKeyExchange message embedded in SessionStructure message
 */
class Textsecure_SessionStructure_PendingKeyExchange extends \ProtobufMessage
{
    /* Field index constants */
    const SEQUENCE = 1;
    const LOCALBASEKEY = 2;
    const LOCALBASEKEYPRIVATE = 3;
    const LOCALRATCHETKEY = 4;
    const LOCALRATCHETKEYPRIVATE = 5;
    const LOCALIDENTITYKEY = 7;
    const LOCALIDENTITYKEYPRIVATE = 8;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::SEQUENCE => array(
            'name' => 'sequence',
            'required' => false,
            'type' => 5,
        ),
        self::LOCALBASEKEY => array(
            'name' => 'localBaseKey',
            'required' => false,
            'type' => 7,
        ),
        self::LOCALBASEKEYPRIVATE => array(
            'name' => 'localBaseKeyPrivate',
            'required' => false,
            'type' => 7,
        ),
        self::LOCALRATCHETKEY => array(
            'name' => 'localRatchetKey',
            'required' => false,
            'type' => 7,
        ),
        self::LOCALRATCHETKEYPRIVATE => array(
            'name' => 'localRatchetKeyPrivate',
            'required' => false,
            'type' => 7,
        ),
        self::LOCALIDENTITYKEY => array(
            'name' => 'localIdentityKey',
            'required' => false,
            'type' => 7,
        ),
        self::LOCALIDENTITYKEYPRIVATE => array(
            'name' => 'localIdentityKeyPrivate',
            'required' => false,
            'type' => 7,
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::SEQUENCE] = null;
        $this->values[self::LOCALBASEKEY] = null;
        $this->values[self::LOCALBASEKEYPRIVATE] = null;
        $this->values[self::LOCALRATCHETKEY] = null;
        $this->values[self::LOCALRATCHETKEYPRIVATE] = null;
        $this->values[self::LOCALIDENTITYKEY] = null;
        $this->values[self::LOCALIDENTITYKEYPRIVATE] = null;
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'sequence' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setSequence($value)
    {
        return $this->set(self::SEQUENCE, $value);
    }

    /**
     * Returns value of 'sequence' property
     *
     * @return int
     */
    public function getSequence()
    {
        return $this->get(self::SEQUENCE);
    }

    /**
     * Sets value of 'localBaseKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setLocalBaseKey($value)
    {
        return $this->set(self::LOCALBASEKEY, $value);
    }

    /**
     * Returns value of 'localBaseKey' property
     *
     * @return string
     */
    public function getLocalBaseKey()
    {
        return $this->get(self::LOCALBASEKEY);
    }

    /**
     * Sets value of 'localBaseKeyPrivate' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setLocalBaseKeyPrivate($value)
    {
        return $this->set(self::LOCALBASEKEYPRIVATE, $value);
    }

    /**
     * Returns value of 'localBaseKeyPrivate' property
     *
     * @return string
     */
    public function getLocalBaseKeyPrivate()
    {
        return $this->get(self::LOCALBASEKEYPRIVATE);
    }

    /**
     * Sets value of 'localRatchetKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setLocalRatchetKey($value)
    {
        return $this->set(self::LOCALRATCHETKEY, $value);
    }

    /**
     * Returns value of 'localRatchetKey' property
     *
     * @return string
     */
    public function getLocalRatchetKey()
    {
        return $this->get(self::LOCALRATCHETKEY);
    }

    /**
     * Sets value of 'localRatchetKeyPrivate' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setLocalRatchetKeyPrivate($value)
    {
        return $this->set(self::LOCALRATCHETKEYPRIVATE, $value);
    }

    /**
     * Returns value of 'localRatchetKeyPrivate' property
     *
     * @return string
     */
    public function getLocalRatchetKeyPrivate()
    {
        return $this->get(self::LOCALRATCHETKEYPRIVATE);
    }

    /**
     * Sets value of 'localIdentityKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setLocalIdentityKey($value)
    {
        return $this->set(self::LOCALIDENTITYKEY, $value);
    }

    /**
     * Returns value of 'localIdentityKey' property
     *
     * @return string
     */
    public function getLocalIdentityKey()
    {
        return $this->get(self::LOCALIDENTITYKEY);
    }

    /**
     * Sets value of 'localIdentityKeyPrivate' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setLocalIdentityKeyPrivate($value)
    {
        return $this->set(self::LOCALIDENTITYKEYPRIVATE, $value);
    }

    /**
     * Returns value of 'localIdentityKeyPrivate' property
     *
     * @return string
     */
    public function getLocalIdentityKeyPrivate()
    {
        return $this->get(self::LOCALIDENTITYKEYPRIVATE);
    }
}

/**
 * PendingPreKey message embedded in SessionStructure message
 */
class Textsecure_SessionStructure_PendingPreKey extends \ProtobufMessage
{
    /* Field index constants */
    const PREKEYID = 1;
    const SIGNEDPREKEYID = 3;
    const BASEKEY = 2;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::PREKEYID => array(
            'name' => 'preKeyId',
            'required' => false,
            'type' => 5,
        ),
        self::SIGNEDPREKEYID => array(
            'name' => 'signedPreKeyId',
            'required' => false,
            'type' => 5,
        ),
        self::BASEKEY => array(
            'name' => 'baseKey',
            'required' => false,
            'type' => 7,
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::PREKEYID] = null;
        $this->values[self::SIGNEDPREKEYID] = null;
        $this->values[self::BASEKEY] = null;
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'preKeyId' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setPreKeyId($value)
    {
        return $this->set(self::PREKEYID, $value);
    }

    /**
     * Returns value of 'preKeyId' property
     *
     * @return int
     */
    public function getPreKeyId()
    {
        return $this->get(self::PREKEYID);
    }

    /**
     * Sets value of 'signedPreKeyId' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setSignedPreKeyId($value)
    {
        return $this->set(self::SIGNEDPREKEYID, $value);
    }

    /**
     * Returns value of 'signedPreKeyId' property
     *
     * @return int
     */
    public function getSignedPreKeyId()
    {
        return $this->get(self::SIGNEDPREKEYID);
    }

    /**
     * Sets value of 'baseKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setBaseKey($value)
    {
        return $this->set(self::BASEKEY, $value);
    }

    /**
     * Returns value of 'baseKey' property
     *
     * @return string
     */
    public function getBaseKey()
    {
        return $this->get(self::BASEKEY);
    }
}

/**
 * SessionStructure message
 */
class Textsecure_SessionStructure extends \ProtobufMessage
{
    /* Field index constants */
    const SESSIONVERSION = 1;
    const LOCALIDENTITYPUBLIC = 2;
    const REMOTEIDENTITYPUBLIC = 3;
    const ROOTKEY = 4;
    const PREVIOUSCOUNTER = 5;
    const SENDERCHAIN = 6;
    const RECEIVERCHAINS = 7;
    const PENDINGKEYEXCHANGE = 8;
    const PENDINGPREKEY = 9;
    const REMOTEREGISTRATIONID = 10;
    const LOCALREGISTRATIONID = 11;
    const NEEDSREFRESH = 12;
    const ALICEBASEKEY = 13;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::SESSIONVERSION => array(
            'name' => 'sessionVersion',
            'required' => false,
            'type' => 5,
        ),
        self::LOCALIDENTITYPUBLIC => array(
            'name' => 'localIdentityPublic',
            'required' => false,
            'type' => 7,
        ),
        self::REMOTEIDENTITYPUBLIC => array(
            'name' => 'remoteIdentityPublic',
            'required' => false,
            'type' => 7,
        ),
        self::ROOTKEY => array(
            'name' => 'rootKey',
            'required' => false,
            'type' => 7,
        ),
        self::PREVIOUSCOUNTER => array(
            'name' => 'previousCounter',
            'required' => false,
            'type' => 5,
        ),
        self::SENDERCHAIN => array(
            'name' => 'senderChain',
            'required' => false,
            'type' => 'Textsecure_SessionStructure_Chain'
        ),
        self::RECEIVERCHAINS => array(
            'name' => 'receiverChains',
            'repeated' => true,
            'type' => 'Textsecure_SessionStructure_Chain'
        ),
        self::PENDINGKEYEXCHANGE => array(
            'name' => 'pendingKeyExchange',
            'required' => false,
            'type' => 'Textsecure_SessionStructure_PendingKeyExchange'
        ),
        self::PENDINGPREKEY => array(
            'name' => 'pendingPreKey',
            'required' => false,
            'type' => 'Textsecure_SessionStructure_PendingPreKey'
        ),
        self::REMOTEREGISTRATIONID => array(
            'name' => 'remoteRegistrationId',
            'required' => false,
            'type' => 5,
        ),
        self::LOCALREGISTRATIONID => array(
            'name' => 'localRegistrationId',
            'required' => false,
            'type' => 5,
        ),
        self::NEEDSREFRESH => array(
            'name' => 'needsRefresh',
            'required' => false,
            'type' => 8,
        ),
        self::ALICEBASEKEY => array(
            'name' => 'aliceBaseKey',
            'required' => false,
            'type' => 7,
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::SESSIONVERSION] = null;
        $this->values[self::LOCALIDENTITYPUBLIC] = null;
        $this->values[self::REMOTEIDENTITYPUBLIC] = null;
        $this->values[self::ROOTKEY] = null;
        $this->values[self::PREVIOUSCOUNTER] = null;
        $this->values[self::SENDERCHAIN] = null;
        $this->values[self::RECEIVERCHAINS] = array();
        $this->values[self::PENDINGKEYEXCHANGE] = null;
        $this->values[self::PENDINGPREKEY] = null;
        $this->values[self::REMOTEREGISTRATIONID] = null;
        $this->values[self::LOCALREGISTRATIONID] = null;
        $this->values[self::NEEDSREFRESH] = null;
        $this->values[self::ALICEBASEKEY] = null;
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'sessionVersion' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setSessionVersion($value)
    {
        return $this->set(self::SESSIONVERSION, $value);
    }

    /**
     * Returns value of 'sessionVersion' property
     *
     * @return int
     */
    public function getSessionVersion()
    {
        return $this->get(self::SESSIONVERSION);
    }

    /**
     * Sets value of 'localIdentityPublic' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setLocalIdentityPublic($value)
    {
        return $this->set(self::LOCALIDENTITYPUBLIC, $value);
    }

    /**
     * Returns value of 'localIdentityPublic' property
     *
     * @return string
     */
    public function getLocalIdentityPublic()
    {
        return $this->get(self::LOCALIDENTITYPUBLIC);
    }

    /**
     * Sets value of 'remoteIdentityPublic' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setRemoteIdentityPublic($value)
    {
        return $this->set(self::REMOTEIDENTITYPUBLIC, $value);
    }

    /**
     * Returns value of 'remoteIdentityPublic' property
     *
     * @return string
     */
    public function getRemoteIdentityPublic()
    {
        return $this->get(self::REMOTEIDENTITYPUBLIC);
    }

    /**
     * Sets value of 'rootKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setRootKey($value)
    {
        return $this->set(self::ROOTKEY, $value);
    }

    /**
     * Returns value of 'rootKey' property
     *
     * @return string
     */
    public function getRootKey()
    {
        return $this->get(self::ROOTKEY);
    }

    /**
     * Sets value of 'previousCounter' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setPreviousCounter($value)
    {
        return $this->set(self::PREVIOUSCOUNTER, $value);
    }

    /**
     * Returns value of 'previousCounter' property
     *
     * @return int
     */
    public function getPreviousCounter()
    {
        return $this->get(self::PREVIOUSCOUNTER);
    }

    /**
     * Sets value of 'senderChain' property
     *
     * @param Textsecure_SessionStructure_Chain $value Property value
     *
     * @return null
     */
    public function setSenderChain(Textsecure_SessionStructure_Chain $value)
    {
        return $this->set(self::SENDERCHAIN, $value);
    }

    /**
     * Returns value of 'senderChain' property
     *
     * @return Textsecure_SessionStructure_Chain
     */
    public function getSenderChain()
    {
        return $this->get(self::SENDERCHAIN);
    }

    /**
     * Appends value to 'receiverChains' list
     *
     * @param Textsecure_SessionStructure_Chain $value Value to append
     *
     * @return null
     */
    public function appendReceiverChains(Textsecure_SessionStructure_Chain $value)
    {
        return $this->append(self::RECEIVERCHAINS, $value);
    }

    /**
     * Clears 'receiverChains' list
     *
     * @return null
     */
    public function clearReceiverChains()
    {
        return $this->clear(self::RECEIVERCHAINS);
    }

    /**
     * Returns 'receiverChains' list
     *
     * @return Textsecure_SessionStructure_Chain[]
     */
    public function getReceiverChains()
    {
        return $this->get(self::RECEIVERCHAINS);
    }

    /**
     * Returns 'receiverChains' iterator
     *
     * @return ArrayIterator
     */
    public function getReceiverChainsIterator()
    {
        return new \ArrayIterator($this->get(self::RECEIVERCHAINS));
    }

    /**
     * Returns element from 'receiverChains' list at given offset
     *
     * @param int $offset Position in list
     *
     * @return Textsecure_SessionStructure_Chain
     */
    public function getReceiverChainsAt($offset)
    {
        return $this->get(self::RECEIVERCHAINS, $offset);
    }

    /**
     * Returns count of 'receiverChains' list
     *
     * @return int
     */
    public function getReceiverChainsCount()
    {
        return $this->count(self::RECEIVERCHAINS);
    }

    /**
     * Sets value of 'pendingKeyExchange' property
     *
     * @param Textsecure_SessionStructure_PendingKeyExchange $value Property value
     *
     * @return null
     */
    public function setPendingKeyExchange(Textsecure_SessionStructure_PendingKeyExchange $value)
    {
        return $this->set(self::PENDINGKEYEXCHANGE, $value);
    }

    /**
     * Returns value of 'pendingKeyExchange' property
     *
     * @return Textsecure_SessionStructure_PendingKeyExchange
     */
    public function getPendingKeyExchange()
    {
        return $this->get(self::PENDINGKEYEXCHANGE);
    }

    /**
     * Sets value of 'pendingPreKey' property
     *
     * @param Textsecure_SessionStructure_PendingPreKey $value Property value
     *
     * @return null
     */
    public function setPendingPreKey(Textsecure_SessionStructure_PendingPreKey $value)
    {
        return $this->set(self::PENDINGPREKEY, $value);
    }

    /**
     * Returns value of 'pendingPreKey' property
     *
     * @return Textsecure_SessionStructure_PendingPreKey
     */
    public function getPendingPreKey()
    {
        return $this->get(self::PENDINGPREKEY);
    }
    public function clearPendingPreKey(){
      $this->values[self::PENDINGPREKEY] = null;
    }
    /**
     * Sets value of 'remoteRegistrationId' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setRemoteRegistrationId($value)
    {
        return $this->set(self::REMOTEREGISTRATIONID, $value);
    }

    /**
     * Returns value of 'remoteRegistrationId' property
     *
     * @return int
     */
    public function getRemoteRegistrationId()
    {
        return $this->get(self::REMOTEREGISTRATIONID);
    }

    /**
     * Sets value of 'localRegistrationId' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setLocalRegistrationId($value)
    {
        return $this->set(self::LOCALREGISTRATIONID, $value);
    }

    /**
     * Returns value of 'localRegistrationId' property
     *
     * @return int
     */
    public function getLocalRegistrationId()
    {
        return $this->get(self::LOCALREGISTRATIONID);
    }

    /**
     * Sets value of 'needsRefresh' property
     *
     * @param bool $value Property value
     *
     * @return null
     */
    public function setNeedsRefresh($value)
    {
        return $this->set(self::NEEDSREFRESH, $value);
    }

    /**
     * Returns value of 'needsRefresh' property
     *
     * @return bool
     */
    public function getNeedsRefresh()
    {
        return $this->get(self::NEEDSREFRESH);
    }

    /**
     * Sets value of 'aliceBaseKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setAliceBaseKey($value)
    {
        return $this->set(self::ALICEBASEKEY, $value);
    }

    /**
     * Returns value of 'aliceBaseKey' property
     *
     * @return string
     */
    public function getAliceBaseKey()
    {
        return $this->get(self::ALICEBASEKEY);
    }
}

/**
 * RecordStructure message
 */
class Textsecure_RecordStructure extends \ProtobufMessage
{
    /* Field index constants */
    const CURRENTSESSION = 1;
    const PREVIOUSSESSIONS = 2;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::CURRENTSESSION => array(
            'name' => 'currentSession',
            'required' => false,
            'type' => 'Textsecure_SessionStructure'
        ),
        self::PREVIOUSSESSIONS => array(
            'name' => 'previousSessions',
            'repeated' => true,
            'type' => 'Textsecure_SessionStructure'
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::CURRENTSESSION] = null;
        $this->values[self::PREVIOUSSESSIONS] = array();
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'currentSession' property
     *
     * @param Textsecure_SessionStructure $value Property value
     *
     * @return null
     */
    public function setCurrentSession(Textsecure_SessionStructure $value)
    {
        return $this->set(self::CURRENTSESSION, $value);
    }

    /**
     * Returns value of 'currentSession' property
     *
     * @return Textsecure_SessionStructure
     */
    public function getCurrentSession()
    {
        return $this->get(self::CURRENTSESSION);
    }

    /**
     * Appends value to 'previousSessions' list
     *
     * @param Textsecure_SessionStructure $value Value to append
     *
     * @return null
     */
    public function appendPreviousSessions(Textsecure_SessionStructure $value)
    {
        return $this->append(self::PREVIOUSSESSIONS, $value);
    }

    /**
     * Clears 'previousSessions' list
     *
     * @return null
     */
    public function clearPreviousSessions()
    {
        return $this->clear(self::PREVIOUSSESSIONS);
    }

    /**
     * Returns 'previousSessions' list
     *
     * @return Textsecure_SessionStructure[]
     */
    public function getPreviousSessions()
    {
        return $this->get(self::PREVIOUSSESSIONS);
    }

    /**
     * Returns 'previousSessions' iterator
     *
     * @return ArrayIterator
     */
    public function getPreviousSessionsIterator()
    {
        return new \ArrayIterator($this->get(self::PREVIOUSSESSIONS));
    }

    /**
     * Returns element from 'previousSessions' list at given offset
     *
     * @param int $offset Position in list
     *
     * @return Textsecure_SessionStructure
     */
    public function getPreviousSessionsAt($offset)
    {
        return $this->get(self::PREVIOUSSESSIONS, $offset);
    }

    /**
     * Returns count of 'previousSessions' list
     *
     * @return int
     */
    public function getPreviousSessionsCount()
    {
        return $this->count(self::PREVIOUSSESSIONS);
    }
}

/**
 * PreKeyRecordStructure message
 */
class Textsecure_PreKeyRecordStructure extends \ProtobufMessage
{
    /* Field index constants */
    const ID = 1;
    const PUBLICKEY = 2;
    const PRIVATEKEY = 3;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::ID => array(
            'name' => 'id',
            'required' => false,
            'type' => 5,
        ),
        self::PUBLICKEY => array(
            'name' => 'publicKey',
            'required' => false,
            'type' => 7,
        ),
        self::PRIVATEKEY => array(
            'name' => 'privateKey',
            'required' => false,
            'type' => 7,
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::ID] = null;
        $this->values[self::PUBLICKEY] = null;
        $this->values[self::PRIVATEKEY] = null;
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'id' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setId($value)
    {
        return $this->set(self::ID, $value);
    }

    /**
     * Returns value of 'id' property
     *
     * @return int
     */
    public function getId()
    {
        return $this->get(self::ID);
    }

    /**
     * Sets value of 'publicKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setPublicKey($value)
    {
        return $this->set(self::PUBLICKEY, $value);
    }

    /**
     * Returns value of 'publicKey' property
     *
     * @return string
     */
    public function getPublicKey()
    {
        return $this->get(self::PUBLICKEY);
    }

    /**
     * Sets value of 'privateKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setPrivateKey($value)
    {
        return $this->set(self::PRIVATEKEY, $value);
    }

    /**
     * Returns value of 'privateKey' property
     *
     * @return string
     */
    public function getPrivateKey()
    {
        return $this->get(self::PRIVATEKEY);
    }
}

/**
 * SignedPreKeyRecordStructure message
 */
class Textsecure_SignedPreKeyRecordStructure extends \ProtobufMessage
{
    /* Field index constants */
    const ID = 1;
    const PUBLICKEY = 2;
    const PRIVATEKEY = 3;
    const SIGNATURE = 4;
    const TIMESTAMP = 5;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::ID => array(
            'name' => 'id',
            'required' => false,
            'type' => 5,
        ),
        self::PUBLICKEY => array(
            'name' => 'publicKey',
            'required' => false,
            'type' => 7,
        ),
        self::PRIVATEKEY => array(
            'name' => 'privateKey',
            'required' => false,
            'type' => 7,
        ),
        self::SIGNATURE => array(
            'name' => 'signature',
            'required' => false,
            'type' => 7,
        ),
        self::TIMESTAMP => array(
            'name' => 'timestamp',
            'required' => false,
            'type' => 3,
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::ID] = null;
        $this->values[self::PUBLICKEY] = null;
        $this->values[self::PRIVATEKEY] = null;
        $this->values[self::SIGNATURE] = null;
        $this->values[self::TIMESTAMP] = null;
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'id' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setId($value)
    {
        return $this->set(self::ID, $value);
    }

    /**
     * Returns value of 'id' property
     *
     * @return int
     */
    public function getId()
    {
        return $this->get(self::ID);
    }

    /**
     * Sets value of 'publicKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setPublicKey($value)
    {
        return $this->set(self::PUBLICKEY, $value);
    }

    /**
     * Returns value of 'publicKey' property
     *
     * @return string
     */
    public function getPublicKey()
    {
        return $this->get(self::PUBLICKEY);
    }

    /**
     * Sets value of 'privateKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setPrivateKey($value)
    {
        return $this->set(self::PRIVATEKEY, $value);
    }

    /**
     * Returns value of 'privateKey' property
     *
     * @return string
     */
    public function getPrivateKey()
    {
        return $this->get(self::PRIVATEKEY);
    }

    /**
     * Sets value of 'signature' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setSignature($value)
    {
        return $this->set(self::SIGNATURE, $value);
    }

    /**
     * Returns value of 'signature' property
     *
     * @return string
     */
    public function getSignature()
    {
        return $this->get(self::SIGNATURE);
    }

    /**
     * Sets value of 'timestamp' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setTimestamp($value)
    {
        return $this->set(self::TIMESTAMP, $value);
    }

    /**
     * Returns value of 'timestamp' property
     *
     * @return int
     */
    public function getTimestamp()
    {
        return $this->get(self::TIMESTAMP);
    }
}

/**
 * IdentityKeyPairStructure message
 */
class Textsecure_IdentityKeyPairStructure extends \ProtobufMessage
{
    /* Field index constants */
    const PUBLICKEY = 1;
    const PRIVATEKEY = 2;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::PUBLICKEY => array(
            'name' => 'publicKey',
            'required' => false,
            'type' => 7,
        ),
        self::PRIVATEKEY => array(
            'name' => 'privateKey',
            'required' => false,
            'type' => 7,
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::PUBLICKEY] = null;
        $this->values[self::PRIVATEKEY] = null;
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'publicKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setPublicKey($value)
    {
        return $this->set(self::PUBLICKEY, $value);
    }

    /**
     * Returns value of 'publicKey' property
     *
     * @return string
     */
    public function getPublicKey()
    {
        return $this->get(self::PUBLICKEY);
    }

    /**
     * Sets value of 'privateKey' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setPrivateKey($value)
    {
        return $this->set(self::PRIVATEKEY, $value);
    }

    /**
     * Returns value of 'privateKey' property
     *
     * @return string
     */
    public function getPrivateKey()
    {
        return $this->get(self::PRIVATEKEY);
    }
}

/**
 * SenderChainKey message embedded in SenderKeyStateStructure message
 */
class Textsecure_SenderKeyStateStructure_SenderChainKey extends \ProtobufMessage
{
    /* Field index constants */
    const ITERATION = 1;
    const SEED = 2;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::ITERATION => array(
            'name' => 'iteration',
            'required' => false,
            'type' => 5,
        ),
        self::SEED => array(
            'name' => 'seed',
            'required' => false,
            'type' => 7,
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::ITERATION] = null;
        $this->values[self::SEED] = null;
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'iteration' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setIteration($value)
    {
        return $this->set(self::ITERATION, $value);
    }

    /**
     * Returns value of 'iteration' property
     *
     * @return int
     */
    public function getIteration()
    {
        return $this->get(self::ITERATION);
    }

    /**
     * Sets value of 'seed' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setSeed($value)
    {
        return $this->set(self::SEED, $value);
    }

    /**
     * Returns value of 'seed' property
     *
     * @return string
     */
    public function getSeed()
    {
        return $this->get(self::SEED);
    }
}

/**
 * SenderMessageKey message embedded in SenderKeyStateStructure message
 */
class Textsecure_SenderKeyStateStructure_SenderMessageKey extends \ProtobufMessage
{
    /* Field index constants */
    const ITERATION = 1;
    const SEED = 2;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::ITERATION => array(
            'name' => 'iteration',
            'required' => false,
            'type' => 5,
        ),
        self::SEED => array(
            'name' => 'seed',
            'required' => false,
            'type' => 7,
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::ITERATION] = null;
        $this->values[self::SEED] = null;
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'iteration' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setIteration($value)
    {
        return $this->set(self::ITERATION, $value);
    }

    /**
     * Returns value of 'iteration' property
     *
     * @return int
     */
    public function getIteration()
    {
        return $this->get(self::ITERATION);
    }

    /**
     * Sets value of 'seed' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setSeed($value)
    {
        return $this->set(self::SEED, $value);
    }

    /**
     * Returns value of 'seed' property
     *
     * @return string
     */
    public function getSeed()
    {
        return $this->get(self::SEED);
    }
}

/**
 * SenderSigningKey message embedded in SenderKeyStateStructure message
 */
class Textsecure_SenderKeyStateStructure_SenderSigningKey extends \ProtobufMessage
{
    /* Field index constants */
    const _PUBLIC = 1;
    const _PRIVATE = 2;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::_PUBLIC => array(
            'name' => 'public',
            'required' => false,
            'type' => 7,
        ),
        self::_PRIVATE => array(
            'name' => 'private',
            'required' => false,
            'type' => 7,
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::_PUBLIC] = null;
        $this->values[self::_PRIVATE] = null;
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'public' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setPublic($value)
    {
        return $this->set(self::_PUBLIC, $value);
    }

    /**
     * Returns value of 'public' property
     *
     * @return string
     */
    public function getPublic()
    {
        return $this->get(self::_PUBLIC);
    }

    /**
     * Sets value of 'private' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setPrivate($value)
    {
        return $this->set(self::_PRIVATE, $value);
    }

    /**
     * Returns value of 'private' property
     *
     * @return string
     */
    public function getPrivate()
    {
        return $this->get(self::_PRIVATE);
    }
}

/**
 * SenderKeyStateStructure message
 */
class Textsecure_SenderKeyStateStructure extends \ProtobufMessage
{
    /* Field index constants */
    const SENDERKEYID = 1;
    const SENDERCHAINKEY = 2;
    const SENDERSIGNINGKEY = 3;
    const SENDERMESSAGEKEYS = 4;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::SENDERKEYID => array(
            'name' => 'senderKeyId',
            'required' => false,
            'type' => 5,
        ),
        self::SENDERCHAINKEY => array(
            'name' => 'senderChainKey',
            'required' => false,
            'type' => 'Textsecure_SenderKeyStateStructure_SenderChainKey'
        ),
        self::SENDERSIGNINGKEY => array(
            'name' => 'senderSigningKey',
            'required' => false,
            'type' => 'Textsecure_SenderKeyStateStructure_SenderSigningKey'
        ),
        self::SENDERMESSAGEKEYS => array(
            'name' => 'senderMessageKeys',
            'repeated' => true,
            'type' => 'Textsecure_SenderKeyStateStructure_SenderMessageKey'
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::SENDERKEYID] = null;
        $this->values[self::SENDERCHAINKEY] = null;
        $this->values[self::SENDERSIGNINGKEY] = null;
        $this->values[self::SENDERMESSAGEKEYS] = array();
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'senderKeyId' property
     *
     * @param int $value Property value
     *
     * @return null
     */
    public function setSenderKeyId($value)
    {
        return $this->set(self::SENDERKEYID, $value);
    }

    /**
     * Returns value of 'senderKeyId' property
     *
     * @return int
     */
    public function getSenderKeyId()
    {
        return $this->get(self::SENDERKEYID);
    }

    /**
     * Sets value of 'senderChainKey' property
     *
     * @param Textsecure_SenderKeyStateStructure_SenderChainKey $value Property value
     *
     * @return null
     */
    public function setSenderChainKey(Textsecure_SenderKeyStateStructure_SenderChainKey $value)
    {
        return $this->set(self::SENDERCHAINKEY, $value);
    }

    /**
     * Returns value of 'senderChainKey' property
     *
     * @return Textsecure_SenderKeyStateStructure_SenderChainKey
     */
    public function getSenderChainKey()
    {
        return $this->get(self::SENDERCHAINKEY);
    }

    /**
     * Sets value of 'senderSigningKey' property
     *
     * @param Textsecure_SenderKeyStateStructure_SenderSigningKey $value Property value
     *
     * @return null
     */
    public function setSenderSigningKey(Textsecure_SenderKeyStateStructure_SenderSigningKey $value)
    {
        return $this->set(self::SENDERSIGNINGKEY, $value);
    }

    /**
     * Returns value of 'senderSigningKey' property
     *
     * @return Textsecure_SenderKeyStateStructure_SenderSigningKey
     */
    public function getSenderSigningKey()
    {
        return $this->get(self::SENDERSIGNINGKEY);
    }

    /**
     * Appends value to 'senderMessageKeys' list
     *
     * @param Textsecure_SenderKeyStateStructure_SenderMessageKey $value Value to append
     *
     * @return null
     */
    public function appendSenderMessageKeys(Textsecure_SenderKeyStateStructure_SenderMessageKey $value)
    {
        return $this->append(self::SENDERMESSAGEKEYS, $value);
    }

    /**
     * Clears 'senderMessageKeys' list
     *
     * @return null
     */
    public function clearSenderMessageKeys()
    {
        return $this->clear(self::SENDERMESSAGEKEYS);
    }

    /**
     * Returns 'senderMessageKeys' list
     *
     * @return Textsecure_SenderKeyStateStructure_SenderMessageKey[]
     */
    public function getSenderMessageKeys()
    {
        return $this->get(self::SENDERMESSAGEKEYS);
    }

    /**
     * Returns 'senderMessageKeys' iterator
     *
     * @return ArrayIterator
     */
    public function getSenderMessageKeysIterator()
    {
        return new \ArrayIterator($this->get(self::SENDERMESSAGEKEYS));
    }

    /**
     * Returns element from 'senderMessageKeys' list at given offset
     *
     * @param int $offset Position in list
     *
     * @return Textsecure_SenderKeyStateStructure_SenderMessageKey
     */
    public function getSenderMessageKeysAt($offset)
    {
        return $this->get(self::SENDERMESSAGEKEYS, $offset);
    }

    /**
     * Returns count of 'senderMessageKeys' list
     *
     * @return int
     */
    public function getSenderMessageKeysCount()
    {
        return $this->count(self::SENDERMESSAGEKEYS);
    }
}

/**
 * SenderKeyRecordStructure message
 */
class Textsecure_SenderKeyRecordStructure extends \ProtobufMessage
{
    /* Field index constants */
    const SENDERKEYSTATES = 1;

    /* @var array Field descriptors */
    protected static $fields = array(
        self::SENDERKEYSTATES => array(
            'name' => 'senderKeyStates',
            'repeated' => true,
            'type' => 'Textsecure_SenderKeyStateStructure'
        ),
    );

    /**
     * Constructs new message container and clears its internal state
     *
     * @return null
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::SENDERKEYSTATES] = array();
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Appends value to 'senderKeyStates' list
     *
     * @param Textsecure_SenderKeyStateStructure $value Value to append
     *
     * @return null
     */
    public function appendSenderKeyStates(Textsecure_SenderKeyStateStructure $value)
    {
        return $this->append(self::SENDERKEYSTATES, $value);
    }

    /**
     * Clears 'senderKeyStates' list
     *
     * @return null
     */
    public function clearSenderKeyStates()
    {
        return $this->clear(self::SENDERKEYSTATES);
    }

    /**
     * Returns 'senderKeyStates' list
     *
     * @return Textsecure_SenderKeyStateStructure[]
     */
    public function getSenderKeyStates()
    {
        return $this->get(self::SENDERKEYSTATES);
    }

    /**
     * Returns 'senderKeyStates' iterator
     *
     * @return ArrayIterator
     */
    public function getSenderKeyStatesIterator()
    {
        return new \ArrayIterator($this->get(self::SENDERKEYSTATES));
    }

    /**
     * Returns element from 'senderKeyStates' list at given offset
     *
     * @param int $offset Position in list
     *
     * @return Textsecure_SenderKeyStateStructure
     */
    public function getSenderKeyStatesAt($offset)
    {
        return $this->get(self::SENDERKEYSTATES, $offset);
    }

    /**
     * Returns count of 'senderKeyStates' list
     *
     * @return int
     */
    public function getSenderKeyStatesCount()
    {
        return $this->count(self::SENDERKEYSTATES);
    }
}
