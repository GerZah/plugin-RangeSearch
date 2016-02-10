jQuery(document).bind("omeka:elementformload", function() {
  var $ = jQuery; // use noConflict version of jQuery as the short $ within this block

  var lightbox = lity(); // https://www.npmjs.com/package/lity

  var textFields = ["#rangeSearch1", "#rangeSearch2", "#rangeSearch3",
                    "#rangeSearch4", "#rangeSearch5", "#rangeSearch6"];

  var curUnits = new Array();
  var autoConversions = new Object();

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

  /**
	* Open data entry / manipulation popup
	*/
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

  /**
	* React on selecting a Triple Unit from the dropdown select
	*/
  $("#rangeSearchUnits").change(function(e) {
    curUnits = $('#rangeSearchUnits option:selected').text();
    curUnits = curUnits.split("-");

    var curSelect = parseInt($("#rangeSearchUnits").val());

    var conversions = new Array();
    autoConversions = new Object();

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
      checkAutoConversions(curSelect);
    }
    prepareAutoConversionsSelect();
  });

  // --------------------------------------------------------

  /**
	* After selecting a Triple Unit, check for compatible = autoconvertable other Triple Units
	*/
  function checkAutoConversions(curSelect) {
    var curGroupId = rangeSearchGroups[1][curSelect];
    var curGroup = rangeSearchGroups[0][curGroupId];

    for(var i=0 ; i<curGroup.length; i++) {
      var potAutoConv = curGroup[i];
      if (curSelect != potAutoConv) {
        var potAutoConvRates = rangeSearchConversions[potAutoConv];
        if (potAutoConvRates.length==3) {
          var potNewUnits = $('#rangeSearchUnits option[value="'+potAutoConv+'"]').text();
          potNewUnits = potNewUnits.split("-");
          for(var a=0; a<3; a++) {
            for (var b=0; b<3; b++) {
              if (curUnits[a] == potNewUnits[b]) {
                // console.log(curUnits[a] + " - " + curSelect+"/"+a + " = " + potAutoConv+"/"+b );
                if ( !(curUnits[a] in autoConversions) ) { autoConversions[curUnits[a]] = new Array(); }
                autoConversions[curUnits[a]].push( new Array(curSelect, a, potAutoConv, b) );
                if ( !autoConversions.hasOwnProperty('convUnits') ) { autoConversions.convUnits = new Array();}
                autoConversions.convUnits.push(curUnits[a]);
              }
            }
          }
          // console.log(autoConversions);
        }
      }
    }
  }

  // --------------------------------------------------------

  /**
	* Hide/show and (if applicable) populate the auto conversion select box
	*/
  function prepareAutoConversionsSelect() {
    $("#rangeSergeAutoConv").empty().unbind("change");
    if (!autoConversions.hasOwnProperty('convUnits')) {
      $("#rangeSergeAutoConvDiv").slideUp();
    }
    else {
      $("#rangeSergeAutoConvDiv").slideDown();
      var selectBelow = $('#rangeSearchUnits option[value="-1"]').text();
      $("#rangeSergeAutoConv").append("<option value='-1' selected>"+selectBelow+"</option>")
      for(var unit=0; unit<autoConversions.convUnits.length; unit++) {
        var curGroup = autoConversions.convUnits[unit];
        var optGroup = "";
        optGroup += "<optgroup label='"+curGroup+"'>";
        var curGroupPoss = autoConversions[curGroup];
        for(var variant=0; variant<curGroupPoss.length; variant++) {
          var curVariant = curGroupPoss[variant];
          var targetTriple = curVariant[2];
          var targetTripelName = $('#rangeSearchUnits option[value="'+targetTriple+'"]').text();
          var curValue = curVariant.join("-");
          optGroup += "<option value='" + curValue + "'>"+targetTripelName+"</option>";
        }
        optGroup += "</optgroup>";
        $("#rangeSergeAutoConv").append(optGroup);
      }

      $("#rangeSergeAutoConv").change(function(e){
        var curAutoConf = $("#rangeSergeAutoConv").val();
        curAutoConf = curAutoConf.split("-");

        var nums = new Array;
        for(var idx=1; (idx<=6); idx++) {
          var num = parseInt( $("#rangeSearch"+idx).val() );
          nums[idx] = ( isNaN(num) ? 0 : num );
        }

        var fromConv = rangeSearchConversions[curAutoConf[0]];
        var fromBtn = parseInt(curAutoConf[1])+1;
        var toConv = rangeSearchConversions[curAutoConf[2]];
        var toBtn = parseInt(curAutoConf[3])+1;

        var nrm = normalizeValues(new Array(nums[1], nums[2], nums[3]), fromConv, fromBtn);
        var deNrm = deNormalizeValue(nrm, toConv, toBtn);
        for(var i=0; i<=2; i++) { nums[1+i] = deNrm[i] };

        var nrm = normalizeValues(new Array(nums[4], nums[5], nums[6]), fromConv, fromBtn);
        var deNrm = deNormalizeValue(nrm, toConv, toBtn);
        for(var i=0; i<=2; i++) { nums[4+i] = deNrm[i] };

        for(var idx=1; (idx<=6); idx++) {
          $("#rangeSearch"+idx).val( nums[idx] );
        }

        $("#rangeSearchUnits option[value='"+curAutoConf[2]+"']")
        .attr('selected',true).change();

      });

    }
  }

  // --------------------------------------------------------

  /**
	* React on clicking on one of the three "Convert" buttons
	*/
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

      var convertedNums = convertValues(new Array(nums[1], nums[2], nums[3]), conversions, btnNum);
      for(var i=0; i<=2; i++) { nums[1+i] = convertedNums[i] };

      var convertedNums = convertValues(new Array(nums[4], nums[5], nums[6]), conversions, btnNum);
      for(var i=0; i<=2; i++) { nums[4+i] = convertedNums[i] };

      for(var idx=1; (idx<=6); idx++) {
        $("#rangeSearch"+idx).val( nums[idx] );
      }
    }
  });

  // --------------------------------------------------------

  /**
	* Spread a number triple based on the triple conversion rates into
  * one of the three components, including modulo calculation for the rest
	*/
  function convertValues(nums, conversions, btnNum) {
    nums.unshift(null);
    // First normalize to lowest -- as if btnNum == 3
    nums[3] = nums[3]
            + nums[2] * conversions[2]
            + nums[1] * conversions[1] * conversions[2];
    nums[2] = nums[1] = 0;
    if ( (btnNum == 2) || (btnNum == 1) ) { // Normalize to second
      nums[2] = Math.floor(nums[3] / conversions[2]);
      nums[3] = nums[3] % conversions[2];
    }
    if (btnNum == 1) { // Normalize to first
      nums[1] = Math.floor(nums[2] / conversions[1]);
      nums[2] = nums[2] % conversions[1];
    }
    for(var i=1; i<=3; i++) { nums[i] = Math.round(nums[i]); }
    nums.shift();
    return nums;
  }

  // --------------------------------------------------------

  /**
	* Push a number triple based on the triple conversion rates
  * into just one float -- probably with fractional digits
	*/
  function normalizeValues(nums, conversions, btnNum) {
    // console.log(nums);
    var result=null;
    nums.unshift(null);
    if (btnNum == 1) {
      result = nums[1]
                + nums[2] / conversions[1]
                + nums[3] / (conversions[1] * conversions[2]);
    }
    else if (btnNum == 2) {
      result = nums[1] * conversions[1]
                + nums[2]
                + nums[3] / conversions[2];
    }
    else if (btnNum == 3) {
      result = nums[1] * (conversions[1] * conversions[2])
                + nums[2] * conversions[2]
                + nums[3];
    }
    return result;
  }

  // --------------------------------------------------------

  function deNormalizeValue(nrm, conversions, btnNum) {
    var nums = [ 0, 0, 0, 0 ];
    nums[btnNum] = nrm;
    nums.shift();
    return convertValues(nums, conversions, 1);
}

  // --------------------------------------------------------

  // Prevent default link reaction for all popup buttons
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

  /**
	* Close tool popup and transfer data back into edit field
	*/
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

    var result="";
    result += $("#rangeSearch1").val() + curUnits[0] + "-" +
              $("#rangeSearch2").val() + curUnits[1] + "-" +
              $("#rangeSearch3").val() + curUnits[2];

    if (range) {
      result += " - " +
                $("#rangeSearch4").val() + curUnits[0] + "-" +
                $("#rangeSearch5").val() + curUnits[1] + "-" +
                $("#rangeSearch6").val() + curUnits[2];
    }

    currentTextArea.replaceSelectedText(result);
    lightbox.close();
  });

  // --------------------------------------------------------

  $(document).on('lity:close', function(event, lightbox) {
  });

  // --------------------------------------------------------

} );
