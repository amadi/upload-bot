<?php

/**
 * CLI support functions
 */
class BotCLI
{
    private static $config = null;

    /** @var Datastore */
    private static $ds = null;

    public static function parse_argv(array $argv)
    {
        if (empty($argv) || count($argv) < 2) {
            self::print_help();
            exit;
        }
        $method_name = 'cmd_' . $argv[1];
        if (!method_exists('BotCLI', $method_name)) {
            self::print_help();
        } else {
            self::$method_name($argv);
        }
    }

    private static function print_help()
    {
        echo "Uploader Bot\n";
        echo "Usage:\n";
        echo "\tcommand [arguments]\n";
        echo "Available commands:\n";
        $rc = new ReflectionClass('BotCLI');
        $methods = $rc->getMethods();
        /** @var ReflectionMethod $m */
        foreach ($methods as $m) {
            if (strstr($m->getName(), 'cmd_')) {
                if (preg_match('/@common_help\s(.+)/', $m->getDocComment(), $matches)) {
                    printf("\t%s\t%s\n", str_pad(str_replace('cmd_', '', $m->getName()), 15, ' ', STR_PAD_RIGHT),
                        $matches[1]);
                }
            }
        }
    }

    /**
     * @common_help Add filenames to resize queue
     * @param $path
     */
    private static function cmd_schedule($args)
    {
        if (!isset($args[2])) {
            echo "Usage:\n";
            echo "bot resize [-n <count>]\n";
            exit;
        }
        $path = $args[2];
        if ($path[0] != '/') {
            $path = APP_PATH . DIRECTORY_SEPARATOR . rtrim($path, DIRECTORY_SEPARATOR);
        }
        if (!is_dir($path)) {
            throw new Exception('Incorrect path');
        }
        $raw_files = scandir($path);
        $files = array();
        foreach ($raw_files as $f) {
            if ($f == '.' || $f == '..') {
                continue;
            } elseif (preg_match('/\.(jpeg|jpg|png)$/', $f)) {
                $files[] = $path . DIRECTORY_SEPARATOR . $f;
            }
        }
        if (count($files) == 0) {
            throw new Exception('No images found in path ' . $path);
        }
        $ds = self::getDatastore();
        $ds->save_queue($files);
    }

    /**
     * @common_help Resize next images from the queue
     * @param $count
     */
    private static function cmd_resize($args)
    {
        $count = null;
        if (isset($args[2]) && $args[2] == '-n' && isset($args[3])) {
            $count = $args[3];
        }
        $ds = self::getDatastore();
        $queue = $ds->get_queue($count);
        $output = APP_PATH . DIRECTORY_SEPARATOR . self::get_config('output_dir') . DIRECTORY_SEPARATOR;
        if (!is_dir($output)) {
            mkdir($output);
        }
        $image_processor = new Image();

        foreach ($queue as $image_file) {
            if ($ds->lock_file($image_file, DS_STATUS_QUEUED, DS_STATUS_RESIZING)) {
                try {
                    $image_processor->load($image_file);
                    $image_processor->resize(640, 640);
                    $image_processor->save($output . basename($image_file));

                    // Verify resized file
                    if (!is_file($output . basename($image_file))) {
                        $ds->failed_resize($image_file, 'Not resized');
                    } else {
                        $ds->file_resized($image_file);
                        unlink($image_file);
                    }
                } catch (Exception $e) {
                    $ds->failed_resize($image_file, 'Error resizing: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * @common_help Output current tasks stats
     * @param $count
     */
    private static function cmd_status($count = null)
    {
        echo "Images Processor Bot\n";
        $ds = self::getDatastore();
        $resize_queue = count($ds->get_queue());
        $upload_queue = count($ds->get_upload_queue());
        $done = $ds->get_done_count();
        $failed = $ds->get_failed_count();
        printf("Queue\tCount\n");
        printf("resize\t%s\n", $resize_queue);
        printf("upload\t%s\n", $upload_queue);
        printf("done\t%s\n", $done);
        printf("failed\t%s\n", $failed);
    }

    /**
     * @common_help Upload next images to remote storage
     * @param $count
     */
    private static function cmd_upload($args)
    {
        $count = null;
        if (isset($args[2]) && $args[2] == '-n' && isset($args[3])) {
            $count = $args[3];
        }
        $ds = self::getDatastore();
        $queue = $ds->get_upload_queue($count);
        $output = APP_PATH . DIRECTORY_SEPARATOR . self::get_config('output_dir') . DIRECTORY_SEPARATOR;

        $client = new GoogleApiClient(self::get_config('client_secret_path'),
            self::get_config('google_credentials_path'));

        foreach ($queue as $image_file) {
            if ($ds->lock_file($image_file, DS_STATUS_RESIZED, DS_STATUS_UPLOADING)) {
                try {
                    $file_to_upload = $output . basename($image_file);
                    $client->upload_file($file_to_upload);
                    $ds->file_uploaded($image_file);
                } catch (Exception $e) {
                    $ds->failed_upload($image_file, 'Error uploading: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * @param $count
     */
    private static function cmd_retry($args)
    {
        $count = null;
        if (isset($args[2]) && $args[2] == '-n' && isset($args[3])) {
            $count = $args[3];
        }
        $ds = self::getDatastore();
        $ds->retry_failed_resizing($count);
    }

    private static function get_config($param = null)
    {
        if (self::$config == null) {
            self::$config = include(APP_PATH . DIRECTORY_SEPARATOR . 'config.php');
        }
        if ($param == null) {
            return self::$config;
        } else {
            if (isset(self::$config[$param])) {
                return self::$config[$param];
            } else {
                throw new Exception('Parameter \'' . $param . '\' is not found in app config');
            }
        }
    }

    /**
     * @return Datastore
     * @throws Exception
     */
    private static function getDatastore()
    {
        if (self::$ds == null) {
            $provider_name = self::get_config('data_provider');
            require_once(INCLUDE_PATH . DIRECTORY_SEPARATOR . 'datastore_' . $provider_name . '.php');
            $class = 'Datastore' . ucfirst($provider_name);
            self::$ds = new $class();
        }
        if (!self::$ds->is_ready()) {
            self::$ds->initiate_datastore();
        }
        return self::$ds;
    }
}

