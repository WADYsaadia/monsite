<?php

header('Content-Type: application/json');

// Include necessary files
include_once 'api.php'; // Contains the CallAPI function
include_once 'db_connect.php'; // Database connection

// Function to send standardized JSON responses
function jsonResponse($status, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

// Validate input parameters
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

// Index products by name for quick lookup
function indexProductsByName($produits) {
    $indexed = [];
    foreach ($produits as $produit) {
        $indexed[$produit['nom_produit']] = $produit['quantite'];
    }
    return $indexed;
}

// Validate scanned products against API data
function validateProducts($produitsScannes, $produitsAPI) {
    $errors = [];
    $apiIndex = indexProductsByName($produitsAPI);

    // Check scanned products
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

    // Check for missing products in the scan
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
    // User inputs
    $bonLivraison = isset($_POST['bon_livraison']) ? filter_var(trim($_POST['bon_livraison']), FILTER_SANITIZE_STRING) : null;
    $produitsScannes = isset($_POST['produits']) ? json_decode($_POST['produits'], true) : null;

    // Validate inputs
    validateInputs($bonLivraison, $produitsScannes);

    // Retrieve associated products via API
    $apiUrl = "https://erp.powertechsystems.eu/api/index.php/shipments?sqlfilters=(t.ref%3A%3D%3A'$bonLivraison')";
    $decodedResponse = CallAPI('GET', $apiUrl);

    if (!$decodedResponse || isset($decodedResponse['error'])) {
        jsonResponse('error', 'Erreur lors de la récupération des produits via l\'API.', [], 500);
    }
    if (empty($decodedResponse)) {
        jsonResponse('error', "Le bon de livraison $bonLivraison n'a pas été trouvé.", [], 404);
    }

    // Extract products from API response
    $produitsAPI = [];
    foreach ($decodedResponse[0]['lines'] ?? [] as $line) {
        $produitsAPI[] = [
            'nom_produit' => $line['ref'] ?? 'Référence non disponible',
            'quantite' => $line['qty'] ?? 0
        ];
    }

    // Validate products
    $errors = validateProducts($produitsScannes, $produitsAPI);

    // Final result
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
