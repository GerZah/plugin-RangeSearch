<?php
  if (!defined("LITYLOADED")) {
    queue_css_file("lity.min");
    queue_js_file('lity.min');
    DEFINE("LITYLOADED", 1);
  }

  queue_js_file('rangesearch');
  queue_css_file('rangesearch');

  $selectFirst = __("Please select a target text area first.");
  $selectUnit = __("Please select a unit.");
  $enterNumber = __("Please enter a number.");
  # queue_js_string() of these variables further down below

  $view = get_view();

  $regEx = SELF::_constructRegEx();
  # foreach($regEx as $key => $val) { $$key = $val; }
  # echo "<!--" . print_r($regEx, true) . "-->\n";
  $combined = $regEx["combinedRegEx"];

  $fullMatchRegEx=<<<EOT
      function rangeSearchFullMatch(str) {
        return str.match(/^$combined$/i);
      }
EOT;
  # queue_js_string() of these variables further down below

  // ------------------------------------------------------

  function editFieldHTML($textField, $view = false) {
    if (!$view)  {$view = get_view(); }
    return $view->formInput($textField,
                            null,
                            array("type" => "text",
                                  "class" => "rangeSearchTextField",
                                  "size" => 4,
                                  "maxlength" => 4,
                                )
                            );
  }

  function editFieldTable($htmlTextFields) {
    $spanclass = "rangeTextArea";
    $result = "<span class='rangeTextArea'>";
    $result .= implode("</span> – <span class='rangeTextArea'>", $htmlTextFields);
    $result .="</span>";
    return $result;
  }

  // ------------------------------------------------------
?>

<div id="range-search-popup" style="overflow: auto; padding: 20px; border-radius: 6px; background: #fff" class="lity-hide">
  <h2><?php echo __("Range Entry"); ?></h2>
  <p>
  <?php
    $units = SELF::_fetchUnitArray();
    $saniUnits = array( ); # -1 => __("Select Below") ); # Both arrays ...
    $saniConversions = array(); # ... starting with index 0
    foreach($units as $unit) {
      $blankBracket = strpos($unit, " (");
      if ($blankBracket) {
        $conversion = substr($unit, $blankBracket+2);
        $conversion = substr($conversion, 0, strpos($conversion, ")") );
        preg_match_all("(\d+)", $conversion, $conversionDecimals);
        if ($conversionDecimals) {
          $conversionDecimals = $conversionDecimals[0];
          foreach(array_keys($conversionDecimals) as $idx) {
            $conversionDecimals[$idx] = ( $conversionDecimals[$idx]<1 ? 1 : $conversionDecimals[$idx] );
          }
          if ($conversionDecimals[0] != 1) { $conversionDecimals[0] = 1; }
        }
        # echo "<pre>#$conversion#</pre>"; #die();
        # echo "<pre>" . print_r($conversionDecimals,true) . "</pre>"; # die();
        $unit = substr($unit, 0, $blankBracket);
      }
      else { $conversionDecimals = array(); }
      if ( substr_count($unit, "-") == 2 ) {
        $saniUnits[] = $unit;
        $saniConversions[] = $conversionDecimals;
      }
    }
    // echo "<pre>" . print_r($saniUnits,true) . "</pre>";
    // echo "<pre>" . print_r($saniConversions,true) . "</pre>";
    $unitSelect = array( -1 => __("Select Below") ) + $saniUnits;
    echo __("Units") . ": ". $view->formSelect('rangeSearchUnits', -1, array(), $unitSelect);
  ?>
  </p>
  <?php
    $jsonSaniUnits = json_encode($saniUnits);
    $jsonSaniConversions = json_encode($saniConversions);
    queue_js_string("
      var rangeSearchSelectFirst='$selectFirst';
      var rangeSearchSelectUnit='$selectUnit';
      var rangeSearchEnterNumber='$enterNumber';
      $fullMatchRegEx
      var rangeSearchUnits=$jsonSaniUnits;
      var rangeSearchConversions=$jsonSaniConversions;
    ");
  ?>
  <p>
    <?php
      $textFields = array("rangeSearch1", "rangeSearch2", "rangeSearch3");
      $htmlTextFields = array();
      foreach($textFields as $textField) {
        $htmlTextFields[] = editFieldHTML($textField, $view);
      }
      echo editFieldTable($htmlTextFields);
    ?>
  </p>
  <p>
    <?php
      echo $view->formCheckbox("rangeSearchRange", false, array() );
      echo " <label for='rangeSearchRange'>".
            # __("Enter range (not just number).").
            __("… (Range)").
            "</label>";
    ?>
  </p>
  <p id="rangeSearchSecondTriple">
    <?php
      $textFields = array("rangeSearch4", "rangeSearch5", "rangeSearch6");
      $htmlTextFields = array();
      foreach($textFields as $textField) {
        $htmlTextFields[] = editFieldHTML($textField, $view);
      }
      echo editFieldTable($htmlTextFields);
    ?>
  </p>
  <p id="rangeSearchConversions">
    <?php
      echo __("Conversion Rates") . ":<br>";
      $textFields = array("rangeSearchConversion0", "rangeSearchConversion1", "rangeSearchConversion2");
      $htmlTextFields = array();
      foreach($textFields as $textField) {
        $htmlTextFields[] = editFieldHTML($textField, $view);
      }
      echo editFieldTable($htmlTextFields)."<br>";

      $btnFields = array("rangerSearchConvert1", "rangerSearchConvert2", "rangerSearchConvert3");
      $htmlBtnFields = array();
      foreach($btnFields as $btnField) {
        $htmlBtnFields[] = "<button class='rangerSearchConvert blue button' id=$btnField>".
                            __("Convert").
                            "</button>";
      }
      echo editFieldTable($htmlBtnFields);
    ?>
  </p>
  <p style="text-align: center;">
    <button id="rangeSearchCancel" class="green button" data-lity-close>
      <?php echo __("Cancel"); ?>
    </button>
    <button id="rangeSearchApply" class="green button"> <!--  data-lity-close -->
      <?php echo __("Apply"); ?>
    </button>
  </p>
</div>

<div id="range-search-controls" style="display:none;">
  <div class='rangeSearchButtons field'>
    <label><?php echo __("Range Entry"); ?>:</label>
    <button class='rangeSearchBtn'><?php echo __("Entry"); ?></button>
  </div>
</div>
