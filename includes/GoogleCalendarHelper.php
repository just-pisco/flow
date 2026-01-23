<?php
require_once __DIR__ . '/db.php';

class GoogleCalendarHelper
{
    private $pdo;
    private $clientId;
    private $clientSecret;
    private $redirectUri = 'postmessage'; // 'postmessage' for popup flow

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        // In produzione, usa variabili d'ambiente. Qui usiamo un placeholder o define.
        $this->clientId = getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_CLIENT_ID_PLACEHOLDER';
        $this->clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR_CLIENT_SECRET_PLACEHOLDER';
    }

    // SCAMBI AUTH CODE PER TOKEN
    public function exchangeCodeForToken($userId, $authCode)
    {
        $url = 'https://oauth2.googleapis.com/token';
        $params = [
            'code' => $authCode,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code'
        ];

        $response = $this->makeHttpRequest($url, $params);

        if (isset($response['error'])) {
            throw new Exception("Google Token Error: " . json_encode($response));
        }

        // Salva i token nel DB
        $this->saveTokens($userId, $response);
        return $response;
    }

    // OTTIENI ACCESS TOKEN (Refresh se necessario)
    public function getAccessToken($userId)
    {
        $stmt = $this->pdo->prepare("SELECT google_access_token, google_refresh_token, google_token_expires_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $tokens = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokens || !$tokens['google_refresh_token']) {
            return null; // Utente non connesso
        }

        // Se scade tra meno di 60 secondi (o è già scaduto), refresh
        if (time() >= ($tokens['google_token_expires_at'] - 60)) {
            return $this->refreshAccessToken($userId, $tokens['google_refresh_token']);
        }

        return $tokens['google_access_token'];
    }

    private function refreshAccessToken($userId, $refreshToken)
    {
        $url = 'https://oauth2.googleapis.com/token';
        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];

        $response = $this->makeHttpRequest($url, $params);

        if (isset($response['error'])) {
            // Se errore è "invalid_grant", l'utente ha revocato l'accesso. Pulire DB?
            // Per ora lanciamo eccezione.
            throw new Exception("Token Refresh Failed: " . json_encode($response));
        }

        $this->saveTokens($userId, $response);
        return $response['access_token'];
    }

    private function saveTokens($userId, $data)
    {
        // Se c'è un refresh token, aggiornalo. Altrimenti mantieni quello vecchio (il refresh token non cambia spesso).
        $refreshToken = $data['refresh_token'] ?? null;
        $accessToken = $data['access_token'];
        $expiresIn = $data['expires_in']; // Seconds
        $expiresAt = time() + $expiresIn;

        if ($refreshToken) {
            $stmt = $this->pdo->prepare("UPDATE users SET google_access_token = ?, google_refresh_token = ?, google_token_expires_at = ? WHERE id = ?");
            $stmt->execute([$accessToken, $refreshToken, $expiresAt, $userId]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE users SET google_access_token = ?, google_token_expires_at = ? WHERE id = ?");
            $stmt->execute([$accessToken, $expiresAt, $userId]);
        }
    }

    // SYNC LOGIC
    public function syncTask($userId, $taskId)
    {
        $token = $this->getAccessToken($userId);
        if (!$token)
            return; // Non connesso

        // Get Task Details
        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task || !$task['scadenza'])
            return; // Serve una scadenza

        // Get Calendar ID
        $calendarId = $this->getOrCreateFlowCalendar($userId, $token);
        if (!$calendarId)
            return;

        // Check Duplicates
        if ($this->eventExists($token, $calendarId, $task)) {
            // Potremmo voler fare UPDATE invece di skip.
            // Per ora skippiamo per evitare duplicati in insert.
            // TODO: Implement update logic if event exists?
            return;
        }

        // Insert Event
        $this->insertEvent($token, $calendarId, $task);
    }

    private function getOrCreateFlowCalendar($userId, $token)
    {
        // Check DB first
        $stmt = $this->pdo->prepare("SELECT google_calendar_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $calId = $stmt->fetchColumn();

        if ($calId) {
            // Verify existence (light check via API list or get)
            // Salto verifica per performance, se fallisce insert aggiorno
            return $calId;
        }

        // Create
        $url = 'https://www.googleapis.com/calendar/v3/calendars';
        $body = [
            'summary' => 'Flow',
            'description' => 'Calendario Task Flow',
            'timeZone' => 'Europe/Rome' // Dovrebbe venire dalle preferenze utente
        ];

        $res = $this->makeApiRequest($url, 'POST', $token, $body);
        if (isset($res['id'])) {
            $stmt = $this->pdo->prepare("UPDATE users SET google_calendar_id = ? WHERE id = ?");
            $stmt->execute([$res['id'], $userId]);
            return $res['id'];
        }
        return null;
    }

    private function eventExists($token, $calendarId, $task)
    {
        // Search by private extended property or title/date
        // Usiamo titolo + data per semplicità come nel JS precedente, ma extended property sarebbe meglio.
        // Simuliamo logica JS: [Flow] Title
        $targetTitle = "[Flow] " . $task['titolo'];
        $timeMin = date('c', strtotime($task['scadenza'] . ' 00:00:00'));
        $timeMax = date('c', strtotime($task['scadenza'] . ' 23:59:59'));

        // Nota: 'privateExtendedProperty' query param è più preciso se usiamo extendedProperties
        // Ma per retrocompatibilità coi vecchi task creati da JS che non avevano extended prop, usiamo anche il testo.
        // Tuttavia, qui stiamo implementando la logica backend PRIMARIA d'ora in poi.
        // Usiamo extended property search.

        $url = "https://www.googleapis.com/calendar/v3/calendars/$calendarId/events?" . http_build_query([
            'privateExtendedProperty' => "flow_task_id={$task['id']}",
            'singleEvents' => 'true'
        ]);

        $res = $this->makeApiRequest($url, 'GET', $token);
        if (!empty($res['items']))
            return true;

        // Fallback: check by title/date (for events created before this update)
        $urlFallback = "https://www.googleapis.com/calendar/v3/calendars/$calendarId/events?" . http_build_query([
            'timeMin' => $timeMin,
            'timeMax' => $timeMax,
            'q' => $targetTitle,
            'singleEvents' => 'true'
        ]);
        $resFallback = $this->makeApiRequest($urlFallback, 'GET', $token);
        return !empty($resFallback['items']);
    }

    private function insertEvent($token, $calendarId, $task)
    {
        $url = "https://www.googleapis.com/calendar/v3/calendars/$calendarId/events";

        $date = $task['scadenza'];
        $endDate = date('Y-m-d', strtotime($date . ' +1 day'));

        $body = [
            'summary' => "[Flow] " . $task['titolo'],
            'description' => "Task ID: {$task['id']}\nStatus: {$task['stato']}",
            'start' => ['date' => $date],
            'end' => ['date' => $endDate],
            'extendedProperties' => [
                'private' => [
                    'flow_task_id' => $task['id']
                ]
            ]
        ];

        $this->makeApiRequest($url, 'POST', $token, $body);
    }

    // UTILS
    private function makeHttpRequest($url, $params)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix Local Cert Error
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log("Curl Error: " . curl_error($ch));
            throw new Exception("Curl Error: " . curl_error($ch));
        }

        curl_close($ch);

        $decoded = json_decode($result, true);
        if ($decoded === null) {
            error_log("Google API Raw Response: " . $result);
        }

        return $decoded;
    }

    private function makeApiRequest($url, $method, $token, $body = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix Local Cert Error
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }
}
?>