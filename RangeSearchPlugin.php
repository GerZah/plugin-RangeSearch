<?php

/**
* RangeSearch plugin.
*
* @package Omeka\Plugins\RangeSearch
*/
class RangeSearchPlugin extends Omeka_Plugin_AbstractPlugin {

	/**
	* @var array This plugin's hooks.
	*/
	protected $_hooks = array(
		'initialize', # tap into i18n
		'install', # create additional table and batch-preprocess existing items for ranges
		'uninstall', # delete table
		'upgrade', # upgrades from revision to revision
		'config_form', # prepare and display configuration form
		'config', # store config settings in the database
		'after_save_item', # preprocess saved item for ranges
		'after_delete_item', # delete deleted item's preprocessed ranges
		'admin_items_search', # add a time search field to the advanced search panel in admin
		'items_browse_sql', # filter for a range after search page submission.
	);

	protected $_options = array(
		'range_search_units' => '',
		'range_search_search_all_fields' => 1,
		'range_search_limit_fields' => "[]",
		'range_search_search_rel_comments' => 1,
	);

	/**
	 * Add the translations.
	 */
	public function hookInitialize() {
		add_translation_source(dirname(__FILE__) . '/languages');
	}

	/**
	 * Install the plugin.
	 */
	public function hookInstall() {
		# Create table
		$db = get_db();

		# Let's assume that a "numval" = number value is at the most "1234567890-1234-1234" == 20 chars long
		# And let's assume that any unit name is at the most 20 chars long ("Reichsmark" would be 10)

		$sql = "
		CREATE TABLE IF NOT EXISTS `$db->RangeSearchValues` (
				`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`item_id` int(10) unsigned NOT NULL REFERENCES `$db->Item`,
				`fromnum` varchar(20) NOT NULL,
				`tonum` varchar(20) NOT NULL,
				`unit` varchar(20) NOT NULL,
				PRIMARY KEY (`id`),
				INDEX (unit)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
		$db->query($sql);

		SELF::_installOptions();

		SELF::_batchProcessExistingItems();
	}

	/**
	 * Uninstall the plugin.
	 */
	public function hookUninstall() {
		$db = get_db();

		# Drop the table
		$sql = "DROP TABLE IF EXISTS `$db->RangeSearchValues`";
		$db->query($sql);

		SELF::_uninstallOptions();
	}

	public function hookUpgrade($args) {
		$oldVersion = $args['old_version'];
		$db = $this->_db;
		if ($oldVersion <= '0.2') {
			$sql="
						ALTER TABLE `$db->RangeSearchValues`
							MODIFY fromnum varchar(20),
							MODIFY tonum varchar(20)
						";
      $db->query($sql);
			SELF::_batchProcessExistingItems();
		}
	}

	/**
	 * Display the plugin configuration form.
	 */
	public static function hookConfigForm() {
		$rangeSearchUnits = implode("\n", SELF::_decodeUnitsFromOption(get_option('range_search_units'), false) );
		# echo "<pre>$rangeSearchUnits</pre>"; die();

		$searchAllFields = (int)(boolean) get_option('range_search_search_all_fields');

		$db = get_db();
		$sql = "select id, name from `$db->Elements` order by name asc";
		$elements = $db->fetchAll($sql);

		$searchElements = array();
		foreach($elements as $element) { $searchElements[$element["id"]] = $element["name"]; }

		$LimitFields = get_option('range_search_limit_fields');
		$LimitFields = ( $LimitFields ? json_decode($LimitFields) : array() );

		$withRelComments=SELF::_withRelComments();
		$searchRelComments = (int)(boolean) get_option('range_search_search_rel_comments');

		require dirname(__FILE__) . '/config_form.php';
	}

	/**
	 * Handle the plugin configuration form.
	 */
	public static function hookConfig() {
		// Unit configuration
		$oldRangeSearchUnits = get_option('range_search_units');
		$newRangeSearchUnits = SELF::_encodeUnitsFromTextArea($_POST['range_search_units']);
		set_option('range_search_units', $newRangeSearchUnits );

		// Search All Fields switch
		$prevSearchAllFields = (int)(boolean) get_option('range_search_search_all_fields');
		$newSearchAllFields = (int)(boolean) $_POST['range_search_search_all_fields'];
		set_option('range_search_search_all_fields', $newSearchAllFields);

		// Limit Fields list (in case "Search All Fields" is false
		$oldLimitFields = get_option('range_search_limit_fields');
		$newLimitFields = array();
		$postIds=false;
		if (isset($_POST["range_search_limit_fields"])) { $postIds = $_POST["range_search_limit_fields"]; }
		if (is_array($postIds)) {
			foreach($postIds as $postId) {
				$postId = intval($postId);
				if ($postId) { $newLimitFields[] = $postId; }
			}
		}
		sort($newLimitFields);
		$newLimitFields = json_encode($newLimitFields);
		set_option('range_search_limit_fields', $newLimitFields);

		// Search Relationship Comments switch
		$prevSearchRelComments = (int)(boolean) get_option('range_search_search_rel_comments');
		$newSearchRelComments = (int)(boolean) $_POST['range_search_search_rel_comments'];
		set_option('range_search_search_rel_comments', $newSearchRelComments);

		$reprocess = false;
		$reprocess = ( ($reprocess) or ($oldRangeSearchUnits != $newRangeSearchUnits) ); 
		$reprocess = ( ($reprocess) or ($prevSearchAllFields != $newSearchAllFields) ); 
		$reprocess = ( ($reprocess) or ( (!$newSearchAllFields) && ($oldLimitFields != $newLimitFields) ) ); 
		$reprocess = ( ($reprocess) or ( (!$newSearchAllFields) && ($prevSearchRelComments != $newSearchRelComments) ) ); 

		if ($reprocess) { SELF::_batchProcessExistingItems(); }
		# echo "<pre>"; print_r($_POST); echo "</pre>"; die();
	}

	/**
	 * Decode JSON array from DB option -- imploded with "\n" it will be displayable in textarea on config page
	 */
	private function _decodeUnitsFromOption($option, $pregQuote = false) {
		$lines = ($option ? json_decode($option) : array() );
		if ($pregQuote) {
			foreach(array_keys($lines) as $idx) { $lines[$idx] = preg_quote($lines[$idx]); }
		}
		return $lines;
	}

	/**
	 * Encode content of textarea on config page to be stored as a JSON array in DB option
	 */                                
	private function _encodeUnitsFromTextArea($textArea) {
		$textArea = str_replace(chr(10), chr(13), $textArea);
		$textArea = str_replace(chr(13).chr(13), chr(13), $textArea);
		$textArea = stripslashes($textArea);

		$lines = explode(chr(13), $textArea);
		$nonEmptyLines = array();
		foreach($lines as $line) {
			$line = trim($line);
			$line = substr($line, 0, 20);
			if ($line) { $nonEmptyLines[]=$line; }
		}

		return json_encode($nonEmptyLines);
	}

	/**
	 * Preprocess ALL existing items which could be rather EVIL in huge installations
	 */
	private function _batchProcessExistingItems() {
		$db = get_db();
		$sql= "select id from `$db->Items`";
		$items = $db->fetchAll($sql);
		foreach($items as $item) { SELF::_preProcessItem($item["id"]); }
	}

	/**
	 * Preprocess numbers after saving an item add/edit form.
	 *
	 * @param array $args
	 */
	public function hookAfterSaveItem($args) {
			if ( (!$args['post']) and (!$args['insert']) ) {
					return;
			}

			$item_id = intval($args["record"]["id"]);
			if ($item_id) { SELF::_preProcessItem($item_id); }

			# die("After Save Item");

	} # hookAfterSaveItem()

	/**
	 * Delete pre-processed numbers after an item has been deleted
	 *
	 * @param array $args
	 */
	public function hookAfterDeleteItem($args) {
			$db = get_db();

			$item_id = intval($args["record"]["id"]);

			if ($item_id) {
				$sql = "delete from `$db->RangeSearchValues` where item_id=$item_id";
				$db->query($sql);
			}

			# echo "<pre>After Delete Item - ID: $item_id\nSQL: $sql\n"; print_r($args); die("</pre>");
	} # hookAfterDeleteItem()

	/**
	 * Determine if Item Relations is installed, and if it's patched to feature relationship comments
	 */
	private function _withRelComments() {
		$db = get_db();

		$withRelComments=false;
		$sql = "show columns from `$db->ItemRelationsRelations` where field='relation_comment'";
		try { $withRelComments = ($db->fetchOne($sql) !== false); }
		catch (Exception $e) { $withRelComments=false; }

		return $withRelComments;
	}

	/**
	 * Get an item's relationship comment text
	 */
	private function _relationshipCommentText($item_id) {
		$db = get_db();
		$text = "";

		# Check if we could add relation comments in case Item Relations is installed and has been patched
		# to feature relation comments.
		$withRelComments=SELF::_withRelComments();

		if ($withRelComments) {
			$sql = "select relation_comment from `$db->ItemRelationsRelations` where subject_item_id=$item_id";
			$comments = $db->fetchAll($sql);
			if ($comments) {
				foreach($comments as $comment) { $text .= " ".$comment["relation_comment"]; }
			}
		}

		return $text;
	}

	/**
	 * Pre-process one item's textual data and store timespans in RangeSearchValues table
	 */
	private function _preProcessItem($item_id) {
		$db = get_db();

		if ($item_id) {
			$sql = "delete from `$db->RangeSearchValues` where item_id=$item_id";
			$db->query($sql);

			$text = false;

			$searchAllFields = (int)(boolean) get_option('range_search_search_all_fields');

			if ($searchAllFields) {
				$text = $db->fetchOne("select text from `$db->SearchTexts` where record_type='Item' and record_id=$item_id");
				$text = ( $text ? $text : "" );

				$text .= SELF::_relationshipCommentText($item_id);
				$text = ( $text ? $text : false );
			} # if ($searchAllFields)

			else { # !$searchAllFields

				$limitFields = get_option('range_search_limit_fields');
				$limitFields = ( $limitFields ? json_decode($limitFields) : array() );

				$elementIds=array();
				if (is_array($limitFields)) {
					foreach($limitFields as $limitField) {
						$limitField = intval($limitField);
						if ($limitField) { $elementIds[] = $limitField; }
					}
					sort($elementIds);
				}

				if ($elementIds) {
					$elementIds = "(" . implode(",", $elementIds) . ")";

					$elementTexts = $db -> fetchAll("select text from `$db->ElementTexts`".
																					" where record_id=$item_id".
																					" and element_id in ($elementIds)");
					if ($elementTexts) {
						$text = "";
						foreach($elementTexts as $elementText) { $text .= " " . $elementText["text"]; }
					} # if ($elementTexts)
				} # if ($elementIds)

				$searchRelComments = (int)(boolean) get_option('range_search_search_rel_comments');

				if ($searchRelComments) {
					$text = ( $text ? $text : "" );
					$text .= SELF::_relationshipCommentText($item_id);
					$text = ( $text ? $text : false );
				}

			}  # !$searchAllFields

			if ($text !== false) {

				$cookedRanges = SELF::_processRangeText($text);
				# echo "<pre>"; print_r($cookedRanges); die("</pre>");

				if ($cookedRanges) {

					$values = array();
					foreach($cookedRanges as $cookedRange) {
						SELF::_swapIfNecessary($cookedRange[0], $cookedRange[1]);
						$values[]='('.$item_id.',"'.$cookedRange[0].'","'.$cookedRange[1].'","'.$cookedRange[2].'")';
					}
					$values = implode(", ", $values);

					$sql = "insert into `$db->RangeSearchValues` (item_id, fromnum, tonum, unit) values $values";
					$db->query($sql);
					# die($sql);

				} # if ($cookedDates)
			} # if ($text)
		} # if ($item_id)
	} #  _preProcessItem()

	/**
	 * Display the time search form on the admin advanced search page
	 */
	public function hookAdminItemsSearch() {
		echo common('range-search-advanced-search', null);
	}

	/**
	 * Filter for a number after search page submission.
	 *
	 * @param array $args
	 */
	public function hookItemsBrowseSql($args) {
		$select = $args['select'];
		$params = $args['params'];

		$regEx = SELF::_constructRegEx();
		foreach($regEx as $key => $val) { $$key = $val; }
		if (	(isset($params['range_search_term'])) and
					(preg_match( "($numberNumberRange)", $params['range_search_term'])) ) {

			$singleCount = preg_match_all ( "($number)", $params['range_search_term'], $singleSplit );
			# echo "<pre>singleCount: "; print_r($singleSplit); echo "</pre>";
			$numberRange = array();
			$numberRange[] = $singleSplit[0][0];
			$numberRange[] = $singleSplit[0][ ($singleCount==2 ? 1 : 0 ) ];
			$numberRange = SELF::_expandNumberRange($numberRange);

			$searchFromNum = $numberRange[0];
			$searchToNum = $numberRange[1];

			$db = get_db();
			$select
					->join(
							array('range_search_values' => $db->RangeSearchValues),
							"range_search_values.item_id = items.id",
							array()
					)
					->where("'$searchFromNum'<=range_search_values.tonum and '$searchToNum'>=range_search_values.fromnum");

			if (isset($params['range_search_unit'])) {
				$rangeSearchUnit = intval($params['range_search_unit']);

				$RangeSearchUnits = get_option('range_search_units');
				if ($RangeSearchUnits) {
					$RangeSearchUnits=json_decode($RangeSearchUnits);
					if (isset($RangeSearchUnits[$rangeSearchUnit])) {
						$filterUnit = $RangeSearchUnits[$rangeSearchUnit];
						$select->where("range_search_values.unit='$filterUnit'");
					}
				}
			}

			# die("<pre>$searchFromNum / $searchToNum --- $select</pre>");

		}
	}

	# ------------------------------------------------------------------------------------------------------

	/**
	 * Cross swap  in case the first element is "bigger" (i.e. sorts behind) the second
	 */
	private function _swapIfNecessary(&$x,&$y) {
		# as in http://stackoverflow.com/a/26549027
		if ($x > $y) {
			$tmp=$x;
			$x=$y;
			$y=$tmp;
		}
	}

	# ------------------------------------------------------------------------------------------------------

	/**
	 * Main regex processing to extract numbers and ranges, to be able to expand them later
	 */
	private function _processRangeText($text) {
		$regEx = SELF::_constructRegEx();
		foreach($regEx as $key => $val) { $$key = $val; }

		$allCount = preg_match_all( "($numberRangeUnits)i", $text, $allMatches);
		# echo "<pre>Count: $allCount\n"; print_r($allMatches); die("</pre>");

		$cookedRanges = array();
		foreach($allMatches[0] as $singleMatch) {
			$singleCount = preg_match_all ( "($number)", $singleMatch, $singleSplit );
			$numberRange = array();
			$numberRange[] = $singleSplit[0][0];
			$numberRange[] = $singleSplit[0][ ($singleCount==2 ? 1 : 0 ) ];
			$numberRange = SELF::_expandNumberRange($numberRange);
			$unit = preg_match( "($units)i", $singleMatch, $unitMatch );
			# echo "<pre>"; print_r($unitMatch); echo "</pre>"; die();
			$numberRange[] = $unitMatch[0];
			$cookedRanges[] = $numberRange;
		}
		# echo "<pre>"; print_r($cookedRanges); die("</pre>");

		return $cookedRanges;
	}

	# ------------------------------------------------------------------------------------------------------

	/**
	 * Create the necessary regEx expressions to deal with xxxx / xxxx-yy / xxxx-yy-zz numbers
	 */
	private function _constructRegEx() {

		# Construct RegEx
		$mainNumber = "\d{1,10}"; # 1 to 10 digits for main number
		$middleNumber = $lastNumber = "\d{1,4}"; # 1 or four digits for middle and last number
		$middleLastNumber = "$middleNumber(?:-$lastNumber)?"; # middle number - possibly with last number
		$number = "$mainNumber(?:-$middleLastNumber)?\b"; # main number - possible with middle and possible with last number
		$separator = "\s*-\s*"; # separator hypen, with or without blanks
		$numberNumberRange = "$number(?:$separator$number)?"; # one number or two numbers with separator in-between

		$unitsArray = SELF::_decodeUnitsFromOption(get_option('range_search_units'), true);
		$units = "\b(?:" . implode("|", $unitsArray) . ")\b";
		$numberRangeUnits = "$numberNumberRange\s$units";

		$result = array(
								"mainNumber" => $mainNumber,
								"middleNumber" => $middleNumber,
								"lastNumber" => $lastNumber,
								"middleLastNumber" => $middleLastNumber,
								"number" => $number,
								"separator" => $separator,
								"numberNumberRange" => $numberNumberRange,
								"units" => $units,
								"numberRangeUnits" => $numberRangeUnits,
							);
		# echo "<pre>"; print_r($result); echo "</pre>\n"; die();

		return $result;

	}

	# ------------------------------------------------------------------------------------------------------

	/**
	 * Transform a (valid) number xxxx-pp-qq into a numer ranger-- down to xxxx-00-00 to yyyy-99-99
	 *
	 * @param string $range as in single number or range
	 * @result array [0] => left edge, [1] => right edge
	 */
	private function _expandNumberRange($range) {
		$result = $range;
	
		if (!is_array($result)) { $result = array($result, $result); }
	
		$result[0] = SELF::_updateRange($result[0], -1); # -1 == left edge, xxxxxxxxxx-00-00
		$result[1] = SELF::_updateRange($result[1], +1); # +1 == right edge, xxxxxxxxxx-99-99
		
		return $result;
	}
	
	# ------------------------------------------------------------------------------------------------------

	/**
	 * Take a valid xxxx / xxxx-y / xxxx-yy / xxxx-y-z / xxxx-yy-z / xxxx-yy-zz
	 * and transform it towards a left edge of possibly xxxx-00-00 or xxxx-99-99
	 * or at least add leading zeros, as in 000000xxxx-0y-0z
	 *
	 * @param string $range to be updated
	 * @param int edge -- -1 -> left edge (-00-00) / +1 -> right edge (-99-99)
	 * @result string $range -- transformed towards edge and with leading zeros
	 */

	private function _updateRange($range, $edge) {
		$result=$range;
	
		$regEx = SELF::_constructRegEx();
		foreach($regEx as $key => $val) { $$key = $val; }
	
		$mainNumberOnly = "^$mainNumber$";
		$mainMiddleNumber = "^$mainNumber-$middleNumber$";
		$mainMiddleLastNumber = "^$mainNumber-$middleNumber-$lastNumber$";
	
		if ( preg_match( "($mainNumberOnly)", $result ) ) { $result = $result."-".( $edge<0 ? "0" : "9999" ); }
		if ( preg_match( "($mainMiddleNumber)", $result ) ) { $result = $result."-".( $edge<0 ? "0" : "9999" ); }
	
		if ( preg_match( "($mainMiddleLastNumber)", $result ) ) {
			$OneDigit = "\b(\d)\b";
			$TwoDigit = "\b(\d\d)\b";
			$ThreeDigit = "\b(\d\d\d)\b";
			$result = preg_replace("($OneDigit)", '000${0}', $result);
			$result = preg_replace("($TwoDigit)", '00${0}', $result);
			$result = preg_replace("($ThreeDigit)", '0${0}', $result);
		}
	
		while (strlen($result)<20) { $result="0$result"; }
	
		return $result;
	}

	# ------------------------------------------------------------------------------------------------------

} # class
