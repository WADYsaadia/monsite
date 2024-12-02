<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <title>Logistique</title>
</head>
<body>
  <header>
    <h1>Logistique</h1>
  </header>

  <nav>
    <a href="accueil.php">Accueil</a>
    <a href="index.php">Commande</a>
    <a href="historique.php">Historique</a>
    <a href="deconnexion.php">Déconnexion</a>
  </nav>

  <div class="container">
    <!-- Conteneur des erreurs -->
    <div id="errorMessages"></div>

    <!-- Formulaire -->
    <div class="form-container">
      <h3>Formulaire</h3>
      <form method="post" action="traitement_commande.php">
        <label for="user">Utilisateur</label>
        <select id="user" name="user">
          <option value="user1">Guillaume</option>
          <option value="user2">Adama</option>
          <option value="user3">Vini</option>
          <option value="user4">Maciré</option>
          <option value="user5">Julien</option>
        </select>

        <label for="bon_livraison">Bon de livraison</label>
        <input type="text" id="bon_livraison" name="bon_livraison" placeholder="Saisissez le bon de livraison">
        
        <div id="client_container" style="display: none;">
          <label for="client">Nom du client :</label>
          <input type="text" id="client" name="client" readonly>
        </div>

        <ul id="produits_liste"></ul>
        <div id="product-fields-container"></div>

        <label for="commentaire">Commentaire</label>
        <textarea id="commentaire" name="commentaire" placeholder="Ajoutez un commentaire"></textarea>

        <button type="submit">Envoyer</button>
      </form>
    </div>

    <div class="bon-container">
      <h3>Le bon de livraison en cours</h3>
      <p>Votre bon de livraison apparaîtra ici.</p>
    </div>
  </div>

  <footer>
    <p>&copy; 2024 - Le site de Logistique</p>
  </footer>
  <script src="script.js"></script>
</body>
</html>
