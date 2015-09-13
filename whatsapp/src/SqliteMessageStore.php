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
        $fileName = __DIR__ . DIRECTORY_SEPARATOR . self::DATA_FOLDER . DIRECTORY_SEPARATOR . 'msgstore-'.$number.'.db';
        $createTable = !file_exists($fileName);

        $this->db = new \PDO("sqlite:" . $fileName, null, null, array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        if ($createTable)
        {
            $this->db->exec('CREATE TABLE messages (`from` TEXT, `to` TEXT, message TEXT, id TEXT, t TEXT)');
        }
    }

    public function saveMessage($from, $to, $txt, $id, $t)
    {
        $sql = 'INSERT INTO messages (`from`, `to`, message, id, t) VALUES (:from, :to, :message, :messageId, :t)';
        $query = $this->db->prepare($sql);

        $query->execute(
            array(
                ':from' => $from,
                ':to' => $to,
                ':message' => $txt,
                ':messageId' => $id,
                ':t' => $t
            )
        );
    }
}
