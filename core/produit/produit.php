<?php

require_once "abstract_object.php";

class Produit extends AbstractObject {

	protected $type = "produit";
	protected $table = "dt_produits";
	protected $images_table = "dt_images_produits";
	protected $phrase_fields = array(
		'phrase_nom',
		'phrase_commercial',
		'phrase_description_courte',
		'phrase_description',
		'phrase_url_key',
		'phrase_meta_title',
		'phrase_meta_description',
		'phrase_meta_keywords',
		'phrase_entretien',
		'phrase_mode_emploi',
		'phrase_avantages_produit',
		'phrase_designation_auto',
	);

	public function liste(&$filter = null) {
		$q = <<<SQL
SELECT pr.id, pr.ref, ph.phrase, pr.actif FROM dt_produits AS pr
LEFT OUTER JOIN dt_phrases AS ph ON ph.id = pr.phrase_nom
LEFT OUTER JOIN dt_langues AS l ON l.id = ph.id_langues
WHERE (l.code_langue = '{$this->langue}' OR pr.phrase_nom = 0)
SQL;
		if ($filter === null) {
			$filter = $this->sql;
		}
		$res = $filter->query($q);

		$liste = array();
		while ($row = $filter->fetch($res)) {
			$liste[$row['id']] = $row;
		}
		
		return $liste;
	}

	public function save($data = null) {
		if ($data === null) {
			$data = array('produit' => array('id' => $this->id));
		}
		$data['produit']['date_modification'] = $_SERVER['REQUEST_TIME'];
		$id = parent::save($data);

		foreach (array('composants', 'variantes', 'accessoires') as $associated_sku) {
			if (isset($data[$associated_sku])) {
				$q = <<<SQL
DELETE FROM dt_sku_$associated_sku WHERE id_produits = $id
SQL;
				$this->sql->query($q);

				$values = array();
				foreach ($data[$associated_sku] as $id_sku => $sku) {
					$classement = (int)$sku['classement'];
					$values[] = "($id, {$id_sku}, {$classement})";
				}
				if (count($values)) {
					$values = implode(",", $values);
					$q = <<<SQL
INSERT INTO dt_sku_$associated_sku (id_produits, id_sku, classement) VALUES $values
SQL;
					$this->sql->query($q);
				}
			}
		}

		$produits = array('complementaires' => "id_produits_compl", 'similaires' => "id_produits_sim");
		foreach ($produits as $associated_produit => $associated_id_field) {
			if (isset($data[$associated_produit])) {
				$q = <<<SQL
DELETE FROM dt_produits_$associated_produit WHERE id_produits = $id
SQL;
				$this->sql->query($q);
				$values = array();
				foreach ($data[$associated_produit] as $id_produits => $produit) {
					$classement = (int)$produit['classement'];
					$values[] = "($id, {$id_produits}, {$classement})";
				}
				if (count($values)) {
					$values = implode(",", $values);
					$q = <<<SQL
INSERT INTO dt_produits_$associated_produit (id_produits, $associated_id_field, classement) VALUES $values
SQL;
					$this->sql->query($q);
				}
			}
		}

		if (isset($data['attributs'])) {
			$q = <<<SQL
DELETE FROM dt_produits_attributs WHERE id_produits = $id
SQL;
			$this->sql->query($q);
			foreach ($data['attributs'] as $attribut_id => $attribut) {
				$type_valeur = "valeur_numerique";
				$valeur = $attribut;
				if (isset($data['phrases']['valeurs_attributs'][$attribut_id])) {
					$type_valeur = "phrase_valeur";
					if (is_array($data['phrases']['valeurs_attributs'][$attribut_id])) {
						foreach ($data['phrases']['valeurs_attributs'][$attribut_id] as $lang => $phrase) {
							$valeur = $this->phrase->save($lang, $phrase, $attribut);
						}
					}
					$valeur = (int)$valeur;
				}
				else {
					$valeur = (float)str_replace(" ", "", str_replace(",", ".", $valeur));
				}
				$q = <<<SQL
INSERT INTO dt_produits_attributs (id_attributs, id_produits, $type_valeur)
VALUES ($attribut_id, $id, $valeur)
SQL;
				$this->sql->query($q);
			}
		}

		if (isset($data['personnalisation'])) {
				$q = <<<SQL
DELETE FROM dt_personnalisations_produits WHERE id_produits = $id
SQL;
				$this->sql->query($q);
			foreach ($data['personnalisation'] as $type => $perso) {
				if ($perso['has']) {
					$q = <<<SQL
INSERT INTO dt_personnalisations_produits (`id_produits`, `type`, `libelle`)
VALUES ($id, '$type', '{$perso['libelle']}')
SQL;
					$this->sql->query($q);
				}
			}
		}

		$q = <<<SQL
SELECT id, code_langue FROM dt_langues
SQL;
		$res = $this->sql->query($q);
		$designations = array();
		$langues = array();
		while ($row = $this->sql->fetch($res)) {
			$designations[$row['id']] = $this->designations_auto($row['id'], $id);
			$langues[$row['id']] = $row['code_langue'];
		}
		
		$q = <<<SQL
SELECT s.phrase_commercial, s.id
FROM dt_sku AS s
INNER JOIN dt_sku_variantes AS sv ON sv.id_sku = s.id AND sv.id_produits = {$id}
SQL;
		$res = $this->sql->query($q);
		while ($row = $this->sql->fetch($res)) {
			$id_phrase = 0;
			foreach ($langues as $id_langue => $code_langue) {
				$designation = $designations[$id_langue][$row['id']];
				if ($designation['auto']) {
					$id_phrase = $this->phrase->save($code_langue, addslashes($designation['auto']), (int)$row['phrase_commercial']);
				}
			}
			$q = <<<SQL
	UPDATE dt_sku SET phrase_commercial = {$id_phrase} WHERE id = {$row['id']}
SQL;
			$this->sql->query($q);
		}
		return $id;
	}

	public function add_gabarit($data, $file, $dir) {
		if (is_array($file)) {
			preg_match("/(\.[^\.]*)$/", $file['name'], $matches);
			$ext = $matches[1];
			$file_name = md5_file($file['tmp_name']).$ext;
			move_uploaded_file($file['tmp_name'], $dir.$file_name);
		}
		else if (file_exists($file)) {
			preg_match("/(\.[^\.]*)$/", $file, $matches);
			$ext = $matches[1];
			$file_name = md5_file($file).$ext;
			copy($file, $dir.$file_name);
		}

		$q = <<<SQL
DELETE FROM dt_gabarits_produits WHERE id_produits = {$this->id}
SQL;
		$this->sql->query($q);

		$q = <<<SQL
INSERT INTO dt_gabarits_produits (id_produits, ref)
VALUES ({$this->id}, '$file_name')
SQL;
		$this->sql->query($q);
	}

	public function delete_gabarit() {
		$q = <<<SQL
DELETE FROM dt_gabarits_produits WHERE id_produits = {$this->id}
SQL;
		$this->sql->query($q);
	}

	public function gabarit() {
		$q = <<<SQL
SELECT ref FROM dt_gabarits_produits WHERE id_produits = {$this->id}
SQL;
		$res = $this->sql->query($q);
		$row = $this->sql->fetch($res);

		return $row ? $row['ref'] : "";
	}

	public function delete($data) {
		$q = <<<SQL
DELETE FROM dt_sku_accessoires WHERE id_produits = {$this->id}
SQL;
		$this->sql->query($q);

		$q = <<<SQL
DELETE FROM dt_sku_composants WHERE id_produits = {$this->id}
SQL;
		$this->sql->query($q);

		$q = <<<SQL
DELETE FROM dt_sku_variantes WHERE id_produits = {$this->id}
SQL;
		$this->sql->query($q);

		$q = <<<SQL
DELETE FROM dt_produits_attributs WHERE id_produits = {$this->id}
SQL;
		$this->sql->query($q);

		$q = <<<SQL
DELETE FROM dt_produits_complementaires WHERE id_produits = {$this->id} OR id_produits_compl = {$this->id}
SQL;
		$this->sql->query($q);

		$q = <<<SQL
DELETE FROM dt_produits_similaires WHERE id_produits = {$this->id} OR id_produits_sim = {$this->id}
SQL;
		$this->sql->query($q);

		$q = <<<SQL
DELETE FROM dt_gabarits_produits WHERE id_produits = {$this->id}
SQL;
		$this->sql->query($q);

		parent::delete($data);
	}

	public function applications() {
		$q = <<<SQL
SELECT a.id, p.phrase AS nom FROM dt_applications AS a
LEFT OUTER JOIN dt_phrases AS p ON p.id = a.phrase_nom
LEFT OUTER JOIN dt_langues AS l ON l.id = p.id_langues
WHERE (l.code_langue = '{$this->langue}' OR a.phrase_nom = 0)
ORDER BY nom
SQL;
		$res = $this->sql->query($q);
		$applications = array();
		while ($row = $this->sql->fetch($res)) {
			$applications[$row['id']] = $row['nom'];
		}

		return $applications;
	}
	
	public function gammes() {
		$q = <<<SQL
SELECT g.id, p.phrase AS nom FROM dt_gammes AS g
LEFT OUTER JOIN dt_phrases AS p ON p.id = g.phrase_nom
LEFT OUTER JOIN dt_langues AS l ON l.id = p.id_langues
WHERE (l.code_langue = '{$this->langue}' OR g.phrase_nom = 0)
ORDER BY nom
SQL;
		$res = $this->sql->query($q);
		$gammes = array("...");
		while ($row = $this->sql->fetch($res)) {
			$gammes[$row['id']] = $row['nom'];
		}

		return $gammes;
	}

	public function recyclage($id_langue) {
		$q = "SELECT r.id, r.numero, p.phrase 
				FROM dt_recyclage AS r 
				LEFT JOIN dt_phrases AS p 
				ON p.id = r.phrase_nom
				AND p.id_langues = ".$id_langue;
		$res = $this->sql->query($q);
		$recycle = array('...');
		while($row = $this->sql->fetch($res)) {
			$recycle[$row['id']] = $row['numero'].' : '.$row['phrase'];
		}
		return $recycle;
	}
	
	public function attributs() {
		$attributs = array();
		$q = <<<SQL
SELECT id_attributs, valeur_numerique, phrase_valeur FROM dt_produits_attributs
WHERE id_produits = {$this->id}
SQL;
		$res = $this->sql->query($q);
		
		while ($row = $this->sql->fetch($res)) {
			$value = $row['phrase_valeur'] ?  $row['phrase_valeur'] : $row['valeur_numerique'];
			$attributs[$row['id_attributs']] = $value;
		}

		return $attributs;
	}

	public function attributs_names() {
		$attributs = array();
		$q = <<<SQL
SELECT a.phrase_nom, pa.id_attributs FROM dt_produits_attributs AS pa
INNER JOIN dt_attributs AS a ON a.id = pa.id_attributs
WHERE pa.id_produits = {$this->id}
SQL;
		$res = $this->sql->query($q);
		
		while ($row = $this->sql->fetch($res)) {
			$attributs[$row['id_attributs']] = $row['phrase_nom'];
		}

		return $attributs;
	}

	public function attributs_filtre($id_langues) {
		$q = <<<SQL
SELECT DISTINCT(a.id), a.id_types_attributs, ph.phrase AS nom
FROM dt_sku_variantes AS sv
INNER JOIN dt_sku_attributs AS sa ON sa.id_sku = sv.id_sku
INNER JOIN dt_attributs AS a ON a.id = sa.id_attributs
INNER JOIN dt_applications_attributs AS aa ON aa.id_attributs = a.id AND aa.filtre = 1
INNER JOIN dt_produits AS p ON p.id = sv.id_produits AND p.id_applications = aa.id_applications
INNER JOIN dt_phrases AS ph ON ph.id = a.phrase_nom AND ph.id_langues = {$id_langues}
WHERE sv.id_produits = {$this->id}
ORDER BY aa.classement ASC
SQL;
		$attributs = array();
		$res = $this->sql->query($q);
		while ($row = $this->sql->fetch($res)) {
			switch ($row['id_types_attributs']) {
				case 5 : // select
					$q = <<<SQL
SELECT oa.phrase_option AS option_id, ph.phrase AS option_name
FROM dt_options_attributs AS oa
INNER JOIN dt_phrases AS ph ON ph.id = oa.phrase_option AND ph.id_langues = {$id_langues}
INNER JOIN dt_sku_attributs AS sa ON sa.phrase_valeur = oa.phrase_option AND sa.id_attributs = {$row['id']}
INNER JOIN dt_sku_variantes AS sv ON sv.id_sku = sa.id_sku AND sv.id_produits = {$this->id}
WHERE oa.id_attributs = {$row['id']}
ORDER by oa.classement ASC
SQL;
					break;
				case 6 : // reference
					$q = <<<SQL
SELECT table_name, field_label, field_value
FROM dt_attributs_references
WHERE id_attributs = {$row['id']}
SQL;
					$res2 = $this->sql->query($q);
					$row2 = $this->sql->fetch($res2);
					if (substr($row2['field_label'], 0, 6) == "phrase") {
						$q = <<<SQL
SELECT DISTINCT(t.{$row2['field_value']}) AS option_id, ph.phrase AS option_name
FROM {$row2['table_name']} AS t
INNER JOIN dt_phrases AS ph ON ph.id = t.{$row2['field_label']} AND ph.id_langues = {$id_langues}
INNER JOIN dt_sku_attributs AS sa ON sa.valeur_numerique = t.{$row2['field_value']} AND sa.id_attributs = {$row['id']}
INNER JOIN dt_sku_variantes AS sv ON sv.id_sku = sa.id_sku AND sv.id_produits = {$this->id}
ORDER BY t.{$row2['field_value']} ASC
SQL;
					}
					else {
						if ($row2['field_value'][0] != ucfirst($row2['field_value'][0])) {
							$row2['field_value'] = "t.".$row2['field_value'];
						}
						if ($row2['field_label'][0] != ucfirst($row2['field_label'][0])) {
							$row2['field_label'] = "t.".$row2['field_label'];
						}
						$q = <<<SQL
SELECT DISTINCT({$row2['field_value']}) AS option_id, {$row2['field_label']} AS option_name
FROM {$row2['table_name']} AS t
INNER JOIN dt_sku_attributs AS sa ON sa.valeur_numerique = {$row2['field_value']} AND sa.id_attributs = {$row['id']}
INNER JOIN dt_sku_variantes AS sv ON sv.id_sku = sa.id_sku AND sv.id_produits = {$this->id}
ORDER BY {$row2['field_value']} ASC
SQL;
					}
					break;
				default : $q = false;
					break;
			}
			if ($q) {
				$res2 = $this->sql->query($q);
				$options = array();
				while ($row2 = $this->sql->fetch($res2)) {
					$options[$row2['option_id']] = $row2['option_name'];
				}
				$attributs[$row['id']] = array('nom' => $row['nom'], 'options' => $options);
			}
		}

		return $attributs;
	}

	public function variantes_filtre() {
		$q = <<<SQL
SELECT sv.id_sku, a.id, sa.valeur_numerique, sa.phrase_valeur
FROM dt_sku_variantes AS sv
INNER JOIN dt_sku_attributs AS sa ON sa.id_sku = sv.id_sku
INNER JOIN dt_attributs AS a ON a.id = sa.id_attributs
INNER JOIN dt_applications_attributs AS aa ON aa.id_attributs = a.id AND aa.filtre = 1
WHERE sv.id_produits = {$this->id}
ORDER BY aa.classement ASC
SQL;
		$variantes = array();
		$res = $this->sql->query($q);
		while ($row = $this->sql->fetch($res)) {
			$variantes[$row['id_sku']][$row['id']] = $row['phrase_valeur'] ? $row['phrase_valeur'] : $row['valeur_numerique'];
		}

		return $variantes;
	}

	public function attributs_data() {
		$attributs = array();
		$q = <<<SQL
SELECT a.phrase_nom, a.id_types_attributs, um.unite, pa.id_attributs, pa.valeur_numerique, pa.phrase_valeur, aa.fiche_technique, aa.pictos_vente, aa.top, aa.comparatif, aa.filtre FROM dt_produits_attributs AS pa
INNER JOIN dt_produits AS p ON pa.id_produits = p.id
INNER JOIN dt_applications_attributs AS aa ON p.id_applications = aa.id_applications AND aa.id_attributs = pa.id_attributs
INNER JOIN dt_attributs AS a ON a.id = pa.id_attributs
LEFT OUTER JOIN dt_unites_mesure AS um ON um.id = a.id_unites_mesure
WHERE pa.id_produits = {$this->id}
ORDER BY aa.classement ASC
SQL;
		$res = $this->sql->query($q);
		
		while ($row = $this->sql->fetch($res)) {
			$attributs[$row['id_attributs']] = $row;
		}

		return $this->attributs_data_from_variantes($attributs);
	}

	public function attributs_data_from_variantes($attributs = array()) {
		$variantes = $this->variantes();
		if (count($variantes)) {
			$variantes_ids = implode(",", array_keys($variantes));
			$q = <<<SQL
SELECT sa.id_attributs, sa.valeur_numerique, sa.phrase_valeur FROM dt_sku_attributs AS sa
INNER JOIN dt_sku_variantes AS sv ON sv.id_sku = sa.id_sku
WHERE sa.id_sku IN ({$variantes_ids})
ORDER BY sv.classement ASC
SQL;
			$res = $this->sql->query($q);
			
			$valeurs_numeriques = array();
			$phrases_valeurs = array();
			$ids_attributs = array();
			while ($row = $this->sql->fetch($res)) {
				if ($row['phrase_valeur']) {
					$phrases_valeurs[$row['id_attributs']][] = $row['phrase_valeur'];
				}
				else {
					$valeurs_numeriques[$row['id_attributs']][] = $row['valeur_numerique'];
				}
				$ids_attributs[$row['id_attributs']] = $row['id_attributs'];
			}
			foreach ($ids_attributs as $id_attributs) {
				if (isset($attributs[$id_attributs])) {
					if (isset($phrases_valeurs[$id_attributs])) {
						$attributs[$id_attributs]['valeur_numerique'] = array();
						$attributs[$id_attributs]['phrase_valeur'] = array_unique($phrases_valeurs[$id_attributs]);
					}
					else {
						$attributs[$id_attributs]['valeur_numerique'] = array_unique($valeurs_numeriques[$id_attributs]);
						$attributs[$id_attributs]['phrase_valeur'] = array();
					}
					$attributs[$id_attributs]['id_attributs'] = $id_attributs;
				}
			}
		}

		return $attributs;
	}

	public function phrases() {
		$ids = parent::phrases();
		$attributs = $this->attributs_names();
		$ids['attributs'] = array();
		foreach ($attributs as $attribut_id => $value) {
			$ids['attributs'][$attribut_id] = $value;
		}
		$ids['valeurs_attributs'] = array();
		$attributs = $this->attributs_data();
		foreach ($attributs as $attribut) {
			if ($attribut['phrase_valeur']) {
				$ids['valeurs_attributs'][$attribut['id_attributs']] = $attribut['phrase_valeur'];
			}
		}
		return $ids;
	}

	public function types($name_as_key = false) {
		$q = <<<SQL
SELECT id, nom FROM dt_types_produits
SQL;
		$res = $this->sql->query($q);

		$types = array();
		while($row = $this->sql->fetch($res)) {
			$types[$name_as_key ? $row['nom'] : $row['id']] = $row['nom'];
		}

		return $types;
	}

	private function associated_sku($table) {
		if (!isset($this->id)) {
			return array();
		}
		$q = <<<SQL
SELECT id_sku, classement FROM $table WHERE id_produits = {$this->id}
ORDER BY classement ASC
SQL;
		$res = $this->sql->query($q);

		$ids = array();
		while ($row = $this->sql->fetch($res)) {
			$ids[$row['id_sku']] = $row;
		}

		return $ids;
	}

	public function all_associated_sku($table, &$filter = null) {
		$q = <<<SQL
SELECT s.id, s.ref_ultralog, p.phrase AS nom, link.classement FROM dt_sku AS s
LEFT OUTER JOIN $table AS link ON link.id_sku = s.id AND link.id_produits = {$this->id}
LEFT OUTER JOIN dt_phrases AS p ON p.id = s.phrase_ultralog
LEFT OUTER JOIN dt_langues AS l ON l.id = p.id_langues
WHERE (l.code_langue = '{$this->langue}' OR s.phrase_ultralog = 0)
SQL;
		if ($filter === null) {
			$filter = $this->sql;
		}
		$res = $filter->query($q);

		$liste = array();
		while ($row = $filter->fetch($res)) {
			$liste[$row['id']] = $row;
		}
		
		return $liste;
	}

	public function composants() {
		return $this->associated_sku('dt_sku_composants');
	}

	public function all_composants(&$filter = null) {
		return $this->all_associated_sku('dt_sku_composants', $filter);
	}

	public function accessoires() {
		return $this->associated_sku('dt_sku_accessoires');
	}

	public function all_accessoires(&$filter = null) {
		return $this->all_associated_sku('dt_sku_accessoires', $filter);
	}

	public function variantes() {
		return $this->associated_sku('dt_sku_variantes');
	}

	public function all_variantes(&$filter = null) {
		return $this->all_associated_sku('dt_sku_variantes', $filter);
	}

	private function associated_produits($table, $id_field) {
		if (!isset($this->id)) {
			return array();
		}
		$q = <<<SQL
SELECT $id_field, classement FROM $table WHERE id_produits = {$this->id}
SQL;
		$res = $this->sql->query($q);

		$ids = array();
		while ($row = $this->sql->fetch($res)) {
			$ids[$row[$id_field]] = $row;
		}

		return $ids;
	}

	private function all_associated_produits($table, $id_field, &$filter = null) {
		$q = <<<SQL
SELECT pr.id, pr.ref, ph.phrase, link.classement FROM dt_produits AS pr
LEFT OUTER JOIN dt_phrases AS ph ON ph.id = pr.phrase_nom
LEFT OUTER JOIN dt_langues AS l ON l.id = ph.id_langues
LEFT OUTER JOIN {$table} AS link ON link.{$id_field} = pr.id AND link.id_produits = {$this->id}
WHERE (l.code_langue = '{$this->langue}' OR pr.phrase_nom = 0)
SQL;
		if ($filter === null) {
			$filter = $this->sql;
		}
		$res = $filter->query($q);

		$liste = array();
		while ($row = $filter->fetch($res)) {
			$liste[$row['id']] = $row;
		}
		
		return $liste;
	}

	public function complementaires() {
		return $this->associated_produits("dt_produits_complementaires", "id_produits_compl");
	}

	public function all_complementaires(&$filter) {
		return $this->all_associated_produits("dt_produits_complementaires", "id_produits_compl", $filter);
	}

	public function similaires() {
		return $this->associated_produits("dt_produits_similaires", "id_produits_sim");
	}

	public function all_similaires(&$filter = null) {
		return $this->all_associated_produits("dt_produits_similaires", "id_produits_sim", $filter);
	}

	public function fiche_perso($user_id, $default) {
		$q = <<<SQL
SELECT * FROM dt_fiches_produits WHERE id_users = $user_id ORDER BY classement
SQL;
		$res = $this->sql->query($q);
		$fiche = array();
		while ($row = $this->sql->fetch($res)) {
			$fiche[$row['zone']][$row['element']] = $row;
		}

		// Si la fiche n'existe pas, on la crée.
		if (!count($fiche)) {
			$data = array();
			foreach ($default as $zone => $elements) {
				$i = 0;
				foreach ($elements as $element) {
					$data['fiche'][$element] = array(
						'zone' => $zone,
						'classement' => $i,
					);
					$i++;
				}
			}
			$this->save_fiche($data, $user_id);
			return $this->fiche($user_id, $default);
		}

		return $fiche;
	}

	public function save_fiche_perso($data, $user_id) {
		$rows = array();
		foreach ($data['fiche'] as $element => $values) {
			if (isset($values['id']) and $values['id']) {
				$q = <<<SQL
UPDATE dt_fiches_produits
SET zone = '{$values['zone']}', classement = '{$values['classement']}'
WHERE id = {$values['id']}
SQL;
			}
			else {
				$q = <<<SQL
INSERT INTO dt_fiches_produits (id_users, element, zone, classement)
VALUES ($user_id, '$element', '{$values['zone']}', {$values['classement']})
SQL;
			}
			$this->sql->query($q);
		}
	}

	public function reset_fiche_perso($user_id) {
		$q = <<<SQL
DELETE FROM dt_fiches_produits WHERE id_users = $user_id
SQL;
		$this->sql->query($q);
	}

	public function fiche_perso_element($id) {
		$q =<<<SQL
SELECT html, xml FROM dt_fiches_produits WHERE id = $id
SQL;
		$res = $this->sql->query($q);

		return $this->sql->fetch($res);
	}

	public function save_fiche_perso_element($data, $id) {
		$element = $data['fiche_element'];
		$q = <<<SQL
UPDATE dt_fiches_produits
SET html = '{$element['html']}', xml = '{$element['xml']}'
WHERE id = $id
SQL;
		$this->sql->query($q);
	}

	public function fiche_perso_attributs($attribut, $langue) {
		$infos = array();
		$phrases = $this->phrase->get($this->phrases());
		foreach ($this->attributs_data() as $data) {
			$attribut->load($data['id_attributs']);

			$unites = $attribut->unites();
			$unite = null;
			if ($attribut->values['id_unites_mesure']) {
				$unite = $unites[$attribut->values['id_unites_mesure']];
			}

			$types = $attribut->types();
			$type = $types[$attribut->values['id_types_attributs']];
			

			if ($data['phrase_valeur']) {
				if (isset($phrases['valeurs_attributs'][$data['id_attributs']][$langue])) {
					$valeur = $phrases['valeurs_attributs'][$data['id_attributs']][$langue];
				}
				elseif (is_array($phrases['valeurs_attributs'][$data['id_attributs']])) {
					$valeur = array();
					foreach ($phrases['valeurs_attributs'][$data['id_attributs']] as $phrase_valeur_attribut) {
						$valeur[] = $phrase_valeur_attribut[$langue];
					}
				}
			}
			else {
				$valeur = $data['valeur_numerique'];
			}

			$infos[] = array(
				'nom' => $phrases['attributs'][$data['id_attributs']][$langue],
				'valeur' => $valeur,
				'type' => $type,
				'unite' => $unite,
				'fiche_technique' => $data['fiche_technique'],
				'pictos_vente' => $data['pictos_vente'],
				'top' => $data['top'],
				'comparatif' => $data['comparatif'],
				'filtre' => $data['filtre'],
			);
		}
		return $infos;
	}

	public function get_id_by_ref($ref) {
		$q = <<<SQL
SELECT id FROM dt_produits WHERE ref = '$ref'
SQL;
		$res = $this->sql->query($q);
		$row = $this->sql->fetch($res);

		return isset($row['id']) ? $row['id'] : false;
	}

	public function prix_mini($id_catalogues = 0) {
		$q = <<<SQL
SELECT MIN(p.montant_ht) AS prix_mini FROM dt_prix AS p
INNER JOIN dt_sku_variantes AS sv ON sv.id_sku = p.id_sku
WHERE sv.id_produits = {$this->id} AND id_catalogues = $id_catalogues
SQL;
		$res = $this->sql->query($q);
		$row = $this->sql->fetch($res);

		return $row['prix_mini'];
	}

	public function duplicate($data) {
		unset($data['produit']['id']);
		return parent::duplicate($data);
	}

	public function personnalisation() {
		$personnalisation = array();
		$q = <<<SQL
SELECT * FROM dt_personnalisations_produits WHERE id_produits = {$this->id}
SQL;
		$res = $this->sql->query($q);
		while ($row = $this->sql->fetch($res)) {
			$personnalisation[$row['type']] = array('has' => 1, 'libelle' => $row['libelle']);
		}

		return $personnalisation;
	}

	public function categories($id_catalogues) {
		$categories = array();
		$q = <<<SQL
SELECT cc.id, cc.nom, cc.titre_url, cc.id_parent, cc.classement FROM dt_catalogues_categories AS cc
INNER JOIN dt_catalogues_categories_produits AS ccp ON ccp.id_catalogues_categories = cc.id AND ccp.id_produits = {$this->id}
WHERE cc.id_catalogues = {$id_catalogues}
ORDER BY id_parent DESC
SQL;
		$res = $this->sql->query($q);
		if ($row = $this->sql->fetch($res)) {
			$categories[] = $row;
			$id_categories = $row['id_parent'];
			while ($id_categories) {
				$q = <<<SQL
SELECT cc.id, cc.nom, cc.titre_url, cc.id_parent, cc.classement FROM dt_catalogues_categories AS cc
WHERE cc.id = {$id_categories} 
ORDER BY cc.classement ASC
SQL;
				$res = $this->sql->query($q);
				$row = $this->sql->fetch($res);
				$categories[] = $row;
				$id_categories = $row['id_parent'];
			}
		}
		return $categories;
	}

	public function catalogues($id_produits = null) {
		if ($id_produits === null) {
			$id_produits = $this->id;
		}
		$q = <<<SQL
SELECT c.id FROM dt_catalogues AS c
INNER JOIN dt_catalogues_categories AS cc ON cc.id_catalogues = c.id
INNER JOIN dt_catalogues_categories_produits AS ccp ON ccp.id_catalogues_categories = cc.id
WHERE ccp.id_produits = $id_produits
SQL;
		$res = $this->sql->query($q);
		$catalogues = array();
		while ($row = $this->sql->fetch($res)) {
			$catalogues[] = $row['id'];
		}

		return $catalogues;
	}

	public function image_hd($image_id) {
		return "prod_{$this->id}_{$image_id}";
	}

	public function attributs_ref() {
		$q = <<<SQL
SELECT DISTINCT a.ref FROM dt_attributs AS a
INNER JOIN dt_sku_attributs AS sa ON sa.id_attributs = a.id
INNER JOIN dt_sku_variantes AS sv ON sv.id_sku = sa.id_sku AND sv.id_produits = {$this->id} 
WHERE ref != ''
SQL;
		$res = $this->sql->query($q);
		$refs = array();			
		while ($row = $this->sql->fetch($res)) {
			$refs[] = $row['ref'];
		}

		return $refs;
	}

	public function designations_auto($id_langues, $id = null) {
		if ($id === null) {
			$id = $this->id;
		}
		$q = <<<SQL
SELECT ph.phrase
FROM dt_produits AS p
INNER JOIN dt_phrases AS ph ON p.phrase_designation_auto = ph.id AND ph.id_langues = {$id_langues}
WHERE p.id = {$id}
SQL;
		$res = $this->sql->query($q);
		$row = $this->sql->fetch($res);
		$pattern = $row['phrase'];

		$q = <<<SQL
SELECT id, ref FROM dt_attributs
WHERE ref != ''
SQL;
		$res = $this->sql->query($q);
		$attr_refs = array();
		while ($row = $this->sql->fetch($res)) {
			$attr_refs[$row['id']] = $row['ref'];
		}
		
		$q = <<<SQL
SELECT  sa.id_sku, sa.id_attributs, sa.valeur_numerique, ph.phrase, ar.field_label, ar.table_name, ar.field_value
FROM dt_sku_attributs AS sa
LEFT OUTER JOIN dt_phrases AS ph ON ph.id = sa.phrase_valeur
LEFT OUTER JOIN dt_attributs_references AS ar ON ar.id_attributs = sa.id_attributs
INNER JOIN dt_sku_variantes AS sv ON sv.id_sku = sa.id_sku AND sv.id_produits = {$id}
SQL;
		$res = $this->sql->query($q);
		$valeurs_attributs = array();
		while ($row = $this->sql->fetch($res)) {
			if ($row['table_name'] and $row['field_label'] and $row['field_value']) {
				if (strpos($row['field_label'], "phrase_") === 0) {
					$q = <<<SQL
SELECT ph.phrase AS label FROM {$row['table_name']} AS t
LEFT OUTER JOIN dt_phrases AS ph ON t.{$row['field_label']} = ph.id AND ph.id_langues = {$id_langues}
WHERE t.{$row['field_value']} = {$row['valeur_numerique']}
SQL;
				}
				else {
					if ($row['field_value'][0] != ucfirst($row['field_value'][0])) {
						$row['field_value'] = "t.".$row['field_value'];
					}
					if ($row['field_label'][0] != ucfirst($row['field_label'][0])) {
						$row['field_label'] = "t.".$row['field_label'];
					}
					$q = <<<SQL
SELECT {$row['field_label']} AS label FROM {$row['table_name']} AS t
WHERE {$row['field_value']} = {$row['valeur_numerique']}
SQL;
				}
				$res2 = $this->sql->query($q);
				$row2 = $this->sql->fetch($res2);
				$valeurs_attributs[$row['id_sku']][$row['id_attributs']] = $row2['label'];
			}
			else {
				$valeurs_attributs[$row['id_sku']][$row['id_attributs']] = $row['phrase'] ? $row['phrase'] : $row['valeur_numerique'];
			}
		}
		
		$q = <<<SQL
SELECT s.id, ph.phrase
FROM dt_sku_variantes AS sv
INNER JOIN dt_sku AS s ON s.id = sv.id_sku
LEFT OUTER JOIN dt_phrases AS ph ON ph.id = s.phrase_ultralog AND id_langues = {$id_langues}
WHERE sv.id_produits = {$id}
SQL;
		$res = $this->sql->query($q);
		$designations = array();
		while ($row = $this->sql->fetch($res)) {
			$q = <<<SQL
SELECT sa.id_attributs
FROM dt_sku_attributs AS sa
WHERE sa.id_sku = {$row['id']}
SQL;
			$res2 = $this->sql->query($q);
			$auto = $pattern;
			while ($row2 = $this->sql->fetch($res2)) {
				if (isset($attr_refs[$row2['id_attributs']]) and isset($valeurs_attributs[$row['id']][$row2['id_attributs']])) {
					$auto = str_replace("%".$attr_refs[$row2['id_attributs']], $valeurs_attributs[$row['id']][$row2['id_attributs']], $auto);
				}
			}
			$designations[$row['id']] = array(
				'actuelle' => $row['phrase'],
				'auto' => $auto,
			);
		}

		return $designations;
	}
}
