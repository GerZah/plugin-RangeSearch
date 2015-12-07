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
		'public_items_search', # add a time search field to the advanced search panel in public
		'admin_items_show_sidebar', # Debug output of stored numbers/ranges in item's sidebar (if activated)
		'items_browse_sql', # filter for a range after search page submission.
		'admin_head', # add lightbox overlay to enter / edit / convert numbers & ranges
	);

	protected $_options = array(
		'range_search_units' => '',
		'range_search_search_all_fields' => 1,
		'range_search_limit_fields' => "[]",
		'range_search_search_rel_comments' => 1,
		'range_search_debug_output' => 0,
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
				`unit` varchar(200) NOT NULL,
				PRIMARY KEY (`id`),
				INDEX (unit)
		) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
		$db->query($sql);

		SELF::_installOptions();

		# SELF::_batchProcessExistingItems(); # Don't ... Do it only if configured.
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
							MODIFY unit varchar(20)
						";
      $db->query($sql);
			SELF::_batchProcessExistingItems();
		}
		if ($oldVersion <= '0.3') {
			$sql="
						ALTER TABLE `$db->RangeSearchValues`
							MODIFY unit varchar(200)
						";
      $db->query($sql);
			SELF::_batchProcessExistingItems();
		}
	}

	/**
	 * Display the plugin configuration form.
	 */
	public static function hookConfigForm() {
		$rangeSearchUnits = SELF::_prepareUnitsFromJsonForEdit();
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

		$debugOutput = (int)(boolean) get_option('range_search_debug_output'); # comment line to remove debug output panel

		require dirname(__FILE__) . '/config_form.php';

		# SELF::_constructRegEx(); // +#+#+# DEBUG
	}

	/**
	 * Handle the plugin configuration form.
	 */
	public static function hookConfig() {
		// Unit configuration
		$rangeSearchUnits = SELF::_encodeUnitsFromTextArea($_POST['range_search_units']);
		set_option('range_search_units', $rangeSearchUnits );

		// Search All Fields switch
		$searchAllFields = (int)(boolean) $_POST['range_search_search_all_fields'];
		set_option('range_search_search_all_fields', $searchAllFields);

		// Limit Fields list (in case "Search All Fields" is false
		$limitFields = array();
		$postIds=false;
		if (isset($_POST["range_search_limit_fields"])) { $postIds = $_POST["range_search_limit_fields"]; }
		if (is_array($postIds)) {
			foreach($postIds as $postId) {
				$postId = intval($postId);
				if ($postId) { $limitFields[] = $postId; }
			}
		}
		sort($limitFields);
		$limitFields = json_encode($limitFields);
		set_option('range_search_limit_fields', $limitFields);

		// Search Relationship Comments switch
		$searchRelComments = (int)(boolean) $_POST['range_search_search_rel_comments'];
		set_option('range_search_search_rel_comments', $searchRelComments);

		// Debug Output switch -- if present
		$debugOutput = 0; // Sanity
		if (isset($_POST['range_search_debug_output'])) {
			$debugOutput = (int)(boolean) $_POST['range_search_debug_output'];
		}
		set_option('range_search_debug_output', $debugOutput);

		$reprocess = (int)(boolean) $_POST['range_search_trigger_reindex'];
		if ($reprocess) { SELF::_batchProcessExistingItems(); }
		# echo "<pre>"; print_r($_POST); echo "</pre>"; die();
	}

	/**
	 * Fetch JSON array from DB option as a PHP array
	 */
	private function _fetchUnitArray() {
		$json = get_option('range_search_units');
		$json = ( $json ? $json : "[]" );
		return json_decode($json);
	}

	/**
	 * Transform unit array to be edited in textarea on config page
	 */
	private function _prepareUnitsFromJsonForEdit() {
		$arr = SELF::_fetchUnitArray();
		return ( $arr ? implode("\n", $arr) : "" );
	}

	/**
	 * Transform plausible entries from units array for use in RegEx
	 */
	private function _decodeUnitsForRegEx() {
		$result = array();

		$arr = SELF::_fetchUnitArray();
		if ($arr) {
			foreach($arr as $unit) {
				if ( substr_count($unit, "-") == 2 ) { // e.h. "RT-Gr-d"
					$units = explode("-", $unit);
					foreach(array_keys($units) as $idx) { $units[$idx] = preg_quote(trim($units[$idx])); }
					if ( $units[0] && $units[1] && $units[2] ) {
						$result[$unit] = $units;
					}
				}
			}
		}

		return $result;
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
																					" and element_id in $elementIds");
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
	protected function _itemsSearch() {
		$validUnits = SELF::_decodeUnitsForRegEx();
		if ($validUnits) {
			$selectUnits = /* array(-1 => "-- ".__("All")." --" ) + */ array_keys($validUnits);
			# echo "<pre>" . print_r(array_keys($selectUnits),true) . "</pre>";
			echo common('range-search-advanced-search', array("selectUnits" => $selectUnits ));
		}
	}

	/**
	 * Display the time search form on the admin advanced search page in admin
	 */
	public function hookAdminItemsSearch() { SELF::_itemsSearch();  }

	/**
	 * Display the time search form on the admin advanced search page in admin
	 */
	public function hookPublicItemsSearch() { SELF::_itemsSearch();  }

  /**
  * Debug output of stored numbers/ranges in item's sidebar (if activated)
  *
  * @param Item $item
  */
  public function hookAdminItemsShowSidebar($args) {
		$debugOutput = (int)(boolean) get_option('range_search_debug_output');
		if ($debugOutput) {
			$itemID = $args['item']['id'];
			if ($itemID) {
				echo "<div class='panel'><h4>".__("Range Search Debug Output")."</h4>\n";
				$db = get_db();
				$sql = "select * from `$db->RangeSearchValues` where item_id=$itemID";
				$ranges = $db->fetchAll($sql);
				if ($ranges) {
					echo "<ul>\n";
					foreach($ranges as $range) {
						$rangeUnit = $range["unit"];
						preg_match_all('!\d+!', $range["fromnum"], $numFrom);
						preg_match_all('!\d+!', $range["tonum"], $numTo);
						echo "<li>". intval($numFrom[0][0])."-".intval($numFrom[0][1])."-".intval($numFrom[0][2]).
									" … ". intval($numTo[0][0])."-".intval($numTo[0][1])."-".intval($numTo[0][2]).
									" " . $rangeUnit.
									"</li>\n";
					}
					echo "</ul>\n";
				}
				echo "</div>\n";
			}
		}
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
					(preg_match( "($unitlessNumberNumberRange)", $params['range_search_term'])) ) {

			$singleCount = preg_match_all ( "($unitlessNumber)", $params['range_search_term'], $singleSplit );
			# echo "<pre>singleCount: " . print_r($singleSplit,true) . "</pre>"; die();
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

			if ( (isset($params['range_search_unit'])) and (is_array($params['range_search_unit'])) ) {
				$rangeSearchFormUnits = array();
				foreach($params['range_search_unit'] as $unit) { $rangeSearchFormUnits[] = intval($unit); }
				if ($rangeSearchFormUnits) {
					$validUnits = SELF::_decodeUnitsForRegEx();
					if ($validUnits) {
						$RangeSearchUnits = array_keys($validUnits);
						$dbUnits = array();
						foreach($rangeSearchFormUnits as $unit) {
							if (isset($RangeSearchUnits[$unit])) { $dbUnits[] = addslashes($RangeSearchUnits[$unit]); }
						}
						if ($dbUnits) {
							$dbUnits = "'" . implode("','", $dbUnits) . "'";
							$select->where("range_search_values.unit in ($dbUnits)");
						}
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
		# echo "<pre>$text\n" . print_r($regEx,true) . "</pre>";
		foreach($regEx as $key => $val) { $$key = $val; }

		$allCount = preg_match_all( "($combinedRegEx)i", $text, $allMatches);
		# echo "<pre>Count: $allCount\n" . print_r($allMatches,true) . "</pre>";

		$cookedRanges = array();
		foreach($allMatches[0] as $singleMatch) {

			$usedRegExId = false;
			foreach($singleRegEx as $id => $testString) {
				$count = preg_match("($testString)i", $singleMatch);
				if ($count) { $usedRegExId = $id; break; }
			}

			if ($usedRegExId) {
				$usedUnit = substr($usedRegExId, 0, strrpos($usedRegExId, "/") );
				$number = $unitRegEx[$usedUnit];
				#echo "<pre>'$singleMatch' = $usedRegExId / $usedUnit ($number)</pre>";

				$singleCount = preg_match_all ( "($number)i", $singleMatch, $singleSplit );
				# echo "<pre>singleCount: $singleCount\n" . print_r($singleSplit,true) . "</pre>";
				$numberRange = array();
				$numberRange[] = $singleSplit[0][0];
				$numberRange[] = $singleSplit[0][ ($singleCount==2 ? 1 : 0 ) ];
				$numberRange = SELF::_expandNumberRange($numberRange);
				# echo "<pre>" . print_r($numberRange,true) . "</pre>";
				$numberRange[] = $usedUnit;
				$cookedRanges[] = $numberRange;
			}
		}
		# echo "<pre>" . print_r($cookedRanges,true) . "</pre>"; die();
		# die();

		return $cookedRanges;
	}

	# ------------------------------------------------------------------------------------------------------

	/**
	 * Create the necessary regEx expressions to deal with xxxx / xxxx-yy / xxxx-yy-zz numbers
	 */
	private function _constructRegEx() {
		# Construct RegEx
		$DBunits = SELF::_decodeUnitsForRegEx();

		$blank = "\s*"; # just whitespace
		$justSeparator = "-"; # just hyphen
		$separator = $blank.$justSeparator.$blank; # separator hypen, with or without blanks
		$mainNumber = "\d{1,10}"; # 1 to 10 digits for main number
		$middleNumber = $lastNumber = "\d{1,4}"; # 1 or four digits for middle and last number
		$middleLastNumber = "$middleNumber(?:$justSeparator$lastNumber)?"; # middle number - possibly with last number
		$unitlessNumber = "$mainNumber(?:$justSeparator$middleLastNumber)?\b"; # main number - possible with middle and possible with last number
		$unitlessNumberNumberRange = "$unitlessNumber(?:$justSeparator$unitlessNumber)?"; # one number or two numbers with separator in-between

		$singleRegEx = array();
		$combinedRegEx = false;
		$unitRegEx = array();

		if ($DBunits) {

			$longMiddleShort = array();
			$longMiddleShortRange = array();

			foreach($DBunits as $unit) {
				$unitId = implode("-", $unit);

				$mainUnit = "$mainNumber".$unit[0];
				$middleUnit = "$middleNumber".$unit[1];
				$lastUnit = "$lastNumber".$unit[2];

				$optionalLastUnit = "(?:$justSeparator$lastUnit)?";
				$optionalMiddleUnit = "(?:$justSeparator$middleUnit$optionalLastUnit)?";
				$unitRegEx[$unitId] = "$mainUnit$optionalMiddleUnit";

				$short = "$mainUnit";
				$shortMiddle = "$short$justSeparator$middleUnit";
				$shortMiddleLong = "$shortMiddle$justSeparator$lastUnit";

				$thisLongMiddleShort = array( $shortMiddleLong, $shortMiddle, $short);

				$thisLongMiddleShortRange = array();
				foreach($thisLongMiddleShort as $front) { // ABC|AB|A cross-product ...
					foreach($thisLongMiddleShort as $back) { // ... with ABC|AB|A
						$thisLongMiddleShortRange[] = "$front$separator$back";
					}
				}
				foreach($thisLongMiddleShort as $single) { $thisLongMiddleShortRange[] = "$single"; } // ABC|AB|A single

				$longMiddleShort[$unitId] = $thisLongMiddleShort;
				$longMiddleShortRange[$unitId] = $thisLongMiddleShortRange;
			}

			$maxidx = 12; // 9 == ABC|AB|A cross product ABC|AB|A + 3 == ABC|AB|A single
			for($i=0; $i<$maxidx; $i++) {
				foreach(array_keys($longMiddleShortRange) as $unitID) {
					$singleRegEx["$unitID/$i"] = $longMiddleShortRange[$unitID][$i];
				}
			}
			$combinedRegEx = "(?:" . implode("|",  $singleRegEx) . ")";

		}

		#echo "<pre>longMiddleShort\n" .  print_r($longMiddleShort,true). "</pre>";
		#echo "<pre>longMiddleShortRange\n" .  print_r($longMiddleShortRange,true). "</pre>";
		#echo "<pre>combinedRegEx\n$combinedRegEx</pre>";

		$result = array(
								"justSeparator" => $justSeparator,
								"separator" => $separator,
								"blank" => $blank,
								"mainNumber" => $mainNumber,
								"middleNumber" => $middleNumber,
								"lastNumber" => $lastNumber,
								"middleLastNumber" => $middleLastNumber,
								"unitlessNumber" => $unitlessNumber,
								"unitlessNumberNumberRange" => $unitlessNumberNumberRange,
								# "longMiddleShort" => $longMiddleShort,
								# "longMiddleShortRange" => $longMiddleShortRange,
								"unitRegEx" => $unitRegEx,
								"singleRegEx" => $singleRegEx,
								"combinedRegEx" => $combinedRegEx,
							);
		# echo "<pre>" .  print_r($result,true). "</pre>";
		# die();

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

		#echo "<pre>_expandNumberRange:\n" . print_r($range,true) . "</pre>";

		if (!is_array($result)) { $result = array($result, $result); }

		$result[0] = SELF::_updateRange($result[0], -1); # -1 == left edge, xxxxxxxxxx-00-00
		$result[1] = SELF::_updateRange($result[1], +1); # +1 == right edge, xxxxxxxxxx-99-99

		return $result;
	}

	# ------------------------------------------------------------------------------------------------------

	/**
	 * Take a valid xxxx / xxxx-y / xxxx-y-z / xxxx-yy-z / xxxx-yy-zz
	 * and transform it towards a left edge of possibly xxxx-0000-0000 or xxxx-9999-9999
	 * or at least add leading zeros, as in 000000xxxx-0y-0z
	 *
	 * @param string $range to be updated
	 * @param int edge -- -1 -> left edge (-0000-0000) / +1 -> right edge (-9999-9999)
	 * @result string $range -- transformed towards edge and with leading zeros
	 */

	private function _updateRange($range, $edge) {
		$numNumbers = preg_match_all('!\d+!', $range, $numbers); // extract number components -- up to three

		if ($numNumbers) { $components = $numbers[0]; } else { $components = array(0); }

		$compLengths = array(10,4,4);
		for($i=0; ($i<3); $i++) {
			if (isset($components[$i])) { $components[$i] = $components[$i]; } else { $components[$i] = ( $edge<0 ? 0 : 9999 ); }
			$components[$i] = substr( "0000000000".$components[$i], -$compLengths[$i] );
		}

		#echo "<pre>components: " . print_r($components,true) . "</pre>";
		return implode("-", $components);
	}

	# ------------------------------------------------------------------------------------------------------

	/**
	 * Sdd calendar sheet / date picker / Greg/Jul conversion functionality
	 */
	public function hookAdminHead() {
		$request = Zend_Controller_Front::getInstance()->getRequest();

		$module = $request->getModuleName();
		if (is_null($module)) { $module = 'default'; }
		$controller = $request->getControllerName();
		$action = $request->getActionName();

		if ($module === 'default'
				&& $controller === 'items'
				&& in_array($action, array('add', 'edit'))) {

			require dirname(__FILE__) . '/RangeSearchUI.php';

		}
	}


} # class
