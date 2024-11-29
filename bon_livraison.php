<?php
// Inclure la connexion à la base de données
require_once 'db_connect.php';

// Définir le type de contenu comme JSON
header('Content-Type: application/json');

try {
    // Vérifier que le paramètre 'bon-livraison' est fourni
    if (!isset($_POST['bon_livraison'])) {
        http_response_code(400); // Mauvaise requête
        echo json_encode(['error' => 'Le paramètre "bon_livraison" est manquant.']);
        exit;
    }

    // Récupérer et sécuriser le paramètre 'bon-livraison'
    $bonLivraison = filter_var(trim($_POST['bon_livraison']), FILTER_SANITIZE_STRING);

    // Vérifier si la valeur est vide après nettoyage
    if (empty($bonLivraison)) {
        http_response_code(400);
        echo json_encode(['error' => 'Le paramètre "bon_livraison" est vide ou invalide.']);
        exit;
    }

    // Préparer la requête pour vérifier si le bon de livraison existe
    $query = $bdd->prepare("SELECT COUNT(*) FROM Commandes WHERE bon_livraison = :bon_livraison");
    $query->bindParam(':bon_livraison', $bonLivraison, PDO::PARAM_STR);
    $query->execute();

    // Vérifier si le bon de livraison existe déjà
    $exists = $query->fetchColumn();

    if ($exists > 0) {
        // Retourner un message d'erreur si le bon de livraison existe
        http_response_code(409); // Conflit
        echo json_encode(['error' => 'Attention, ce bon de livraison a déjà été traité.']);
    } else {
        // Retourner un succès si le bon de livraison est valide
        http_response_code(200);
        echo json_encode(['success' => 'Bon de livraison valide et non traité.']);
    }
} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    error_log("Erreur lors de la vérification du bon de livraison : " . $e->getMessage());
    http_response_code(500); // Erreur interne
    echo json_encode(['error' => 'Erreur interne lors de la vérification du bon de livraison.']);
}
?>
