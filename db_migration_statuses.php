try {
// Direct connection for CLI migration reliability
$host = '127.0.0.1'; // Force TCP/IP
$db = 'local';
$user = 'root';
$pass = 'root';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$pdo = new PDO($dsn, $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
echo "Connected to DB successfully.<br>";
// 1. Convert tasks.stato to VARCHAR if it is ENUM
// Note: This might need specific SQL syntax depending on DB, assuming MySQL.
// 'MODIFY COLUMN' is standard MySQL.

// Check if column is already VARCHAR (schema check omitted for brevity, just running ALTER)
// If it fails because it's already VARCHAR, we catch it or ignore.
$pdo->exec("ALTER TABLE tasks MODIFY COLUMN stato VARCHAR(50) DEFAULT 'da_fare'");
echo "Converted tasks.stato to VARCHAR.<br>";

// 2. Create task_statuses table
$sql = "CREATE TABLE IF NOT EXISTS task_statuses (
id INT AUTO_INCREMENT PRIMARY KEY,
user_id INT NOT NULL,
nome VARCHAR(50) NOT NULL,
colore VARCHAR(7) NOT NULL,
ordine INT DEFAULT 0,
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$pdo->exec($sql);
echo "Created task_statuses table.<br>";

// 3. Seed Defaults for existing users
// Get all user IDs
$stmt = $pdo->query("SELECT id FROM users");
$users = $stmt->fetchAll(PDO::FETCH_COLUMN);

$defaults = [
['nome' => 'da_fare', 'colore' => '#64748b', 'ordine' => 0], // Slate-500
['nome' => 'in_corso', 'colore' => '#3b82f6', 'ordine' => 1], // Blue-500
['nome' => 'completato', 'colore' => '#22c55e', 'ordine' => 2] // Green-500
];

foreach ($users as $userId) {
// Check if user already has statuses
$check = $pdo->prepare("SELECT COUNT(*) FROM task_statuses WHERE user_id = ?");
$check->execute([$userId]);
if ($check->fetchColumn() == 0) {
$insert = $pdo->prepare("INSERT INTO task_statuses (user_id, nome, colore, ordine) VALUES (?, ?, ?, ?)");
foreach ($defaults as $status) {
$insert->execute([$userId, $status['nome'], $status['colore'], $status['ordine']]);
}
echo "Seeded defaults for user ID $userId.<br>";
}
}

echo "Migration completed successfully.";

} catch (PDOException $e) {
die("Migration failed: " . $e->getMessage());
}
?>