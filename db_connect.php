<?php

$dbServername = 'localhost';
$dbUsername = 'saadia';
$dbPassword = '5*U9htsa5*U9htsa';
$dbName = 'ptslora';
$dbCharset = 'utf8';

try {
    $bdd = new PDO(
        "mysql:host=$dbServername;dbname=$dbName;charset=$dbCharset",
        $dbUsername,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Erreur de connexion à la base de données : " . $e->getMessage(), 3, __DIR__ . '/../logs/error.log');
    die(json_encode(['error' => 'Erreur de connexion à la base de données.']));
}

?>
