<?php

interface axolotlInterface
{
    //PreKeys

    public function storePreKey($prekeyId, $preKeyRecord);

    public function loadPreKey($preKeyId);

    public function loadPreKeys();

    public function containsPreKey($preKeyId);

    public function removePreKey($preKeyId);

    public function removeAllPreKeys();

    //signedPreKey

    public function storeSignedPreKey($signedPreKeyId, $signedPreKeyRecord);

    public function loadSignedPreKey($signedPreKeyId);

    public function loadSignedPreKeys();

    public function removeSignedPreKey($signedPreKeyId);

    public function containsSignedPreKey($signedPreKeyId);

    //identity

    public function storeLocalData($registrationId, $identityKeyPair);

    public function getIdentityKeyPair();

    public function getLocalRegistrationId();

    public function isTrustedIdentity($recipientId, $identityKey);

    public function saveIdentity($recipientId, $identityKey);

    //session

    public function storeSession($recipientId, $deviceId, $sessionRecord);

    public function loadSession($recipientId, $deviceId);

    public function getSubDeviceSessions($recipientId);

    public function containsSession($recipientId, $deviceId);

    public function deleteSession($recipientId, $deviceId);

    public function deleteAllSessions($recipientId);

    //sender_keys

    public function storeSenderKey($senderKeyId, $senderKeyRecord);

    public function loadSenderKey($senderKeyId);

    public function removeSenderKey($senderKeyId);

    public function containsSenderKey($senderKeyId);

    public function clear();
}

class axolotlSqliteStore implements axolotlInterface
{
    const DATA_FOLDER = 'wadata';

    private $db;
    private $filename;

    public function __construct($number, $customPath = null)
    {
        if ($customPath) {
            $this->fileName = $customPath.'axolotl-'.$number.'.db';
        } else {
            $this->fileName = __DIR__.DIRECTORY_SEPARATOR.self::DATA_FOLDER.DIRECTORY_SEPARATOR.'axolotl-'.$number.'.db';
        }

        $this->create();
    }

    protected function create()
    {
        $createTable = !file_exists($this->fileName);

        $this->db = new \PDO('sqlite:'.$this->fileName, null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($createTable) {
            //create necesary tables before starting
        $this->db->exec('CREATE TABLE IF NOT EXISTS identities
                          (
                            `_id` INTEGER PRIMARY KEY AUTOINCREMENT,
                            `recipient_id` INTEGER UNIQUE,
                            `registration_id` INTEGER,
                            `public_key` BLOB,
                            `private_key` BLOB,
                            `next_prekey_id` INTEGER,
                            `timestamp` INTEGER
                          );');
            $this->db->exec('CREATE TABLE IF NOT EXISTS prekeys
                          (
                            `_id` INTEGER PRIMARY KEY AUTOINCREMENT,
                            `prekey_id` INTEGER UNIQUE,
                            `sent_to_server` BOOLEAN,
                            `record` BLOB
                          );');
        //$this->db->exec('CREATE TABLE IF NOT EXISTS sender_keys
        //                (`group_id` TEXT, sender_id TEXT, record TEXT)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS sessions
                         (
                            `_id` INTEGER PRIMARY KEY AUTOINCREMENT,
                            `recipient_id` INTEGER UNIQUE,
                            `device_id` INTEGER,
                            `record` BLOB,
                            `timestamp` INTEGER
                         );');
            $this->db->exec('CREATE TABLE IF NOT EXISTS signed_prekeys
                        (
                            `_id` INTEGER PRIMARY KEY AUTOINCREMENT,
                            `prekey_id` INTEGER UNIQUE,
                            `timestamp` INTEGER,
                            `record` BLOB
                        );');
            $this->db->exec('CREATE TABLE IF NOT EXISTS sender_keys
                        (
                            `_id` INTEGER PRIMARY KEY AUTOINCREMENT,
                            `sender_key_id` TEXT UNIQUE,
                            `record` BLOB
                        );');
        }
    }

    //prekeys

    public function storePreKey($prekeyId, $record)
    {
        $sql = 'INSERT INTO prekeys (`prekey_id`, `record`) VALUES (:prekey_id, :record)';
        $query = $this->db->prepare($sql);

        $query->execute(
            [
                ':prekey_id' => $prekeyId,
                ':record'    => $record->serialize(),
            ]
        );
    }

    public function storePreKeys($keys)
    {
        $this->db->beginTransaction();
        foreach ($keys as $key) {
            $this->storePreKey($key->getId(), $key);
        }
        $this->db->commit();
    }

    public function loadPreKey($preKeyId)
    {
        $sql = 'SELECT `record` FROM prekeys where `prekey_id` = :id';
        $query = $this->db->prepare($sql);

        $query->execute(
            [
                    ':id' => $preKeyId,
                ]
        );
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row == null || $row === false) {
            throw new Exception('No such prekey with id: '.$preKeyId);
        }

        return new PreKeyRecord(null, null, $row['record']);
    }

    public function loadPreKeys()
    {
        $sql = 'SELECT `record` FROM prekeys';
        $query = $this->db->prepare($sql);

        $query->execute();
        $prekeys = [];
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            if ($row != null && $row !== false) {
                $prekeys[] = $row['record'];
            }
        }

        return $prekeys;
    }

    public function containsPreKey($preKeyId)
    {
        $sql = 'SELECT `record` FROM prekeys where `prekey_id` = :id';
        $query = $this->db->prepare($sql);

        $query->execute(
            [
                    ':id' => $preKeyId,
                ]
        );
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row == null || $row === false) {
            return false;
        }

        return true;
    }

    public function removePreKey($preKeyId)
    {
        $sql = 'DELETE FROM prekeys WHERE `prekey_id` = :id';
        $query = $this->db->prepare($sql);
        $query->execute(
            [
                    ':id' => $preKeyId,
                ]
        );
    }

    public function removeAllPreKeys()
    {
        $sql = 'DELETE FROM prekeys';
        $query = $this->db->prepare($sql);
        $query->execute();
    }

    //signedPreKey

    public function loadSignedPreKey($signedPreKeyId)
    {
        $sql = 'SELECT `record` FROM signed_prekeys where `prekey_id` = :id';
        $query = $this->db->prepare($sql);

        $query->execute(
            [
                    ':id' => $signedPreKeyId,
                ]
        );
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row == null || $row === false) {
            throw new Exception('No such signedprekey with id: '.$signedPreKeyId);
        }

        return new SignedPreKeyRecord(null, null, null, null, $row['record']);
    }

    public function loadSignedPreKeys()
    {
        $sql = 'SELECT `record` FROM signed_prekeys';
        $query = $this->db->prepare($sql);

        $query->execute();
        $keys = [];
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            if ($row != null && $row !== false) {
                $keys[] = new SignedPreKeyRecord(null, null, null, null, $row['record']);
            }
        }

        return $keys;
    }

    public function storeSignedPreKey($signedPreKeyId, $signedPreKeyRecord)
    {
        $sql = 'INSERT INTO signed_prekeys (`prekey_id`, `record`) VALUES (:prekey_id, :record)';
        $query = $this->db->prepare($sql);

        $query->execute(
          [
              ':prekey_id' => $signedPreKeyId,
              ':record'    => $signedPreKeyRecord->serialize(),
          ]
      );
    }

    public function removeSignedPreKey($signedPreKeyId)
    {
        $sql = 'DELETE FROM signed_prekeys WHERE `prekey_id` = :id';
        $query = $this->db->prepare($sql);
        $query->execute(
          [
                  ':id' => $signedPreKeyId,
              ]
      );
    }

    public function containsSignedPreKey($signedPreKeyId)
    {
        $sql = 'SELECT `record` FROM signed_prekeys where `prekey_id` = :id';
        $query = $this->db->prepare($sql);

        $query->execute(
          [
                  ':id' => $signedPreKeyId,
              ]
      );
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row == null || $row === false) {
            return false;
        }

        return true;
    }

    //identity

    public function getIdentityKeyPair()
    {
        $sql = 'SELECT `public_key`, `private_key` FROM identities where recipient_id = -1';
        $query = $this->db->prepare($sql);

        $query->execute();
        $row = $query->fetch(PDO::FETCH_ASSOC);

        if ($row != null && $row !== false) {
            $keys = new IdentityKeyPair(
                                      new IdentityKey(new DjbECPublicKey(substr($row['public_key'], 1))),
                                      new DjbECPrivateKey($row['private_key'])
                                   );
        } else {

        //this should not happen
        $keys = null;
        }

        return $keys;
    }

    public function getLocalRegistrationId()
    {
        $sql = 'SELECT `registration_id` FROM identities WHERE recipient_id = -1';
        $query = $this->db->prepare($sql);

        $query->execute();
        $row = $query->fetch(PDO::FETCH_ASSOC);

        if ($row != null && $row !== false) {
            $localRegistrationId = $row['registration_id'];
        } else {
            $localRegistrationId = null;
        }

        return $localRegistrationId;
    }

    public function storeLocalData($registrationId, $identityKeyPair)
    {
        $sql = 'INSERT OR REPLACE INTO identities(recipient_id, registration_id, public_key, private_key)
              VALUES (:recipient_id, :registration_id, :public_key, :private_key)';
        $query = $this->db->prepare($sql);

        $query->execute(
          [
              ':recipient_id'    => -1,
              ':registration_id' => $registrationId,
              ':public_key'      => $identityKeyPair->getPublicKey()->serialize(), //this should be tested, identityKeyPair.getPublicKey().getPublicKey().serialize()
              ':private_key'     => $identityKeyPair->getPrivateKey()->serialize(),
          ]
      );
    }

    public function clearRecipient($recipientId)
    {
        $sql = 'DELETE FROM identities where recipient_id = :recipient_id';
        $query = $this->db->prepare($sql);

        $query->execute(
        [
              ':recipient_id' => $recipientId,
          ]
      );
        $sql = 'DELETE FROM sessions where recipient_id = :recipient_id';
        $query = $this->db->prepare($sql);

        $query->execute(
        [
              ':recipient_id' => $recipientId,
          ]
      );
    }

    public function isTrustedIdentity($recipientId, $identityKey)
    {
      /*
        $sql = 'SELECT public_key from identities WHERE recipient_id = :recipient_id';
        $query = $this->db->prepare($sql);

        $query->execute(
        [
              ':recipient_id' => $recipientId,
          ]
      );
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row == null || $row === false) {
            return true;
        }

        return $row['public_key'] == $identityKey->getPublicKey()->serialize();
        */
        return true;
    }

    public function saveIdentity($recipientId, $identityKey)
    {
        $sql = 'DELETE FROM identities WHERE recipient_id = :recipient_id';
        $query = $this->db->prepare($sql);

        $query->execute(
          [
              ':recipient_id' => $recipientId,
          ]
      );

        $sql = 'INSERT INTO identities (recipient_id, public_key) VALUES(:recipient_id, :public_key)';
        $query = $this->db->prepare($sql);

        $query->execute(
          [
              ':recipient_id' => $recipientId,
              ':public_key'   => $identityKey->getPublicKey()->serialize(),
          ]
      );
    }

    //session

    public function loadSession($recipientId, $deviceId)
    {
        $sql = 'SELECT record FROM sessions WHERE recipient_id = :recipient_id AND device_id = :device_id';
        $query = $this->db->prepare($sql);

        $query->execute(
          [
              ':recipient_id' => $recipientId,
              ':device_id'    => $deviceId,
          ]
      );
        $row = $query->fetch(PDO::FETCH_ASSOC);

        if ($row != null && $row !== false) {
            $SessionRecord = new SessionRecord(null, $row['record']);
        } else {
            $SessionRecord = new SessionRecord();
        }

        return $SessionRecord;
    }

    public function getSubDeviceSessions($recipientId)
    {
        $sql = 'SELECT device_id from sessions WHERE recipient_id = :recipient_id';
        $query = $this->db->prepare($sql);

        $query->execute(
          [
              ':recipient_id' => $recipientId,
          ]
      );
        $deviceIds = [];
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $deviceIds[] = $row['device_id'];
        }

        return $deviceIds;
    }

    public function storeSession($recipientId, $deviceId, $sessionRecord)
    {
        $this->deleteSession($recipientId, 1);
        $sql = 'INSERT INTO sessions(recipient_id, device_id, record) VALUES (:recipient_id, :device_id, :record)';
        $query = $this->db->prepare($sql);

        $query->execute(
          [
              ':recipient_id' => $recipientId,
              ':device_id'    => $deviceId,
              ':record'       => $sessionRecord->serialize(),
          ]
      );
    }

    public function containsSession($recipientId, $deviceId)
    {
        $sql = 'SELECT record FROM sessions WHERE recipient_id = :recipient_id AND device_id = :device_id';
        $query = $this->db->prepare($sql);

        $query->execute(
          [
                  ':recipient_id' => $recipientId,
                  ':device_id'    => $deviceId,
              ]
      );
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row == null || $row === false) {
            return false;
        }

        return true;
    }

    public function deleteSession($recipientId, $deviceId)
    {
        $sql = 'DELETE FROM sessions WHERE recipient_id = :recipient_id AND device_id = :device_id';
        $query = $this->db->prepare($sql);
        $query->execute(
          [
                  ':recipient_id' => $recipientId,
                  ':device_id'    => $deviceId,
              ]
      );
    }

    public function deleteAllSessions($recipientId)
    {
        $sql = 'DELETE FROM sessions WHERE recipient_id = :recipient_id';
        $query = $this->db->prepare($sql);
        $query->execute(
          [
                  ':recipient_id' => $recipientId,
          ]
      );
    }

    //sender_keys

    public function storeSenderKey($senderKeyId, $senderKeyRecord)
    {
        $this->removeSenderKey($senderKeyId);
        $sql = 'INSERT INTO sender_keys(sender_key_id, record) VALUES (:sender_key_id, :record)';
        $query = $this->db->prepare($sql);

        $query->execute(
          [
              ':sender_key_id' => $senderKeyId,
              ':record'        => $senderKeyRecord->serialize(),
          ]
      );
    }

    public function removeSenderKey($senderKeyId)
    {
        $sql = 'DELETE FROM sender_keys where sender_key_id = :sender_key_id';
        $query = $this->db->prepare($sql);

        $query->execute(
          [
              ':sender_key_id' => $senderKeyId,
          ]
      );
    }

    public function loadSenderKey($senderKeyId)
    {
        $sql = 'SELECT record FROM sender_keys WHERE sender_key_id = :sender_key_id';
        $query = $this->db->prepare($sql);

        $query->execute(
          [
              ':sender_key_id' => $senderKeyId,
          ]
      );
        $row = $query->fetch(PDO::FETCH_ASSOC);
        $record = new SenderKeyRecord();
        if ($row != null && $row !== false) {
            $record = new SenderKeyRecord($row['record']);
        }

        return $record;
    }

    public function containsSenderKey($senderKeyId)
    {
        $sql = 'SELECT record FROM sender_keys WHERE sender_key_id = :sender_key_id';
        $query = $this->db->prepare($sql);

        $query->execute(
          [
              ':sender_key_id' => $senderKeyId,
          ]
      );
        $row = $query->fetch(PDO::FETCH_ASSOC);

        if ($row === null && $row === false) {
            return false;
        }

        return true;
    }

    public function clear()
    {
        if (file_exists($this->filename)) {
            unlink($this->filename);
        }
        $this->create();
    }
}
