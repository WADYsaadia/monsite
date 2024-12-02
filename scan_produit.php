<?php
header('Content-Type: application/json');
session_start(); // Start the session to track scanned serial numbers

// Inclure les fichiers nécessaires
include_once 'db_connect.php'; // Connexion à la base de données

// Fonction pour envoyer une réponse JSON standardisée
function jsonResponse($status, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

// Assainissement et validation des paramètres d'entrée
$numeroSerie = isset($_POST['numero_serie']) ? trim($_POST['numero_serie']) : null;

// Validation : vérifier que le champ n'est pas vide
if (empty($numeroSerie)) {
    jsonResponse('error', 'Le numéro de série est manquant ou invalide.', [], 400);
}

// Validation : format du numéro de série
if (!preg_match('/^[A-Z0-9\-]+$/', $numeroSerie)) {
    jsonResponse('error', 'Le numéro de série a un format invalide.', [], 400);
}

// Check if the serial number has already been scanned in this session
if (isset($_SESSION['scanned_serials']) && in_array($numeroSerie, $_SESSION['scanned_serials'])) {
    jsonResponse('error', 'Attention ce produit est déjà scanné.', [], 409);
}

try {
    // Vérifiez d'abord si le numéro de série existe dans la table Produits
    $queryProduits = "
        SELECT nom_produit 
        FROM Produits 
        WHERE numero_serie = :numero_serie
    ";
    $stmtProduits = $bdd->prepare($queryProduits);
    $stmtProduits->bindParam(':numero_serie', $numeroSerie, PDO::PARAM_STR);
    $stmtProduits->execute();
    $produit = $stmtProduits->fetch(PDO::FETCH_ASSOC);

    if (!$produit) {
        jsonResponse('error', 'Le numéro de série n\'existe pas dans la table Produits.', [], 404);
    }

    // Vérifiez si le numéro de série est un doublon dans les commandes
    if (strpos($numeroSerie, "ASSN-") !== 0) { // Exclut les numéros commençant par "ASSN-"
        $queryCommandes = "
            SELECT COUNT(*) as count 
            FROM Commandes 
            WHERE numero_serie = :numero_serie
        ";
        $stmtCommandes = $bdd->prepare($queryCommandes);
        $stmtCommandes->bindParam(':numero_serie', $numeroSerie, PDO::PARAM_STR);
        $stmtCommandes->execute();
        $result = $stmtCommandes->fetch(PDO::FETCH_ASSOC);

        // Détection de doublon
        if ($result && $result['count'] > 0) {
            jsonResponse('error', 'Ce numéro de série est déjà associé à une commande.', [], 409);
        }
    }

    // Tout est valide, ajouter le numéro de série scanné à la session
    $_SESSION['scanned_serials'][] = $numeroSerie;

    // Retourner le produit associé
    jsonResponse('success', 'Le numéro de série est valide.', ['nom_produit' => $produit['nom_produit']]);
} catch (PDOException $e) {
    // Logs détaillés pour les erreurs de base de données
    error_log("Erreur PDO : " . $e->getMessage(), 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'Erreur interne lors de la vérification du numéro de série.', [], 500);
} catch (Exception $e) {
    // Logs pour d'autres types d'erreurs
    error_log("Erreur inattendue : " . $e->getMessage(), 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'Une erreur inattendue s\'est produite.', [], 500);
}
?>
