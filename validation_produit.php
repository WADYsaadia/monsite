<?php

header('Content-Type: application/json');

// Désactiver l'affichage des erreurs en production
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Inclure les fichiers nécessaires
include_once 'api.php'; // Contient la fonction CallAPI
include_once 'db_connect.php'; // Connexion à la base de données

// Fonction pour envoyer une réponse JSON standardisée
function jsonResponse($status, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

// Vérification des paramètres
$bonLivraison = isset($_POST['bon_livraison']) ? filter_var(trim($_POST['bon_livraison']), FILTER_SANITIZE_STRING) : null;
$produitsScannes = isset($_POST['produits']) ? json_decode($_POST['produits'], true) : null;

if (!$bonLivraison || !is_array($produitsScannes)) {
    jsonResponse('error', 'Paramètres bon_livraison ou produits manquants ou invalides.', [], 400);
}

// Construire l'URL pour récupérer les produits associés au bon de livraison via l'API
$encodedBonLivraison = urlencode($bonLivraison);
$apiUrl = "https://erp.powertechsystems.eu/api/index.php/shipments?sqlfilters=(t.ref%3A%3D%3A'$encodedBonLivraison')";

// Appel de l'API
$decodedResponse = CallAPI('GET', $apiUrl);
if (!$decodedResponse || isset($decodedResponse['error'])) {
    jsonResponse('error', 'Erreur lors de la récupération des produits via l\'API.', [], 500);
}

if (empty($decodedResponse)) {
    jsonResponse('error', "Le bon de livraison $bonLivraison n'a pas été trouvé.", [], 404);
}

// Extraction des produits de l'API
$produitsAPI = [];
foreach ($decodedResponse[0]['lines'] ?? [] as $line) {
    $produitsAPI[] = [
        'nom_produit' => $line['ref'] ?? 'Référence non disponible',
        'quantite' => $line['qty'] ?? 0
    ];
}

// Comparaison des produits scannés et des produits de l'API
$erreurs = [];
foreach ($produitsScannes as $produitScanne) {
    $trouve = false;
    foreach ($produitsAPI as $produitAPI) {
        if ($produitScanne['nom_produit'] === $produitAPI['nom_produit']) {
            $trouve = true;
            if ($produitScanne['quantite'] != $produitAPI['quantite']) {
                $erreurs[] = [
                    'produit' => $produitScanne['nom_produit'],
                    'erreur' => "Quantité incorrecte : attendu {$produitAPI['quantite']}, reçu {$produitScanne['quantite']}."
                ];
            }
            break;
        }
    }
    if (!$trouve) {
        $erreurs[] = [
            'produit' => $produitScanne['nom_produit'],
            'erreur' => "Produit non trouvé dans le bon de livraison."
        ];
    }
}

// Vérification des produits manquants dans le scan
foreach ($produitsAPI as $produitAPI) {
    $trouve = false;
    foreach ($produitsScannes as $produitScanne) {
        if ($produitScanne['nom_produit'] === $produitAPI['nom_produit']) {
            $trouve = true;
            break;
        }
    }
    if (!$trouve) {
        $erreurs[] = [
            'produit' => $produitAPI['nom_produit'],
            'erreur' => "Produit manquant dans le scan utilisateur."
        ];
    }
}

// Retourner les résultats
if (empty($erreurs)) {
    jsonResponse('success', 'Tous les produits correspondent et les quantités sont correctes.', [], 200);
} else {
    jsonResponse('error', 'Des erreurs ont été détectées.', ['erreurs' => $erreurs], 400);
}

?>
