<?php
header('Content-Type: application/json');
session_start(); // Start the session to track scanned serial numbers

// Include necessary files
include_once 'db_connect.php'; // Database connection

// Function to send standardized JSON responses
function jsonResponse($status, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

// Sanitize and validate input parameters
$numeroSerie = isset($_POST['numero_serie']) ? trim($_POST['numero_serie']) : null;

// Validation: check if the field is not empty
if (empty($numeroSerie)) {
    jsonResponse('error', 'Le numéro de série est manquant ou invalide.', [], 400);
}

// Validation: format of the serial number
if (!preg_match('/^[A-Z0-9\-]+$/', $numeroSerie)) {
    jsonResponse('error', 'Le numéro de série a un format invalide.', [], 400);
}

// Check if the serial number has already been scanned in this session
if (isset($_SESSION['scanned_serials']) && in_array($numeroSerie, $_SESSION['scanned_serials'])) {
    jsonResponse('error', 'Attention ce produit est déjà scanné.', [], 409);
}

try {
    // Check if the serial number exists in the Products table
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

    // Check if the serial number is a duplicate in orders
    if (strpos($numeroSerie, "ASSN-") !== 0) { // Exclude numbers starting with "ASSN-"
        $queryCommandes = "
            SELECT COUNT(*) as count 
            FROM Commandes 
            WHERE numero_serie = :numero_serie
        ";
        $stmtCommandes = $bdd->prepare($queryCommandes);
        $stmtCommandes->bindParam(':numero_serie', $numeroSerie, PDO::PARAM_STR);
        $stmtCommandes->execute();
        $result = $stmtCommandes->fetch(PDO::FETCH_ASSOC);

        // Duplicate detection
        if ($result && $result['count'] > 0) {
            jsonResponse('error', 'Ce numéro de série est déjà associé à une commande.', [], 409);
        }
    }

    // Everything is valid, add the scanned serial number to the session
    $_SESSION['scanned_serials'][] = $numeroSerie;

    // Return the associated product
    jsonResponse('success', 'Le numéro de série est valide.', ['nom_produit' => $produit['nom_produit']]);
} catch (PDOException $e) {
    // Detailed logs for database errors
    error_log("Erreur PDO : " . $e->getMessage(), 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'Erreur interne lors de la vérification du numéro de série.', [], 500);
} catch (Exception $e) {
    // Logs for other types of errors
    error_log("Erreur inattendue : " . $e->getMessage(), 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'Une erreur inattendue s\'est produite.', [], 500);
}
?>
