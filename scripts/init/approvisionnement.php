<?php
// Crée les tables de la réception matériel (bons de livraison et leurs lignes) et des
// demandes d'approvisionnement, puis y pose un jeu d'essai.
// Relançable sans effet de bord : les tables sont créées si absentes et le jeu d'essai
// n'est posé que sur les demandes qui n'ont encore aucun bon de livraison.
$root = is_dir('/var/www/html/dashboard') ? '/var/www/html' : __DIR__ . '/../../src';
require_once $root . '/db.php';
$db = new MyPDO($root . '/my_setting.ini');

$db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS bon_livraison (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_demande INT NOT NULL,
    numero VARCHAR(40) NOT NULL,
    fournisseur VARCHAR(120) NOT NULL,
    date_bl DATE NOT NULL,
    statut ENUM('attendu', 'partielle', 'complete', 'refusee') NOT NULL DEFAULT 'attendu',
    commentaire VARCHAR(1000) DEFAULT NULL,
    receptionne_par INT DEFAULT NULL,
    date_reception DATETIME DEFAULT NULL,
    -- Verrou optimiste : deux chefs d'atelier peuvent ouvrir le même bon.
    revision INT UNSIGNED NOT NULL DEFAULT 0,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY bon_livraison_numero (numero),
    KEY bon_livraison_demande (id_demande),
    CONSTRAINT bon_livraison_demande_fk FOREIGN KEY (id_demande)
        REFERENCES demande_prefabrication (id) ON DELETE CASCADE,
    CONSTRAINT bon_livraison_receptionnaire_fk FOREIGN KEY (receptionne_par)
        REFERENCES Utilisateur (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS bon_livraison_ligne (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_bon INT NOT NULL,
    ordre INT NOT NULL DEFAULT 0,
    reference_article VARCHAR(80) DEFAULT NULL,
    designation VARCHAR(180) NOT NULL,
    unite VARCHAR(16) NOT NULL DEFAULT 'u',
    quantite_attendue DECIMAL(12,3) NOT NULL,
    -- NULL tant que la ligne n'a pas été pointée ; 0 = rien reçu.
    quantite_recue DECIMAL(12,3) DEFAULT NULL,
    commentaire VARCHAR(500) DEFAULT NULL,
    KEY bon_livraison_ligne_bon (id_bon),
    CONSTRAINT bon_livraison_ligne_bon_fk FOREIGN KEY (id_bon)
        REFERENCES bon_livraison (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

$db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS demande_approvisionnement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_demande INT NOT NULL,
    -- Renseignée quand le besoin vient d'un manquant constaté à la réception.
    id_ligne_bl INT DEFAULT NULL,
    designation VARCHAR(180) NOT NULL,
    quantite DECIMAL(12,3) NOT NULL,
    unite VARCHAR(16) NOT NULL DEFAULT 'u',
    motif ENUM('oubli', 'erreur', 'fabrication', 'autre') NOT NULL,
    commentaire VARCHAR(1000) DEFAULT NULL,
    demande_par INT NOT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('nouvelle', 'prise_en_compte', 'commandee', 'livree', 'refusee') NOT NULL DEFAULT 'nouvelle',
    traite_par INT DEFAULT NULL,
    date_traitement DATETIME DEFAULT NULL,
    reponse VARCHAR(1000) DEFAULT NULL,
    KEY demande_approvisionnement_demande (id_demande),
    KEY demande_approvisionnement_statut (statut),
    CONSTRAINT demande_approvisionnement_demande_fk FOREIGN KEY (id_demande)
        REFERENCES demande_prefabrication (id) ON DELETE CASCADE,
    CONSTRAINT demande_approvisionnement_ligne_fk FOREIGN KEY (id_ligne_bl)
        REFERENCES bon_livraison_ligne (id) ON DELETE SET NULL,
    CONSTRAINT demande_approvisionnement_auteur_fk FOREIGN KEY (demande_par)
        REFERENCES Utilisateur (id) ON DELETE CASCADE,
    CONSTRAINT demande_approvisionnement_traitant_fk FOREIGN KEY (traite_par)
        REFERENCES Utilisateur (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

echo "Tables en place.\n";

// ===== Jeu d'essai =====
// Un bon par demande validée, avec des lignes représentatives d'une préfa tuyauterie.
$modeles = [
    ['fournisseur' => 'Aciéries de l’Est', 'lignes' => [
        ['TUB-304L-50', 'Tube inox 304L DN50 — 6 m', 'm', 36],
        ['CDE-90-50', 'Coude 90° à souder DN50', 'u', 8],
        ['BRI-PN16-50', 'Bride plate PN16 DN50', 'u', 4],
        ['JNT-PN16-50', 'Joint plat PN16 DN50', 'u', 4],
    ]],
    ['fournisseur' => 'Comptoir du Tube', 'lignes' => [
        ['TUB-316L-80', 'Tube inox 316L DN80 — 6 m', 'm', 24],
        ['TE-EG-80', 'Té égal à souder DN80', 'u', 3],
        ['RED-80-50', 'Réduction concentrique DN80/DN50', 'u', 2],
        ['BOU-80', 'Bouchon elliptique DN80', 'u', 1],
    ]],
    ['fournisseur' => 'Visserie Industrielle SA', 'lignes' => [
        ['BOU-M16-70', 'Boulon TH M16 × 70 inox A4', 'u', 32],
        ['ECR-M16', 'Écrou H M16 inox A4', 'u', 32],
        ['ELE-308L-32', 'Électrode 308L Ø 3,2 mm', 'kg', 5],
    ]],
];

$demandes = $db->query('SELECT d.id FROM demande_prefabrication d
    LEFT JOIN bon_livraison b ON b.id_demande = d.id
    WHERE d.id_statut = 2 AND b.id IS NULL
    ORDER BY d.id')->fetchAll(PDO::FETCH_COLUMN);

if (!$demandes) {
    echo "Aucune demande validée sans bon de livraison : jeu d'essai déjà en place.\n";
    return;
}

$db->beginTransaction();
try {
    $insererBon = $db->prepare('INSERT INTO bon_livraison (id_demande, numero, fournisseur, date_bl) VALUES (?, ?, ?, ?)');
    $insererLigne = $db->prepare('INSERT INTO bon_livraison_ligne (id_bon, ordre, reference_article, designation, unite, quantite_attendue) VALUES (?, ?, ?, ?, ?, ?)');
    // Le compteur repart du plus grand numéro existant : relancer le script ne crée pas de doublon.
    $dernier = (int) preg_replace('/\D/', '', (string) $db->query("SELECT numero FROM bon_livraison WHERE numero LIKE 'BL-%' ORDER BY id DESC LIMIT 1")->fetchColumn());
    $compteur = $dernier % 1000;
    $lignesPosees = 0;

    foreach ($demandes as $index => $idDemande) {
        $modele = $modeles[$index % count($modeles)];
        $compteur++;
        $numero = sprintf('BL-%s-%03d', date('Y'), $compteur);
        $insererBon->execute([$idDemande, $numero, $modele['fournisseur'], date('Y-m-d', strtotime('-' . (3 + $index) . ' days'))]);
        $idBon = (int) $db->lastInsertId();
        foreach ($modele['lignes'] as $ordre => [$reference, $designation, $unite, $quantite]) {
            $insererLigne->execute([$idBon, $ordre, $reference, $designation, $unite, $quantite]);
            $lignesPosees++;
        }
    }

    $db->commit();
    echo count($demandes), " bon(s) de livraison posé(s), $lignesPosees ligne(s).\n";
} catch (Throwable $erreur) {
    $db->rollBack();
    fwrite(STDERR, 'Jeu d’essai non posé : ' . $erreur->getMessage() . "\n");
    exit(1);
}
