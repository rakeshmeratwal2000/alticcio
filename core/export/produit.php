<?php

require_once "abstract_export.php";

class ExportProduit extends AbstractExport {

	public $export_table = "fiches_produits";

	public function export() {
		$date_export = time();
		$produits = $this->produits_a_exporter();
		$fields = $this->fields();
		$this->prepare($fields, $produits);

		$ids_produits = array();
		$value_list = array();
		foreach($produits as $produit_data) {
			$ids_produits[] = $produit_data['id'];
			$value_list[] = "({$produit_data['id']}, {$date_export})";
		}
		$ids_produits_list = implode(",", $ids_produits);

		$q = <<<SQL
DELETE FROM dt_exports_produits WHERE id_produits IN ($ids_produits_list) 
SQL;
		$this->sql->query($q);

		$values_list = implode(",", $value_list); 
		$q = <<<SQL
INSERT INTO dt_exports_produits (id_produits, date_export) VALUES $values_list
SQL;
		$this->sql->query($q);

		$values = array();
		$i = 1;
		foreach ($this->data($produits) as $data) {
			$values[] = $data;
			if ($i % 500 == 0) {
				$this->insert_values($fields, $values);
				$values = array();
			}
			$i++;
		}
		$this->insert_values($fields, $values);
	}

	public function fields() {
		$fields = array(
			'id_langue',
			'code_langue',
			'id_produit',
			'offre',
			'recyclage',
			'produit',
			'attributs_produits',
			'avantages_produit',
			'description',
			'description_longue',
			'mode_emploi',
			'entretien',
		);
		for ($i = 1; $i <= $this->max_images(); $i++) {
			$fields[] = "image$i";
		}
		$fields = array_merge($fields, array(
			'type_sku',
			'classement',
			'id_sku',
			'reference',
			'designation',
		));
		for ($i = 65; $i < 65 + $this->max_prix(); $i++) {
			$a = chr($i);
			$fields[] = "Prix_{$a}_qte";
			$fields[] = "Prix_{$a}_montant";
		}
		$fields = array_merge($fields, array(
			'cartouche_prix',
			'ecotaxe',
			'note_prix',
			'note_personnalisation',
			'attribut_vente',
			'attributs_pictos',
			'Url_raccourcie',
			'nouveau',
			'attributs_fiche',
			'actif',
		));

		return $fields;
	}

	function data($produits) {
		$phrase = new Phrase($this->sql);
		$produit = new Produit($this->sql, $phrase);
		$sku = new Sku($this->sql);
		$url_redirection = new UrlRedirection($this->sql, 5);

		$data_lignes = array();
		$couples = array();
		
		foreach ($this->langues() as $id_langues => $code_langue) {
			foreach ($produits as $data) { 	
				$produit->load($data['id']);
				$produit_values = $produit->values;
				$produit_phrases = $produit->phrases_dynamiques();
				foreach (array('variante' => "variantes", 'accessoire' => "accessoires") as $type_sku => $method) {
					$notes_prix = array();
					if ($produit_values['id_types_produits'] == 2) {
						$notes_prix['perso'] = "perso";
					}
					foreach ($produit->$method() as $variante) {
						if (!isset($couples[$data['id']][$variante['id_sku']])) {
							$couples[$data['id']][$variante['id_sku']] = true;
							$sku->load($variante['id_sku']);
							$sku_values = $sku->values;
							$sku_phrases = $phrase->get($sku->phrases());

							$data_ligne = array(
								$id_langues,
								$code_langue,
								$produit_values['id'],
								$produit_values['offre'],
								$this->recyclage($produit_values['id_recyclage']),
								$this->phrase('phrase_nom', $produit_phrases, $code_langue),
								$this->attributs($produit, $produit_phrases, $code_langue, "top"),
								$this->phrase('phrase_avantages_produit', $produit_phrases, $code_langue),
								$this->phrase('phrase_description_courte', $produit_phrases, $code_langue),
								$this->phrase('phrase_description', $produit_phrases, $code_langue),
								$this->phrase('phrase_mode_emploi', $produit_phrases, $code_langue),
								$this->phrase('phrase_entretien', $produit_phrases, $code_langue),
							);
							$images = $this->images($type_sku == "variante" ? $produit : $sku, $this->max_images());
							$data_ligne = array_merge($data_ligne, $images);
							$nom_sku = (isset($sku_phrases['phrase_commercial'][$code_langue]) and $sku_phrases['phrase_commercial'][$code_langue]) ? 'phrase_commercial' : 'phrase_ultralog';
							$data_ligne = array_merge($data_ligne, array(
								$type_sku,
								$variante['classement'],
								$sku_values['id'],
								$sku_values['ref_ultralog'],
								$this->phrase($nom_sku, $sku_phrases, $code_langue),
							));
							$id_catalogues = 0;
							$prix = $this->prix($sku, $this->max_prix(), $id_catalogues);
							$data_ligne = array_merge($data_ligne, $prix);
							$prix = $sku->prix();
							$prix_unitaire = $type_sku == "variante" ? $sku->prix_unitaire_min($id_catalogues) : $prix['montant_ht'];
							if ($prix['franco'] == 0) {
								$notes_prix['frai_port'] = "frais_port";
							}
							$data_ligne = array_merge($data_ligne, array(
								$prix_unitaire,
								$this->ecotaxe($sku_values['id'], $id_catalogues),
								implode(" ", $notes_prix),
								"", // note personnalisation 
								$this->attributs($produit, $produit_phrases, $code_langue, "top", 1),
								$this->attributs($produit, $produit_phrases, $code_langue, "pictos_vente", 1),
								"http://www.doublet.com/".$url_redirection->long2short($this->phrase('phrase_url_key', $produit_phrases, $code_langue), $this->section($id_catalogues)),
								$produit_values['nouveau'],
								$this->attributs($produit, $produit_phrases, $code_langue, "fiche_technique"),
								$produit_values['actif'],
							));
							$data_lignes[] = $data_ligne;
						}
					}
				}
			}
		}

		return $data_lignes;
	}

	public function produits_a_exporter() {
		$q = <<<SQL
SELECT p.id, ep.date_export, p.date_modification AS dm1,
MAX(s1.date_modification) AS dm2, MAX(s2.date_modification) AS dm3
FROM dt_produits AS p
LEFT OUTER JOIN dt_sku_accessoires AS sa ON sa.id_produits = p.id
LEFT OUTER JOIN dt_sku AS s1 ON s1.id = sa.id_sku
LEFT OUTER JOIN dt_sku_variantes AS sv ON sv.id_produits = p.id
LEFT OUTER JOIN dt_sku AS s2 ON s2.id = sa.id_sku
LEFT OUTER JOIN dt_exports_produits AS ep ON ep.id_produits = p.id
GROUP BY p.id
HAVING ep.date_export IS NULL
OR ep.date_export < date_modification
OR ep.date_export < MAX(s1.date_modification)
OR ep.date_export < MAX(s2.date_modification)
LIMIT 200
SQL;
		$res = $this->sql->query($q);
		$produits = array();
		while ($row = $this->sql->fetch($res)) {
			$produits[] = array(
				'id' => $row['id'],
				'last_update' => max($row['dm1'], $row['dm2'], $row['dm3']),
				'last_export' => $row['date_export'],
			);
		}

		return $produits;
	}

	public function prepare($fields, $produits) {
		$field_list = "";
		foreach ($fields as $i => $field) {
			if ($field != "id_produit" and $field != "id_sku" and $field != "id_langue") {
				$field_list .= "`$field` mediumtext NOT NULL,";
			}
			else {
				$field_list .= "`$field` int(11) NOT NULL,";
				$this->$field = $i;
			}
		}
		$q = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->export_table}` (
  $field_list
  PRIMARY KEY (`id_langue`,`id_produit`,`id_sku`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;
SQL;
		$this->sql_export->query($q);

		$ids_produits = array();
		foreach($produits as $produit_data) {
			$ids_produits[] = $produit_data['id'];
		}
		$ids_produits_list = implode(",", $ids_produits);

		$q = <<<SQL
DELETE FROM `{$this->export_table}` WHERE id_produit IN ($ids_produits_list)
SQL;
		$this->sql_export->query($q);
	}

	function max_images() {
		if (isset($this->max_images)) {
			return $this->max_images;
		}
		$q = <<<SQL
SELECT COUNT(id) AS nb_images FROM dt_images_produits GROUP BY id_produits ORDER BY nb_images DESC LIMIT 0, 1
SQL;
		$res = $this->sql->query($q);
		$row = $this->sql->fetch($res);

		return $this->max_images = $row['nb_images'];
	}

	function images($produit, $max_images) {
		$images = array();
		foreach ($produit->images() as $img) {
			if ($img['affichage']) {
				$images[] = $img['ref'];
			}
		}
		$ret = array();
		for ($i = 1; $i <= $max_images; $i++) {
			$ret[] = isset($images[$i - 1]) ? $images[$i - 1] : "";
		}

		return $ret;
	}

	function max_prix() {
		if (isset($this->max_prix)) {
			return $this->max_prix;
		}
		$q = <<<SQL
SELECT COUNT(id) AS nb_prix FROM dt_prix_degressifs GROUP BY id_sku, id_catalogues ORDER BY nb_prix DESC LIMIT 0, 1
SQL;
		$res = $this->sql->query($q);
		$row = $this->sql->fetch($res);

		return $this->max_prix = $row['nb_prix'];
	}

	function prix($sku, $max_prix, $id_catalogues) {
		$prix = array();
		$p = $sku->prix_catalogue($id_catalogues);
		$prix[] = $sku->values['min_commande'];
		$prix[] = $this->cartouche_prix($p['montant_ht']);
		foreach ($sku->prix_degressifs_catalogue($id_catalogues) as $p) {
			if ($p['quantite'] > 1) {
				$prix[] = $p['quantite'];
				$prix[] = $this->cartouche_prix($p['montant_ht']);
			}
		}
		$ret = array();
		for ($i = 1; $i <= $max_prix; $i++) {
			$ret[] = isset($prix[$i * 2 - 2]) ? $prix[$i * 2 - 2] : "";
			$ret[] = isset($prix[$i * 2 - 1]) ? $prix[$i * 2 - 1] : "";
		}

		return $ret;
	}

	function cartouche_prix($prix) {
		return $prix;
	}

	function langues() {
		$q = <<<SQL
SELECT id, code_langue FROM dt_langues
SQL;
		$res = $this->sql->query($q);
		$langues = array();
		while ($row = $this->sql->fetch($res)) {
			$langues[$row['id']] = $row['code_langue'];
		}

		return $langues;
	}

	function phrase($field, $phrases, $code_langue) {
		return isset($phrases[$field][$code_langue]) ? strip_tags($phrases[$field][$code_langue]) : "";
	}

	function recyclage($id_recyclage) {
		if (isset($this->recyclages)) {
			return isset($this->recyclages[$id_recyclage]) ? $this->recyclages[$id_recyclage] : "";
		}
		$this->recyclages = array();
		$q = <<<SQL
SELECT id, numero FROM dt_recyclage
SQL;
		$res = $this->sql->query($q);
		while ($row = $this->sql->fetch($res)) {
			$this->recyclages[$row['id']] = $row['numero'];
		}

		return isset($this->recyclages[$id_recyclage]) ? $this->recyclages[$id_recyclage] : "";
	}

	function attributs($produit, $phrases, $code_langue, $type, $nb = null) {
		$separator = "\n";
		$attributs = array();
		foreach ($produit->attributs_data() as $attr) {
			$attr = $attr[0]; // On ne gère pas les valeurs multiples
			if ($attr[$type]) {
				if ($attr['phrase_valeur']) {
					if (is_array($attr['phrase_valeur'])) {
						$valeurs_unites = array();
						foreach ($phrases['valeurs_attributs'][$attr['id_attributs']][0] as $v) {
							$valeurs_unites[] = trim("{$v[$code_langue]} {$attr['unite']}");
						}
						$valeur = implode(", ", $valeurs_unites);
					}
					else {
						$valeur = $phrases['valeurs_attributs'][$attr['id_attributs']][0][$code_langue];
						$valeur .= " {$attr['unite']}";
					}
				}
				else {
					$choices = array(
						0 => "N/A",		
						1 => "Oui",
						2 => "Non",
					);
					if (is_array($attr['valeur_numerique'])) {
						$valeurs_unites = array();
						foreach ($attr['valeur_numerique'] as $v) {
							switch ($attr['id_types_attributs']) {
								case 1:
									$v = $choices[$v];
									break;
								case 2:
									$v .= "/5";
									break;
							}
							$valeurs_unites[] = trim("{$v} {$attr['unite']}");
						}
						$valeur = implode(", ", $valeurs_unites);
					}
					else {
						$valeur = $attr['valeur_numerique'];
						switch ($attr['id_types_attributs']) {
							case 1:
								$valeur = $choices[$valeur];
								break;
							case 2:
								$valeur .= "/5";
								break;
						}
						$valeur .= " {$attr['unite']}";
					}
				}
				$attributs[] = $phrases['attributs'][$attr['id_attributs']][$code_langue]." : ".trim($valeur);
			}
		}
		return implode($separator, $nb === null ? $attributs : array_slice($attributs, 0, $nb));
	}

	function ecotaxe($id_sku, $id_catalogues) {
		$q = <<<SQL
SELECT montant, id_catalogues FROM dt_ecotaxes AS e
INNER JOIN dt_langues AS l ON l.id_pays = e.id_pays
INNER JOIN dt_catalogues AS c ON c.id_langues = l.id
WHERE id_sku = $id_sku AND (id_catalogues = 0 OR id_catalogues = $id_catalogues)
ORDER BY id_catalogues DESC
SQL;
		$res = $this->sql->query($q);
		$row = $this->sql->fetch($res);

		return $row['montant'];
	}

	function section($id_catalogues) {
		switch ($id_catalogues) {
			case 5 : return 2;
			case 9 : return 3;
			case 6 : return 4;
			case 10 : return 5;
			default : return 1;
		}
	}
}
