<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/GoogleCalendarHelper.php'; // Reuse makeApiRequest logic

class GoogleDriveHelper extends GoogleCalendarHelper
{
    // Eredita costruttore e metodi base (makeApiRequest, getAccessToken)

    public function findOrCreateFolder($userId, $folderName)
    {
        $accessToken = $this->getAccessToken($userId);
        if (!$accessToken)
            throw new Exception("User not authenticated");

        // 1. Search
        $query = "mimeType='application/vnd.google-apps.folder' and name='" . $folderName . "' and trashed=false";
        $url = "https://www.googleapis.com/drive/v3/files?q=" . urlencode($query);

        $res = $this->driveApiRequest($url, 'GET', $accessToken);

        if (!empty($res['files'])) {
            return $res['files'][0]['id'];
        }

        // 2. Create if not exists
        $body = [
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder'
        ];

        $resCreate = $this->driveApiRequest("https://www.googleapis.com/drive/v3/files", 'POST', $accessToken, $body);

        if (isset($resCreate['id'])) {
            return $resCreate['id'];
        }

        throw new Exception("Cannot create folder: " . json_encode($resCreate));
    }

    public function uploadFile($userId, $filePath, $fileName, $mimeType, $folderId)
    {
        $accessToken = $this->getAccessToken($userId);
        if (!$accessToken)
            throw new Exception("User not authenticated");

        $url = "https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink,iconLink,webContentLink";

        $metadata = [
            'name' => $fileName,
            'parents' => [$folderId]
        ];

        $boundary = '-------314159265358979323846';
        $delimiter = "\r\n--" . $boundary . "\r\n";
        $close_delimiter = "\r\n--" . $boundary . "--";

        $content = file_get_contents($filePath);

        $postBody = $delimiter .
            "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
            json_encode($metadata) .
            $delimiter .
            "Content-Type: " . $mimeType . "\r\n\r\n" .
            $content .
            $close_delimiter;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $accessToken,
            "Content-Type: multipart/related; boundary=" . $boundary,
            "Content-Length: " . strlen($postBody)
        ]);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log("Upload Curl Error: " . curl_error($ch));
            throw new Exception("Curl Error during upload");
        }
        curl_close($ch);

        $json = json_decode($result, true);

        if (isset($json['error'])) {
            throw new Exception("Drive Upload Error: " . $json['error']['message']);
        }

        return $json;
    }

    public function setPublicPermission($userId, $fileId)
    {
        $accessToken = $this->getAccessToken($userId);
        if (!$accessToken) {
            throw new Exception("User not authenticated with Google");
        }

        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}/permissions";
        $body = [
            'role' => 'reader',
            'type' => 'anyone'
        ];

        return $this->driveApiRequest($url, 'POST', $accessToken, $body);
    }

    private function driveApiRequest($url, $method, $token, $body = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix Local Cert

        $headers = [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log("Drive Curl Error: " . curl_error($ch));
        }

        curl_close($ch);
        $decoded = json_decode($result, true);

        // Log error if permission fails
        if (isset($decoded['error'])) {
            error_log("Drive Permission Error: " . json_encode($decoded));
        }

        return $decoded;
    }
}
