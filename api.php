
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

// Vérification de la clé API
define('API_KEY', getenv('API_KEY') ?: 'aRPCriw7YLWU115ezhl0b1FE46z5RwK8');
if (empty(API_KEY)) {
    http_response_code(500);
    echo json_encode(['error' => 'Clé API manquante ou invalide.']);
    exit;
}

// Fonction générique pour appeler une API
function CallAPI($method, $url, $data = false) {
    $curl = curl_init();

    // Configurer les en-têtes HTTP
    $httpHeader = [
        'DOLAPIKEY: ' . API_KEY,
        'Content-Type: application/json'
    ];

    // Configurer la méthode HTTP
    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($curl, CURLOPT_POST, true);
            if ($data) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            break;
        case 'PUT':
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            break;
        default: // GET
            if ($data) $url .= '?' . http_build_query($data);
    }

    // Configurer cURL
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $httpHeader,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    // Exécuter et gérer les erreurs
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($httpCode >= 400 || curl_errno($curl)) {
        $error = curl_error($curl) ?: "HTTP Code: $httpCode";
        curl_close($curl);
        return ['error' => "Erreur lors de l'appel API : $error"];
    }

    curl_close($curl);
    return json_decode($response, true);
}

// Vérification du paramètre `bon_livraison`
if (!isset($_GET['bon_livraison'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre bon_livraison manquant.']);
    exit;
}

$bonLivraison = filter_var(trim($_GET['bon_livraison']), FILTER_SANITIZE_STRING);
if (!$bonLivraison) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre bon_livraison invalide.']);
    exit;
}

// Construire l'URL de l'API pour le bon de livraison
$encodedBonLivraison = urlencode($bonLivraison);
$apiUrl = "https://erp.powertechsystems.eu/api/index.php/shipments?sqlfilters=(t.ref%3A%3D%3A'$encodedBonLivraison')";

// Appel de l'API pour récupérer les informations
$decodedResponse = CallAPI('GET', $apiUrl);

if (!$decodedResponse || isset($decodedResponse['error'])) {
    http_response_code(500);
    echo json_encode(['error' => $decodedResponse['error'] ?? 'Erreur lors de la récupération des données.']);
    exit;
}

if (empty($decodedResponse)) {
    http_response_code(404);
    echo json_encode(['error' => "Le bon de livraison $bonLivraison n'a pas été trouvé."]);
    exit;
}

// Extraction du socid
$socid = null;
foreach ($decodedResponse as $order) {
    if (isset($order['ref']) && $order['ref'] === $bonLivraison) {
        $socid = $order['socid'];
        break;
    }
}

if (!$socid) {
    http_response_code(404);
    echo json_encode(['error' => 'socid non trouvé dans les données récupérées.']);
    exit;
}

// Appel pour récupérer le nom du client
$clientApiUrl = "https://erp.powertechsystems.eu/api/index.php/thirdparties/$socid";
$clientData = CallAPI('GET', $clientApiUrl);

if (!$clientData || isset($clientData['error']) || empty($clientData['name'])) {
    http_response_code(500);
    echo json_encode(['error' => "Erreur lors de la récupération du nom du client pour socid $socid."]);
    exit;
}

// Préparer la réponse avec `ref` comme nom_produit
$response = [
    'client' => $clientData['name'],
    'produits' => array_map(function ($line) {
        return [
            'nom_produit' => $line['ref'] ?? 'Référence non disponible', // Utilisation de `ref`
            'quantite' => $line['qty'] ?? 0
        ];
    }, $decodedResponse[0]['lines'] ?? [])
];

// Envoyer la réponse
echo json_encode($response);

?>

