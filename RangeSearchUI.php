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

  queue_js_string("
    var rangeSearchSelectFirst='$selectFirst';
    var rangeSearchSelectUnit='$selectUnit';
    var rangeSearchEnterNumber='$enterNumber';
  ");

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
  queue_js_string($fullMatchRegEx);

  // ------------------------------------------------------

  function editFieldHTML($textField, $view = false) {
    if (!$view)  {$view = get_view(); }
    return $view->formInput($textField,
                            null,
                            array("type" => "text",
                                  "class" => "rangeSearchTextField",
                                  "size" => 4,
                                  "maxlength" => 10,
                                )
                            );
  }

  // ------------------------------------------------------
?>

<div id="range-search-popup" style="overflow: auto; padding: 20px; border-radius: 6px; background: #fff" class="lity-hide">
  <h2><?php echo __("Range Entry"); ?></h2>
  <p>
  <?php
    $units = SELF::_fetchUnitArray();
    $saniUnits = array( -1 => __("Select Below") );
    foreach($units as $unit) {
      if ( substr_count($unit, "-") == 2 ) { $saniUnits[] = $unit;}
    }
    echo __("Units") . ": ". $view->formSelect('rangeSearchUnits', -1, array(), $saniUnits);
  ?>
  </p>
  <?php queue_js_string("var rangeSearchUnits=" . json_encode($saniUnits) . ";"); ?>
  <p>
    <?php
      $textFields = array("rangeSearch1", "rangeSearch2", "rangeSearch3");
      $htmlTextFields = array();
      foreach($textFields as $textField) {
        $htmlTextFields[] = editFieldHTML($textField, $view);
      }
      echo implode(" — ", $htmlTextFields);
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
      echo implode(" — ", $htmlTextFields);
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
