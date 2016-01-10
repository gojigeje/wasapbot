<?php

interface MessageStoreInterface
{
    public function saveMessage($from, $to, $txt, $id, $t);
}

class SqliteMessageStore implements MessageStoreInterface
{
    const DATA_FOLDER = 'wadata';

    private $db;

    public function __construct($number)
    {
        $fileName = __DIR__.DIRECTORY_SEPARATOR.self::DATA_FOLDER.DIRECTORY_SEPARATOR.'msgstore-'.$number.'.db';
        $createTable = !file_exists($fileName);

        $this->db = new \PDO('sqlite:'.$fileName, null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($createTable) {
            $this->db->exec('CREATE TABLE messages (`from` TEXT, `to` TEXT, message TEXT, id TEXT, t TEXT)');
            $this->db->exec('CREATE TABLE messages_pending(`id` TEXT PRIMARY KEY,`jid` TEXT, `pending` TINYINT(1) DEFAULT 0)');
        } else {
            //backward compatibility
            $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='messages_pending';")->fetchAll();
            if ($result == null || $result == false || count($result) == 0) {
                $this->db->exec('CREATE TABLE messages_pending(`id` TEXT PRIMARY KEY,`jid` TEXT, `pending` TINYINT(1) DEFAULT 0)');
            }
        }
    }

    public function saveMessage($from, $to, $txt, $id, $t)
    {
        $sql = 'INSERT INTO messages (`from`, `to`, message, id, t) VALUES (:from, :to, :message, :messageId, :t)';
        $query = $this->db->prepare($sql);

        $query->execute(
            [
                ':from'      => $from,
                ':to'        => $to,
                ':message'   => $txt,
                ':messageId' => $id,
                ':t'         => $t,
            ]
        );
    }

    public function setPending($id, $jid)
    {
        $sql = 'UPDATE  messages_pending set `pending` = 1, `jid` = :jid where `id` = :id';
        $query = $this->db->prepare($sql);
        $query->execute(
            [
                    ':id'  => $id,
                    ':jid' => $jid,
                ]
        );
        $sql = 'INSERT OR IGNORE into messages_pending(`id`,`jid`, `pending`) VALUES(:id,:jid,1)';
        $query = $this->db->prepare($sql);
        $query->execute(
            [
                    ':id'  => $id,
                    ':jid' => $jid,
                ]
        );
    }

    public function getPending($jid)
    {
        $sql = 'SELECT `id` from messages_pending where `jid` = :jid and `pending` = 1';
        $query = $this->db->prepare($sql);
        $query->execute(
            [
                ':jid' => $jid,
            ]
        );
        $pending_ids = [];
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            if ($row != null && $row !== false) {
                $pending_ids[] = $row['id'];
            }
        }
        if (count($pending_ids) == 0) {
            return [];
        }
        $messages = [];
        $qMarks = str_repeat('?,', count($pending_ids) - 1).'?';
        $sql = "SELECT * from messages where `id` IN ($qMarks)";
        $query = $this->db->prepare($sql);
        $query->execute($pending_ids);
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            if ($row != null && $row !== false) {
                $messages[] = $row;
            }
        }
        $sql = 'DELETE FROM messages_pending  where `pending` = 1 and jid = :jid';
        $query = $this->db->prepare($sql);
        $query->execute([':jid' => $jid]);

        return $messages;
    }
}
