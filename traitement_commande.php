<?php
require_once 'db_connect.php'; // Charger la connexion à la base de données
require '../Mail/phpmailer/PHPMailerAutoload.php'; // Charger PHPMailer
include_once 'api.php'; // Pour utiliser la fonction CallAPI

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Fonction pour renvoyer une réponse JSON
function jsonResponse($response, $httpCode = 200) {
    header('Content-Type: application/json');
    http_response_code($httpCode);
    echo json_encode($response);
    exit;
}

// Journaliser les erreurs pour débogage
function logError($message) {
    $logFile = __DIR__ . '/logs/traitement_commande.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Journaliser la requête reçue
        logError("Requête POST reçue : " . print_r($_POST, true));

        // Récupérer les données du formulaire
        $user = $_POST['user'] ?? null;
        $bonLivraison = $_POST['bon_livraison'] ?? null;
        $client = $_POST['client'] ?? null;
        $commentaire = $_POST['commentaire'] ?? null;

        // Vérification des champs obligatoires
        if (!$user || !$bonLivraison || !$client || !$commentaire) {
            jsonResponse(['success' => false, 'error' => 'Tous les champs obligatoires ne sont pas remplis.'], 400);
        }

        // Validation du format du bon de livraison
        if (!preg_match('/^SH\d+$/', $bonLivraison)) {
            jsonResponse(['success' => false, 'error' => 'Le bon de livraison est invalide.'], 400);
        }

        // Vérification des produits scannés
        $produitsScannes = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'produit_') === 0 && !empty($value)) {
                $produitsScannes[] = htmlspecialchars($value);
            }
        }

        if (empty($produitsScannes)) {
            jsonResponse(['success' => false, 'error' => 'Aucun produit scanné.'], 400);
        }

        // Vérification avec l'API pour comparer les produits attendus
        $encodedBonLivraison = urlencode($bonLivraison);
        $apiUrl = "https://erp.powertechsystems.eu/api/index.php/shipments?sqlfilters=(t.ref%3A%3D%3A'$encodedBonLivraison')";
        $decodedResponse = CallAPI('GET', $apiUrl);

        if (!$decodedResponse || isset($decodedResponse['error'])) {
            logError("Erreur API : " . json_encode($decodedResponse));
            jsonResponse(['success' => false, 'error' => 'Erreur lors de la récupération des produits via l\'API.'], 500);
        }

        if (empty($decodedResponse)) {
            jsonResponse(['success' => false, 'error' => "Le bon de livraison $bonLivraison n'a pas été trouvé."], 404);
        }

        // Extraction des produits attendus depuis l'API
        $produitsAPI = [];
        foreach ($decodedResponse[0]['lines'] ?? [] as $line) {
            $produitsAPI[] = [
                'nom_produit' => $line['ref'] ?? 'Référence non disponible',
                'quantite' => $line['qty'] ?? 0
            ];
        }

        // Comparaison des produits scannés avec les produits attendus
        $erreurs = [];
        foreach ($produitsScannes as $produitScanne) {
            $trouve = false;
            foreach ($produitsAPI as $produitAPI) {
                if ($produitScanne === $produitAPI['nom_produit']) {
                    $trouve = true;
                    break;
                }
            }
            if (!$trouve) {
                $erreurs[] = "Le produit scanné '$produitScanne' ne correspond à aucun produit attendu.";
            }
        }

        if (!empty($erreurs)) {
            jsonResponse(['success' => false, 'error' => 'Des erreurs ont été détectées.', 'details' => $erreurs], 400);
        }

        // Toutes les vérifications sont passées, enregistrement des données
        $bdd->beginTransaction(); // Début de la transaction

        foreach ($produitsScannes as $produit) {
            $stmt = $bdd->prepare(
                "INSERT INTO Commandes (bon_livraison, numero_serie, nom_produit, commentaires, prepa, date_commande) 
                 VALUES (:bon_livraison, :numero_serie, :nom_produit, :commentaires, :prepa, NOW())"
            );
            $stmt->execute([
                ':bon_livraison' => $bonLivraison,
                ':numero_serie' => $produit,
                ':nom_produit' => $produit, // Remplacez par des données valides si nécessaire
                ':commentaires' => $commentaire,
                ':prepa' => $user,
            ]);
        }

        $bdd->commit(); // Validation de la transaction

        // Envoi de l'e-mail
        try {
            $adminemail = "noreply@votresite.com";
            $recipients = [
                'saadia@powertechsystems.eu',
                'christelle@powertechsystems.eu',
                'faten@powertechsystems.eu',
                'guillaume@powertechsystems.eu',
                'romain@powertechsystems.eu',
                'grace@powertechsystems.eu'
            ];

            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = 'smtp-relay.brevo.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'cyril@powertechsystems.eu';
            $mail->Password = 'kPATKn9gsqzJMrSm'; // Ajoutez le mot de passe SMTP
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom($adminemail, 'Logistique');
            foreach ($recipients as $recipient) {
                $mail->addAddress($recipient);
            }

            $mail->isHTML(true);
            $mail->Subject = 'Nouvelle commande enregistrée';
            $mailContent = "<h1>Nouvelle commande enregistrée</h1>";
            $mailContent .= "<p>Bon de livraison : $bonLivraison</p>";
            foreach ($produitsScannes as $produit) {
                $mailContent .= "<p>Produit scanné : $produit</p>";
            }
            $mail->Body = $mailContent;

            $mail->send();
        } catch (Exception $e) {
            logError("Erreur d'envoi de mail : " . $mail->ErrorInfo);
            jsonResponse(['success' => true, 'message' => 'Commande enregistrée, mais e-mail non envoyé.', 'email_error' => $mail->ErrorInfo]);
        }

        jsonResponse(['success' => true, 'message' => 'Commande enregistrée et e-mail envoyé.']);
    } else {
        jsonResponse(['success' => false, 'error' => 'Méthode non autorisée.'], 405);
    }
} catch (Exception $e) {
    if ($bdd->inTransaction()) {
        $bdd->rollBack();
    }

    logError("Erreur générale : " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => "Une erreur s'est produite. Veuillez réessayer plus tard."], 500);
}
