<?php

header('Content-Type: application/json');

// Inclure les fichiers nécessaires
include_once 'api.php'; // Contient la fonction CallAPI
include_once 'db_connect.php'; // Connexion à la base de données

// Fonction pour envoyer une réponse JSON standardisée
function jsonResponse($status, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

// Vérification et validation des paramètres d'entrée
function validateInputs($bonLivraison, $produitsScannes) {
    if (!$bonLivraison || !is_array($produitsScannes)) {
        jsonResponse('error', 'Paramètres bon_livraison ou produits manquants ou invalides.', [], 400);
    }
    foreach ($produitsScannes as $produit) {
        if (!isset($produit['nom_produit'], $produit['quantite'])) {
            jsonResponse('error', 'Structure invalide pour un produit scanné.', [], 400);
        }
    }
}

// Optimisation des produits pour une recherche rapide
function indexProductsByName($produits) {
    $indexed = [];
    foreach ($produits as $produit) {
        $indexed[$produit['nom_produit']] = $produit['quantite'];
    }
    return $indexed;
}

// Validation des produits
function validateProducts($produitsScannes, $produitsAPI) {
    $errors = [];
    $apiIndex = indexProductsByName($produitsAPI);

    // Vérifier les produits scannés
    foreach ($produitsScannes as $produitScanne) {
        $nomProduit = $produitScanne['nom_produit'];
        $quantiteScannee = $produitScanne['quantite'];

        if (!isset($apiIndex[$nomProduit])) {
            $errors[] = [
                'produit' => $nomProduit,
                'erreur' => "Produit non trouvé dans le bon de livraison."
            ];
        } elseif ($apiIndex[$nomProduit] != $quantiteScannee) {
            $errors[] = [
                'produit' => $nomProduit,
                'erreur' => "Quantité incorrecte : attendu {$apiIndex[$nomProduit]}, reçu $quantiteScannee."
            ];
        }
    }

    // Vérifier les produits manquants dans le scan
    $scannedIndex = indexProductsByName($produitsScannes);
    foreach ($apiIndex as $nomProduit => $quantiteAttendue) {
        if (!isset($scannedIndex[$nomProduit])) {
            $errors[] = [
                'produit' => $nomProduit,
                'erreur' => "Produit manquant dans le scan utilisateur."
            ];
        }
    }

    return $errors;
}

try {
    // Entrées utilisateur
    $bonLivraison = isset($_POST['bon_livraison']) ? filter_var(trim($_POST['bon_livraison']), FILTER_SANITIZE_STRING) : null;
    $produitsScannes = isset($_POST['produits']) ? json_decode($_POST['produits'], true) : null;

    // Validation des entrées
    validateInputs($bonLivraison, $produitsScannes);

    // Récupération des produits associés via l'API
    $apiUrl = "https://erp.powertechsystems.eu/api/index.php/shipments?sqlfilters=(t.ref%3A%3D%3A'$bonLivraison')";
    $decodedResponse = CallAPI('GET', $apiUrl);

    if (!$decodedResponse || isset($decodedResponse['error'])) {
        jsonResponse('error', 'Erreur lors de la récupération des produits via l\'API.', [], 500);
    }
    if (empty($decodedResponse)) {
        jsonResponse('error', "Le bon de livraison $bonLivraison n'a pas été trouvé.", [], 404);
    }

    // Extraire les produits de l'API
    $produitsAPI = [];
    foreach ($decodedResponse[0]['lines'] ?? [] as $line) {
        $produitsAPI[] = [
            'nom_produit' => $line['ref'] ?? 'Référence non disponible',
            'quantite' => $line['qty'] ?? 0
        ];
    }

    // Validation des produits
    $errors = validateProducts($produitsScannes, $produitsAPI);

    // Résultat final
    if (empty($errors)) {
        jsonResponse('success', 'Tous les produits correspondent et les quantités sont correctes.', [], 200);
    } else {
        jsonResponse('error', 'Des erreurs ont été détectées.', ['erreurs' => $errors], 400);
    }
} catch (Exception $e) {
    error_log("Erreur lors de la validation des produits : " . $e->getMessage(), 3, __DIR__ . '/../logs/error.log');
    jsonResponse('error', 'Erreur interne lors de la validation des produits.', [], 500);
}
?>
