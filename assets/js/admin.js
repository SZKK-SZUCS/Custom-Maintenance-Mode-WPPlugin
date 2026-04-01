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
});
