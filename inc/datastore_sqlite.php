<?php

/**
 * Class DatastorageSqlite
 * SQLite datastore driver
 */
class DatastoreSqlite implements Datastore
{
    private static $filename = 'ds.sqlite';
    /** @var  SQLite3 */
    private static $conn;

    public function is_ready()
    {
        return is_file(APP_PATH . DIRECTORY_SEPARATOR . self::$filename);
    }

    public function initiate_datastore()
    {
        echo "Initiate SQLite DB\n";
        if ($db = new SQLite3(APP_PATH . DIRECTORY_SEPARATOR . self::$filename)) {
            $db->exec('CREATE TABLE queue (file VARCHAR (255), status SMALLINT DEFAULT 0, error VARCHAR (255) NULL)');
        } else {
            throw new Exception('Failed to initialise SQLite3 db');
        }
        self::$conn = $db;
    }

    /**
     * @return SQLite3
     * @throws Exception
     */
    public function get_connection()
    {
        if (self::$conn == null) {
            self::$conn = new SQLite3(APP_PATH . DIRECTORY_SEPARATOR . self::$filename);
            if (!self::$conn) {
                throw new Exception('Failed SQLite3 connection');
            }
        }
        return self::$conn;
    }

    public function save_queue(array $queue)
    {
        $statement = $this->get_connection()->prepare("INSERT INTO queue (file, status) VALUES (:file, " . DS_STATUS_QUEUED . ")");
        foreach ($queue as $file) {
            $statement->bindValue(':file', $file);
            $statement->execute();
        }
    }

    public function get_queue($count = null)
    {
        $query = 'SELECT file FROM queue WHERE status = ' . DS_STATUS_QUEUED;
        if ($count) {
            $count = preg_replace('/[^0-9]/', '', $count);
            if ($count > 0) {
                $query .= ' LIMIT ' . $count;
            }
        }
        $results = $this->get_connection()->query($query);
        $queue = array();
        while ($row = $results->fetchArray()) {
            $queue[] = $row['file'];
        }
        return $queue;
    }

    public function get_upload_queue($count = null)
    {
        $query = 'SELECT file FROM queue WHERE status = ' . DS_STATUS_RESIZED;
        if ($count) {
            $count = preg_replace('/[^0-9]/', '', $count);
            if ($count > 0) {
                $query .= ' LIMIT ' . $count;
            }
        }
        $results = $this->get_connection()->query($query);
        $queue = array();
        while ($row = $results->fetchArray()) {
            $queue[] = $row['file'];
        }
        return $queue;
    }

    public function lock_file($filename, $before_status, $new_status)
    {
        $result = $this->get_connection()->query('UPDATE queue SET status = ' . $new_status . ' WHERE file LIKE \'' . $filename . '\' AND status = ' . $before_status);
        return $result;

    }

    public function file_resized($filename)
    {
        $this->get_connection()->exec('UPDATE queue SET status = ' . DS_STATUS_RESIZED . ' WHERE file LIKE \'' . $filename . '\' AND status = ' . DS_STATUS_RESIZING);
    }

    public function file_uploaded($filename)
    {
        $this->get_connection()->exec('UPDATE queue SET status = ' . DS_STATUS_UPLOADED . ' WHERE file LIKE \'' . $filename . '\' AND status = ' . DS_STATUS_UPLOADING);
    }

    public function failed_resize($filename, $error_msg)
    {
        $this->get_connection()->exec('UPDATE queue SET status = ' . DS_STATUS_RESIZE_ERROR . ', error = \'' . $error_msg . '\' WHERE file LIKE \'' . $filename . '\'');
    }

    public function failed_upload($filename, $error_msg)
    {
        $this->get_connection()->exec('UPDATE queue SET status = ' . DS_STATUS_UPLOAD_ERROR . ', error = \'' . $error_msg . '\' WHERE file LIKE \'' . $filename . '\'');
    }

    public function retry_failed_resizing($count = null)
    {
        $query = 'UPDATE queue SET status = ' . DS_STATUS_QUEUED . ' WHERE status = ' . DS_STATUS_RESIZE_ERROR;
        if ($count) {
            $count = preg_replace('/[^0-9]/', '', $count);
            if ($count > 0) {
                $query .= ' LIMIT ' . $count;
            }
        }
        $this->get_connection()->exec($query);
    }

    public function get_done_count()
    {
        $result = $this->get_connection()->query('SELECT COUNT(*) as cnt FROM queue WHERE status = ' . DS_STATUS_UPLOADED);
        $row = $result->fetchArray();
        return $row['cnt'];
    }

    public function get_failed_count()
    {
        $result = $this->get_connection()->query('SELECT COUNT(*) as cnt FROM queue WHERE status < ' . DS_STATUS_QUEUED);
        $row = $result->fetchArray();
        return $row['cnt'];
    }


}