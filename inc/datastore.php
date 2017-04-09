<?php
define('DS_STATUS_QUEUED',0);
define('DS_STATUS_RESIZE_ERROR',-1);
define('DS_STATUS_UPLOAD_ERROR',-2);
define('DS_STATUS_RESIZING',1);
define('DS_STATUS_RESIZED',2);
define('DS_STATUS_UPLOADING',3);
define('DS_STATUS_UPLOADED',4);
interface Datastore{

    /**
     * Test if datastorage is ready
     * @return boolean
     */
    public function is_ready();

    /**
     * Prepare data storage for usage. Apply migration, create tables, etc.
     */
    public function initiate_datastore();

    /**
     * Initiate connection
     * @return mixed
     */
    public function get_connection();

    /**
     * Save queue into data storage
     * @param $queue array of paths to images
     * @return mixed
     */
    public function save_queue(array $queue);
    
    /**
     * Get resize queue
     * @param $queue array of paths to images
     * @return mixed
     */
    public function get_queue($count = null);
    
    /**
     * Get upload queue
     * @param $queue array of paths to images
     * @return mixed
     */
    public function get_upload_queue($count = null);
    
    /**
     * Move failed resizing tasks to queue
     * @param $queue array of paths to images
     * @return mixed
     */
    public function retry_failed_resizing($count = null);

    /**
     * Lock file during it's resizing
     * @param $filename
     * @param $before_status
     * @param $new_status
     * @return boolean TRUE if lock created, FALSE if it's already locked
     */
    public function lock_file($filename, $before_status, $new_status);

    /**
     * Mark file as resized
     * @param $filename
     */
    public function file_resized($filename);
    
    /**
     * Mark file as uploaded
     * @param $filename
     */
    public function file_uploaded($filename);
    
    /**
     * Mark file as failed resize
     * @param $filename
     */
    public function failed_resize($filename,$error_msg);

    /**
     * Mark file as failed upload
     * @param $filename
     */
    public function failed_upload($filename,$error_msg);

    /**
     * Count of done tasks
     * @return int
     */
    public function get_done_count();
    
    /**
     * Count of failed tasks
     * @return int
     */
    public function get_failed_count();
}