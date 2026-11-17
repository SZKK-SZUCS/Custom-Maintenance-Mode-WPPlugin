// Készítette: Szurofka Márton - MFÜI
jQuery(document).ready(function ($) {
  var mediaUploader;

  $("#cmm-upload-button").click(function (e) {
    e.preventDefault();
    if (mediaUploader) {
      mediaUploader.open();
      return;
    }
    mediaUploader = wp.media.frames.file_frame = wp.media({
      title: "Válassz egy logót",
      button: { text: "Kiválasztás" },
      multiple: false,
    });
    mediaUploader.on("select", function () {
      var attachment = mediaUploader.state().get("selection").first().toJSON();
      $("#cmm_logo_id").val(attachment.id);
      $("#cmm-image-preview")
        .attr("src", attachment.sizes?.thumbnail?.url || attachment.url)
        .show();
      $("#cmm-remove-button").show();
    });
    mediaUploader.open();
  });

  $("#cmm-remove-button").click(function (e) {
    e.preventDefault();
    $("#cmm_logo_id").val("");
    $("#cmm-image-preview").attr("src", "").hide();
    $(this).hide();
  });

  var linksWrapper = $("#cmm-links-wrapper");

  $("#cmm-add-link").click(function (e) {
    e.preventDefault();
    var uniqueIndex = Date.now();

    var newRow = `
            <div class="cmm-link-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="text" name="cmm_settings[links][${uniqueIndex}][label]" value="" placeholder="Gomb (HU)" class="regular-text" style="width: 150px;" />
                <input type="text" name="cmm_settings[links][${uniqueIndex}][label_en]" value="" placeholder="Gomb (EN)" class="regular-text" style="width: 150px;" />
                <input type="url" name="cmm_settings[links][${uniqueIndex}][url]" value="" placeholder="https://..." class="regular-text" style="width: 250px;" />
                <button type="button" class="button button-link-delete cmm-remove-link" style="color: #a00;">Törlés</button>
            </div>
        `;
    linksWrapper.append(newRow);
  });

  linksWrapper.on("click", ".cmm-remove-link", function (e) {
    e.preventDefault();
    $(this).closest(".cmm-link-row").remove();
  });

  // Kiadás kérdése (devtracker): csak akkor van a lapon, ha a szerver szerint kérdeznie kell, és ilyenkor az oldal
  // a mentett állapotban karbantartásban van. A jelölőnégyzet kikapcsolásakor jelenik meg, és választás nélkül a
  // mentés nem megy át. A szerver külön ellenőrzi: kérdés nélküli kikapcsolás nem minősül kiadásnak.
  var $question = $("#cmm-release-question");
  var $active = $('input[name="cmm_settings[is_active]"]');
  if ($question.length && $active.length) {
    var $choices = $question.find('input[type="radio"]');
    var $error = $("#cmm-release-error");

    var syncQuestion = function () {
      var show = !$active.prop("checked");
      $question.prop("hidden", !show);
      // Rejtve letiltva: nem kerül az űrlapba, és nem akadályozza a mentést.
      $choices.prop("disabled", !show);
      if (!show) {
        $choices.prop("checked", false);
        $error.prop("hidden", true);
      }
    };
    $active.on("change", syncQuestion);
    syncQuestion();

    $choices.on("change", function () {
      $error.prop("hidden", true);
    });

    $active.closest("form").on("submit", function (e) {
      if ($question.prop("hidden") || $choices.filter(":checked").length) {
        return;
      }
      e.preventDefault();
      // A kérdés a Beállítások fülön van: ha másik fülről mentenek, oda váltunk.
      $('#cmm-tabs a[href="#settings"]').trigger("click");
      $error.prop("hidden", false);
      $choices.first().trigger("focus");
    });
  }
});
