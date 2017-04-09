<?php

/**
 * There should have been an interface
 * 
 * Class GoogleApiClient
 */
class GoogleApiClient
{
    private $client;

    /**
     * GoogleApiClient constructor.
     */
    public function __construct($client_secret_path, $credentials_path)
    {
        $client = new Google_Client();
        $client->setApplicationName('Uploader-bot');
        $client->setScopes(implode(' ', array(
                Google_Service_Drive::DRIVE_FILE
            )
        ));
        $client->setAuthConfig($client_secret_path);
        $client->setAccessType('offline');

        $credentials_path = $this->expandHomeDirectory($credentials_path);
        if (file_exists($credentials_path)) {
            $accessToken = json_decode(file_get_contents($credentials_path), true);
        } else {
            echo 'Request authorization from the user.';
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            // Store the credentials to disk.
            if (!file_exists(dirname($credentials_path))) {
                mkdir(dirname($credentials_path), 0700, true);
            }
            file_put_contents($credentials_path, json_encode($accessToken));
            printf("Credentials saved to %s\n", $credentials_path);
        }
        $client->setAccessToken($accessToken);

        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($credentials_path, json_encode($client->getAccessToken()));
        }
        $this->client = $client;
    }

    private function expandHomeDirectory($path)
    {
        $homeDirectory = getenv('HOME');
        if (empty($homeDirectory)) {
            $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
        }
        return str_replace('~', realpath($homeDirectory), $path);
    }

    /**
     * @param $file
     * @throws Google_Service_Exception
     */
    public function upload_file($file)
    {
        $service = new Google_Service_Drive($this->getClient());

        $drive_file = new Google_Service_Drive_DriveFile(array(
            'name' => basename($file)
        ));

        $service->files->create($drive_file, array(
            'data' => file_get_contents($file),
            'mimeType' => mime_content_type($file),
            'uploadType' => 'multipart'
        ));
    }

    /**
     * @return Google_Client
     */
    public function getClient()
    {
        return $this->client;
    }

}

