jQuery(document).bind("omeka:elementformload", function() {
  var $ = jQuery; // use noConflict version of jQuery as the short $ within this block

  var lightbox = lity(); // https://www.npmjs.com/package/lity

  var textFields = ["#rangeSearch1", "#rangeSearch2", "#rangeSearch3",
                    "#rangeSearch4", "#rangeSearch5", "#rangeSearch6"];

  // --------------------------------------------------------

  $("#rangeSearchWrapper").remove();
  $("#save")
    .append("<span id='rangeSearchWrapper'>"+
              $("#range-search-controls").html()+
              "</span>");

  // --------------------------------------------------------

  var currentTextArea = false;
  $("textarea").focus(function(e) { currentTextArea = $(this); })

  // --------------------------------------------------------

  function showHideSecondTriple(range) {
    if (range) { $("#rangeSearchSecondTriple").slideDown("fast"); }
    else { $("#rangeSearchSecondTriple").slideUp("fast"); }
  }

  // -------------------

  function presetFormValues(selText) {
    var usableSelection = rangeSearchFullMatch(selText); // in RangeSearchUI.php

    if (usableSelection) {
      var decimals = selText.match(/(\d+)/g); // decimals
      var cnt = decimals.length;
      var range = (cnt <= 3 ? false : true);
      $("#rangeSearchRange").prop("checked", range);
      showHideSecondTriple(range);

      for(var i = 0; i < cnt; i++) { $(textFields[i]).val(decimals[i]); }
      for(var i = cnt; i < 6; i++) { $(textFields[i]).val("0"); }

      var units = selText.match(/((?![-| ])\D)+/g); // no dashes, no blanks
      units = units.slice(0,3).join("-").toLowerCase();
      var unitsLen = units.length;
      for(var idx in rangeSearchUnits) {
        if (units == rangeSearchUnits[idx].toLowerCase().substr(0,unitsLen)) {
          $("#rangeSearchUnits").val(idx).change();
          return;
        }
      }
    }
    else {
      for(var i = 0; i < 6; i++) { $(textFields[i]).val(""); }
      $("#rangeSearchRange").prop("checked", false);
      $("#rangeSearchUnits").val(-1).change();
      showHideSecondTriple(false);
    }

  }

  // -------------------

  $(".rangeSearchButtons button").click(function(e) {
    e.preventDefault();

    if (!currentTextArea) { alert(rangeSearchSelectFirst); return; }

    var sel = currentTextArea.getSelection();
    var selText = "";
    if (sel.start != sel.end) { selText = sel.text; }

    lightbox("#range-search-popup");
    presetFormValues(selText);
  });

  // --------------------------------------------------------

  $("#rangeSearchUnits").change(function(e) {
    var curSelect = $("#rangeSearchUnits").val();

    var conversions = new Array;

    if (typeof rangeSearchConversions[curSelect] != 'undefined') {
      conversions = rangeSearchConversions[curSelect];
    }

    var conversionsLength = conversions.length;

    if (conversionsLength!=3) {
      $("#rangeSearchConversions").slideUp("fast");
    }
    else {
      $("#rangeSearchConversions").slideDown("fast");
      for(var idx=0 ; (idx<=2) ; idx++) { $("#rangeSearchConversion"+idx).val(conversions[idx]); }
      $("#rangeSearchConversion0").prop("readonly", true);
    }

  });

  // --------------------------------------------------------

  $(".rangerSearchConvert").click(function(e) {
    e.preventDefault();
    var btnId = e.target.id;
    var btnNum = parseInt( btnId.match(/(\d+)/g) );
    if (btnNum) {
      var nums = new Array;
      for(var idx=1; (idx<=6); idx++) {
        var num = parseInt( $("#rangeSearch"+idx).val() );
        nums[idx] = ( isNaN(num) ? 0 : num );
      }
      var conversions = new Array;
      for(var idx=0; (idx<=2); idx++) {
        var num = parseInt( $("#rangeSearchConversion"+idx).val() );
        conversions[idx] = ( isNaN(num) ? 1 : num );
        conversions[idx] = ( conversions[idx]<2 ? 1 : conversions[idx] );
        $("#rangeSearchConversion"+idx).val( conversions[idx] );
      }
      // First normalize to lowest -- as if btnNum == 3
      nums[3] = nums[3]
              + nums[2] * conversions[2]
              + nums[1] * conversions[1] * conversions[2];
      nums[2] = nums[1] = 0;
      nums[6] = nums[6]
              + nums[5] * conversions[2]
              + nums[4] * conversions[1] * conversions[2];
      nums[5] = nums[4] = 0;
      if ( (btnNum == 2) || (btnNum == 1) ) { // Normalize to second
        nums[2] = Math.floor(nums[3] / conversions[2]);
        nums[3] = nums[3] % conversions[2];
        nums[5] = Math.floor(nums[6] / conversions[2]);
        nums[6] = nums[6] % conversions[2];
      }
      if (btnNum == 1) { // Normalize to first
        nums[1] = Math.floor(nums[2] / conversions[1]);
        nums[2] = nums[2] % conversions[1];
        nums[4] = Math.floor(nums[5] / conversions[1]);
        nums[5] = nums[5] % conversions[1];
      }
      for(var idx=1; (idx<=6); idx++) {
        $("#rangeSearch"+idx).val( nums[idx] );
      }
    }
  });

  // --------------------------------------------------------

  $("#range-search-popup button").click(function(e) { e.preventDefault(); });

  // --------------------------------------------------------

  function isRange() {
    return $("#rangeSearchRange").is(":checked");
  }

  // -------------------

  $("#rangeSearchRange").change(function() { showHideSecondTriple(isRange()); });

  // --------------------------------------------------------

  function checkTextfields(curTextFields) {
    for(var textField of curTextFields) {
      if ($(textField).val() == "") { $(textField).val(0); }
      if (!$(textField).val().match(/^\d+$/)) {
        alert(rangeSearchEnterNumber);
        $(textField).focus();
        return false;
      }
    }
    return true;
  }

  // -------------------

  $("#rangeSearchApply").click(function () {
    if (!currentTextArea) { alert(rangeSearchSelectFirst); return; }

    if ($("#rangeSearchUnits").val() == -1) {
      alert(rangeSearchSelectUnit);
      $("#rangeSearchUnits").focus();
      return;
    }

    if (!checkTextfields(textFields.slice(0, 3))) { return; }
    var range = isRange();
    if (range) {
      if (!checkTextfields(textFields.slice(3))) { return; }
    }

    var units = $('#rangeSearchUnits option:selected').text();
    units = units.split("-");

    var result="";
    result += $("#rangeSearch1").val() + units[0] + "-" +
              $("#rangeSearch2").val() + units[1] + "-" +
              $("#rangeSearch3").val() + units[2];

    if (range) {
      result += " - " +
                $("#rangeSearch4").val() + units[0] + "-" +
                $("#rangeSearch5").val() + units[1] + "-" +
                $("#rangeSearch6").val() + units[2];
    }

    currentTextArea.replaceSelectedText(result);
    lightbox.close();
  });

  // --------------------------------------------------------

  $(document).on('lity:close', function(event, lightbox) {
  });

  // --------------------------------------------------------

} );
