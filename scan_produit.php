
<?php
header('Content-Type: application/json');

// Désactiver l'affichage des erreurs en production (activer uniquement en développement)
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Inclure les fichiers nécessaires
include_once 'db_connect.php'; // Connexion à la base de données

// Fonction pour envoyer une réponse JSON standardisée
function jsonResponse($status, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

// Validation et assainissement des paramètres
$numeroSerie = isset($_POST['numero_serie']) ? filter_var(trim($_POST['numero_serie']), FILTER_SANITIZE_STRING) : null;

if (!$numeroSerie) {
    jsonResponse('error', 'Le numéro de série est manquant ou invalide.', [], 400);
}

try {
    // Étape 1 : Vérifier si le numéro de série existe dans la table Produits
    $queryProduits = "SELECT nom_produit FROM Produits WHERE numero_serie = :numero_serie";
    $stmtProduits = $bdd->prepare($queryProduits);
    $stmtProduits->bindParam(':numero_serie', $numeroSerie, PDO::PARAM_STR);
    $stmtProduits->execute();
    $produit = $stmtProduits->fetch(PDO::FETCH_ASSOC);

    if (!$produit) {
        // Le numéro de série n'existe pas dans Produits
        jsonResponse('error', 'Le numéro de série n\'existe pas dans la table Produits.', [], 404);
    }

    // Étape 2 : Vérifier si le numéro de série existe déjà dans la table Commandes
    if (strpos($numeroSerie, "ASSN-") !== 0) { // Exception pour les numéros commençant par "ASSN-"
        $queryCommandes = "SELECT COUNT(*) as count FROM Commandes WHERE numero_serie = :numero_serie";
        $stmtCommandes = $bdd->prepare($queryCommandes);
        $stmtCommandes->bindParam(':numero_serie', $numeroSerie, PDO::PARAM_STR);
        $stmtCommandes->execute();
        $result = $stmtCommandes->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['count'] > 0) {
            // Le numéro de série existe déjà dans Commandes
            jsonResponse('error', 'Ce numéro de série est déjà associé à une commande.', [], 409);
        }
    }

    // Si toutes les validations passent
    jsonResponse('success', 'Le numéro de série est valide.', ['nom_produit' => $produit['nom_produit']]);
} catch (PDOException $e) {
    error_log("Erreur de base de données : " . $e->getMessage(), 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'Erreur interne lors de la vérification du numéro de série.', [], 500);
}
?>
