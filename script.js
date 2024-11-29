document.addEventListener("DOMContentLoaded", function () {
    const bonLivraisonField = document.getElementById("bon_livraison");
    const clientField = document.getElementById("client");
    const clientContainer = document.getElementById("client_container");
    const bonContainer = document.querySelector(".bon-container");
    const champsProduits = document.getElementById("product-fields-container");
    const commentaireField = document.getElementById("commentaire");
    const form = document.querySelector("form");

    let requiredProducts = {}; // Produits et quantités requis pour le bon de livraison
    let scannedProducts = {}; // Quantités scannées

    // Ajout automatique du préfixe "SH" pour le champ "Bon de livraison"
    bonLivraisonField.addEventListener("focus", function () {
        if (!bonLivraisonField.value.startsWith("SH")) {
            bonLivraisonField.value = "SH";
        }
    });

    // Vérification du bon de livraison lorsqu'il perd le focus
    bonLivraisonField.addEventListener("blur", function () {
        const bonLivraison = bonLivraisonField.value.trim();

        if (bonLivraison.startsWith("SH")) {
            verifyDeliveryNote(bonLivraison, function () {
                fetchDeliveryNoteData(bonLivraison); // Si valide, récupérer les données associées
            });
        } else {
            showError("Le bon de livraison doit commencer par 'SH'.");
        }
    });

    // Validation lors du focus sur le champ commentaire
    commentaireField.addEventListener("focus", function () {
        const validationErrors = validateScannedProducts();

        if (validationErrors.length > 0) {
            alert(`Erreur(s) :\n${validationErrors.join("\n")}`);
            commentaireField.blur(); // Sortir du champ commentaire si la validation échoue
        }
    });

    // Vérification si le bon de livraison existe déjà
    function verifyDeliveryNote(bonLivraison, callback) {
        fetch("bon_livraison.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `bon_livraison=${encodeURIComponent(bonLivraison)}`,
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.error) {
                    showError(data.error); // Affiche un message d'erreur si le bon existe déjà
                } else {
                    callback(); // Si tout est valide, appeler la fonction suivante
                }
            })
            .catch((error) => console.error("Erreur lors de la vérification du bon de livraison :", error));
    }

    // Récupération des données associées au bon de livraison via l'API
    function fetchDeliveryNoteData(bonLivraison) {
        fetch(`api.php?bon_livraison=${encodeURIComponent(bonLivraison)}`)
            .then((response) => response.json())
            .then((data) => {
                if (data.error) {
                    showError(data.error);
                } else if (data.client && data.produits) {
                    displayClientName(data.client); // Afficher le nom du client
                    initializeRequiredProducts(data.produits); // Initialiser les produits requis
                    updateBonContainer(); // Mettre à jour la section des produits
                    addProductFields(); // Ajouter le premier champ pour scanner les produits
                } else {
                    showError("Les données du bon de livraison sont incomplètes.");
                }
            })
            .catch((error) => console.error("Erreur lors de la récupération des données de livraison :", error));
    }

    // Initialiser les produits requis pour validation
    function initializeRequiredProducts(produits) {
        requiredProducts = {}; // Réinitialiser les produits requis
        scannedProducts = {}; // Réinitialiser les produits scannés

        produits.forEach((produit) => {
            requiredProducts[produit.nom_produit] = {
                quantite: produit.quantite,
                scanned: 0, // Quantité scannée initiale
            };
            scannedProducts[produit.nom_produit] = 0;
        });
    }

    // Mettre à jour la section des produits et quantités dans bon-container
    function updateBonContainer() {
        if (bonContainer) {
            const productList = document.createElement("ul");
            bonContainer.innerHTML = `<h3>Produits associés au bon de livraison</h3>`; // Réinitialiser le contenu
            for (const [nomProduit, data] of Object.entries(requiredProducts)) {
                const listItem = document.createElement("li");
                listItem.textContent = `${nomProduit} - Quantité : ${data.quantite} - Scanné : ${data.scanned}`;
                productList.appendChild(listItem);
            }
            bonContainer.appendChild(productList);
        }
    }

    // Afficher le nom du client
    function displayClientName(clientName) {
        if (clientField && clientContainer) {
            clientField.value = clientName;
            clientContainer.style.display = "block";
        }
    }

    // Ajouter un champ pour scanner un produit
    function addProductFields(autoAdd = false) {
        if (!champsProduits) return;

        const productDiv = document.createElement("div");
        productDiv.className = "scanned-product mb-3";

        const numeroSerieLabel = document.createElement("label");
        numeroSerieLabel.textContent = "Numéro de Série :";
        const numeroSerieInput = document.createElement("input");
        numeroSerieInput.type = "text";
        numeroSerieInput.className = "form-control form-control-sm numero_serie";
        numeroSerieInput.placeholder = "Scannez le numéro de série";

        const nomProduitLabel = document.createElement("label");
        nomProduitLabel.textContent = "Nom du Produit :";
        const nomProduitInput = document.createElement("input");
        nomProduitInput.type = "text";
        nomProduitInput.className = "form-control form-control-sm nom_produit";
        nomProduitInput.placeholder = "Nom du produit scanné";
        nomProduitInput.readOnly = true;

        const deleteButton = document.createElement("button");
        deleteButton.className = "btn btn-danger btn-sm mt-2";
        deleteButton.textContent = "Supprimer";
        deleteButton.onclick = (e) => {
            e.preventDefault();
            champsProduits.removeChild(productDiv);
        };

        productDiv.appendChild(numeroSerieLabel);
        productDiv.appendChild(numeroSerieInput);
        productDiv.appendChild(nomProduitLabel);
        productDiv.appendChild(nomProduitInput);
        productDiv.appendChild(deleteButton);
        champsProduits.appendChild(productDiv);

        if (!autoAdd) {
            numeroSerieInput.addEventListener("blur", () => {
                verifyProduct(numeroSerieInput, nomProduitInput);
            });
        }
    }

    // Vérifier si le produit scanné est valide
    function verifyProduct(numeroSerieInput, nomProduitInput) {
        const numeroSerie = numeroSerieInput.value.trim();

        fetch("scan_produit.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `numero_serie=${encodeURIComponent(numeroSerie)}`,
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.error) {
                    showError(data.error);
                    nomProduitInput.value = "";
                } else {
                    const productRef = data.nom_produit;

                    if (!requiredProducts[productRef]) {
                        showError("Ce produit ne fait pas partie de la commande.");
                        nomProduitInput.value = "";
                        return;
                    }

                    nomProduitInput.value = productRef;
                    scannedProducts[productRef] = (scannedProducts[productRef] || 0) + 1;

                    if (scannedProducts[productRef] > requiredProducts[productRef].quantite) {
                        showError(
                            `Trop de produits scannés pour ${productRef}. Requis : ${requiredProducts[productRef].quantite}, Scannés : ${scannedProducts[productRef]}`
                        );
                    } else {
                        requiredProducts[productRef].scanned = scannedProducts[productRef];
                        updateBonContainer(); // Mettre à jour la section des produits scannés
                    }

                    // Ajouter un nouveau champ après validation
                    addProductFields();
                }
            })
            .catch((error) => console.error("Erreur lors de la vérification du produit :", error));
    }

    // Validation des produits scannés avant soumission
    // Validation des produits scannés avant soumission
    form.addEventListener("submit", function (e) {
        e.preventDefault(); // Empêche la soumission par défaut

        const validationErrors = validateScannedProducts();

        if (validationErrors.length > 0) {
            alert(`Erreur(s) :\n${validationErrors.join("\n")}`);
        } else if (!commentaireField.value.trim()) {
            alert("Veuillez ajouter un commentaire.");
        } else {
            // Soumettre le formulaire avec un fetch POST
            const formData = new FormData(form);
            fetch("traitement_commande.php", {
                method: "POST",
                body: formData,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        alert("Commande traitée avec succès !");
                    } else {
                        alert(`Erreur : ${data.error}`);
                    }
                })
                .catch((error) => console.error("Erreur lors de la soumission :", error));
        }
    });

    // Fonction pour afficher les erreurs
    function showError(message) {
        alert(`Erreur : ${message}`);
    }
    // Fonction pour valider les produits scannés
    function validateScannedProducts() {
        const errors = [];

        // Vérifier chaque produit scanné
        for (const productRef in requiredProducts) {
            const requiredQuantity = requiredProducts[productRef].quantite;
            const scannedQuantity = scannedProducts[productRef] || 0;

            if (scannedQuantity < requiredQuantity) {
                errors.push(`Quantité insuffisante pour ${productRef}. Requis : ${requiredQuantity}, scannés : ${scannedQuantity}`);
                for (let i = scannedQuantity; i < requiredQuantity; i++) {
                    addProductFields(true); // Ajoute des champs dynamiques automatiquement
                }
            }
        }

        return errors;
    }

    // Fonction pour afficher les erreurs
    function showError(message) {
        alert(`Erreur : ${message}`);
    }
});
